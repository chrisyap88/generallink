<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\DataScopeService;
use App\Services\EspoCrmService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 19 Jul 2026 — per Chris: separate from the automatic,
// customer-facing renewal reminder (insurance_renewal_schedules,
// task #94), an agent can set their OWN personal follow-up reminders
// against any customer or Prospect in their scope — e.g. "call this
// Prospect back in 3 months" or "call this customer before their car
// insurance lapses to try to close the renewal personally". Multiple
// reminders per customer, each with its own type (call follow-up,
// renewal, etc. — open-ended string, not a rigid enum). These
// consolidate onto the agent's own Calendar & Reminders view alongside
// admin-authored company events and the automatic renewal reminders.
// -------------------------------------------------------
class PersonalReminderController extends Controller
{
    private function rolePrefix($agent): string
    {
        return match ($agent->role) {
            'ADMIN' => 'admin',
            'GROUP_LEADER' => 'gl',
            'TEAM_LEADER' => 'tl',
            default => 'introducer',
        };
    }

    public function store(Request $request, string $customerId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $scope = new DataScopeService();
        $rolePrefix = $this->rolePrefix($agent);

        // Confirm this customer/prospect is actually within the agent's
        // own scope before letting them attach a reminder to it.
        $query = DB::table('customers')->where('customer_id', $customerId)->where('is_deleted', false);
        $scope->applyToCustomers($query);
        $customer = $query->first();
        abort_if(!$customer, 404);

        $validated = $request->validate([
            'reminder_type' => ['required', 'string', 'max:40'],
            'reminder_date' => ['required', 'date'],
            'note'          => ['nullable', 'string', 'max:1000'],
            'submit_action' => ['required', 'in:save,save_send'],
        ]);

        $send = $validated['submit_action'] === 'save_send';
        $reminderId = (string) Str::uuid();

        // NEW 29 Jul 2026 — EspoCRM integration (task #251). Mirror this
        // reminder as a Task in EspoCRM in the background — the agent
        // never sees or touches EspoCRM directly, this just keeps their
        // follow-up living there too (calendar/task ownership moved to
        // EspoCRM per the CRM evaluation decision). If EspoCRM is down or
        // misconfigured, createFollowUpTask() returns null and the
        // GeneralLink reminder is still saved normally — this integration
        // must never block the agent's own workflow.
        $typeLabel = ucwords(strtolower(str_replace('_', ' ', $validated['reminder_type'])));
        $espoTaskId = $espoCrm->createFollowUpTask(
            "{$typeLabel}: {$customer->full_name}",
            $validated['note'] ?? null,
            $validated['reminder_date']
        );

        DB::table('personal_reminders')->insert([
            'reminder_id'    => $reminderId,
            'agent_id'       => $agent->agent_id,
            'customer_id'    => $customerId,
            'reminder_type'  => $validated['reminder_type'],
            'reminder_date'  => $validated['reminder_date'],
            'note'           => $validated['note'] ?? null,
            'espocrm_task_id' => $espoTaskId,
            'status'         => 'PENDING',
            'escalated'      => $send,
            'is_deleted'     => false,
            'created_by'     => $agent->agent_id,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // NEW 19 Jul 2026 — per Chris: "Save and Send" notifies whichever
        // side isn't the person clicking it. Normally that's the agent's
        // own upline chain (they own this customer, so their upline
        // can't otherwise see it — this is a deliberate, opt-in FYI).
        // If it's Admin adding a reminder on a customer they don't
        // personally own (the only role that can reach one), the
        // OWNING agent is notified instead.
        if ($send) {
            $title = 'Follow-up reminder set — ' . $customer->full_name;
            $typeLabel = ucwords(strtolower(str_replace('_', ' ', $validated['reminder_type'])));
            $message = "{$agent->full_name} set a {$typeLabel} reminder for {$customer->full_name} on " .
                \Illuminate\Support\Carbon::parse($validated['reminder_date'])->format('d M Y') .
                (!empty($validated['note']) ? ("\n\nNote: " . $validated['note']) : '');

            if ($agent->agent_id === $customer->owned_by_agent_id) {
                $notifyService = new NotificationService();
                $notifyService->notify(
                    $notifyService->recipientsForUplineChain($agent),
                    'FOLLOW_UP_REMINDER',
                    $title,
                    $message,
                    $agent->agent_id
                );
            } else {
                $owner = Agent::find($customer->owned_by_agent_id);
                if ($owner) {
                    (new NotificationService())->notify([$owner], 'FOLLOW_UP_REMINDER', $title, $message, $agent->agent_id);
                }
            }
        }

        return redirect()->route($rolePrefix . '.customers.show', $customerId)->with('success', $send ? 'Reminder added and sent.' : 'Reminder added.');
    }

    public function markDone(string $reminderId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $reminder = DB::table('personal_reminders')->where('reminder_id', $reminderId)->where('is_deleted', false)->first();
        abort_if(!$reminder, 404);
        // Personal reminders are private to whoever set them — not
        // scope-shared like customers/transactions.
        abort_if($reminder->agent_id !== $agent->agent_id, 403);

        DB::table('personal_reminders')->where('reminder_id', $reminderId)->update([
            'status' => 'DONE',
            'updated_at' => now(),
        ]);

        // NEW 29 Jul 2026 — EspoCRM integration (task #251). Keep the
        // mirrored Task in sync — failures here are non-fatal, see
        // EspoCrmService::completeTask().
        if (!empty($reminder->espocrm_task_id)) {
            $espoCrm->completeTask($reminder->espocrm_task_id);
        }

        return redirect()->route($rolePrefix . '.customers.show', $reminder->customer_id)->with('success', 'Reminder marked as done.');
    }

    public function destroy(string $reminderId, EspoCrmService $espoCrm)
    {
        $agent = auth('agent')->user();
        $rolePrefix = $this->rolePrefix($agent);

        $reminder = DB::table('personal_reminders')->where('reminder_id', $reminderId)->where('is_deleted', false)->first();
        abort_if(!$reminder, 404);
        abort_if($reminder->agent_id !== $agent->agent_id, 403);

        DB::table('personal_reminders')->where('reminder_id', $reminderId)->update([
            'is_deleted' => true,
            'updated_at' => now(),
        ]);

        // NEW 29 Jul 2026 — EspoCRM integration (task #251).
        if (!empty($reminder->espocrm_task_id)) {
            $espoCrm->deleteTask($reminder->espocrm_task_id);
        }

        return redirect()->route($rolePrefix . '.customers.show', $reminder->customer_id)->with('success', 'Reminder removed.');
    }
}
