<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

// -------------------------------------------------------
// NEW 18 Jul 2026 — Stage 0 of the renewal flow: finds insurance
// policies whose coverage is about to end and sends the initial
// reminder (coverage summary + secure "Yes Renew" response link) to
// the customer, cc'ing the owning Introducer + their TL + GL + Admin,
// per Chris's confirmed distribution list.
//
// Runs once per policy per renewal cycle — reminder_sent_at on
// insurance_renewal_schedules guards against sending it twice.
// -------------------------------------------------------
class SendRenewalReminders extends Command
{
    protected $signature = 'renewals:send-reminders {--days= : How many days before coverage_end to start reminding (overrides Notification Setup)}';
    protected $description = 'Sends the initial renewal reminder (with secure response link) for policies approaching their coverage end date';

    public function handle()
    {
        // CHANGED 20 Jul 2026 — per Chris: this lead time is now
        // configurable under Admin > Notification Setup
        // (system_settings key renewal_reminder_days) instead of a
        // hardcoded 30. The --days option still works if you want to
        // override it for a one-off manual run.
        $daysAhead = (int) ($this->option('days') ?? \Illuminate\Support\Facades\DB::table('system_settings')->where('setting_key', 'renewal_reminder_days')->value('setting_value') ?? 30);
        $cutoff = now()->addDays($daysAhead)->toDateString();

        // Chunk — this table can hold millions of rows in production
        // (per Chris: ~1 million renewals/month), so never load them
        // all into memory at once.
        DB::table('insurance_renewal_schedules as irs')
            ->join('sales_transactions as st', 'irs.policy_id', '=', 'st.policy_id')
            ->where('st.is_deleted', false)
            ->whereIn('irs.status', ['UPCOMING', 'DUE'])
            ->whereNull('irs.reminder_sent_at')
            ->whereDate('irs.coverage_end', '<=', $cutoff)
            ->whereDate('irs.coverage_end', '>=', now()->toDateString())
            ->select('irs.renewal_id', 'irs.policy_id', 'st.customer_id', 'st.agent_id', 'irs.reminder_message')
            ->orderBy('irs.renewal_id')
            ->chunkById(500, function ($renewals) {
                foreach ($renewals as $renewal) {
                    $this->sendOne($renewal);
                }
            }, 'irs.renewal_id');

        $this->info('Renewal reminder sweep complete.');
    }

    private function sendOne(object $renewal): void
    {
        $customer = DB::table('customers')->where('customer_id', $renewal->customer_id)->first();
        if (!$customer || !$customer->email) {
            // No email on file — can't send a link at all. Leave
            // reminder_sent_at null so it stays visible as "not yet
            // reminded" rather than silently marking it done.
            return;
        }

        $owner = DB::table('agents')->where('agent_id', $renewal->agent_id)->first();

        $signedUrl = URL::temporarySignedRoute(
            'renewal.response.show',
            now()->addDays(45),
            ['policyId' => $renewal->policy_id]
        );

        $ccEmails = $this->ownerChainEmails($renewal->agent_id);

        // NEW 19 Jul 2026 — if this policy has a stored reminder_message
        // (captured on the create screen at submission time, per Chris),
        // use it as the source of truth and just swap in the real signed
        // link now that we're actually sending. Falls back to the old
        // inline-built text for any older row created before this column
        // existed, so nothing already in the pipeline breaks.
        $body = !empty($renewal->reminder_message)
            ? str_replace('{{RENEWAL_LINK}}', $signedUrl, $renewal->reminder_message)
            : "Dear {$customer->full_name},\n\n" .
              "Your policy is due for renewal soon. Please review your current coverage and let us know how you'd like to proceed:\n\n" .
              "{$signedUrl}\n\n" .
              ($owner ? "Your agent, {$owner->full_name} ({$owner->phone}, {$owner->email}), is also available to help.\n\n" : '') .
              "GeneralLink";

        Mail::raw(
            $body,
            function ($mail) use ($customer, $ccEmails) {
                $mail->to($customer->email)->subject('Renewal Reminder — Action Needed');
                if (!empty($ccEmails)) {
                    $mail->cc($ccEmails);
                }
            }
        );

        DB::table('insurance_renewal_schedules')
            ->where('renewal_id', $renewal->renewal_id)
            ->update(['reminder_sent_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Introducer + their TL + GL + Admin — same chain used for
     * escalation, so the reminder email and the escalation reminders
     * always reach the same people.
     */
    private function ownerChainEmails(string $agentId): array
    {
        $emails = [];
        $current = DB::table('agents')->where('agent_id', $agentId)->first();
        if (!$current) return $emails;

        if ($current->email) $emails[] = $current->email;

        while ($current && $current->parent_id) {
            $parent = DB::table('agents')->where('agent_id', $current->parent_id)->first();
            if (!$parent) break;
            if ($parent->email) $emails[] = $parent->email;
            if ($parent->role === 'GROUP_LEADER') break;
            $current = $parent;
        }

        $admins = DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->pluck('email')->filter()->all();

        return array_values(array_unique(array_merge($emails, $admins)));
    }
}
