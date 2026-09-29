<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" —
// Statutory Compliance Reminders was one of the 7 approved secretarial
// gaps): each cbe_compliance_items row recurs every year on its own
// due_month/due_day; this command notifies this entity's officers +
// Admin once today falls within reminder_lead_days of that date.
// last_notified_year guards against sending the same year's reminder
// twice — checked/reset naturally since it's compared against the
// CURRENT year, so next year's occurrence notifies again on its own.
class CheckComplianceReminders extends Command
{
    protected $signature = 'cbe:check-compliance-reminders';
    protected $description = 'Notifies officers/Secretary when a statutory compliance deadline is approaching';

    public function handle()
    {
        $today = now();
        $notificationService = app(NotificationService::class);

        $items = DB::table('cbe_compliance_items')->where('is_active', true)->get();

        foreach ($items as $item) {
            if ((int) $item->last_notified_year === $today->year) {
                continue;
            }

            // This year's occurrence of the due date (handles 29 Feb on
            // a non-leap year by falling back to 28 Feb, never crashes).
            try {
                $dueDate = \Carbon\Carbon::create($today->year, $item->due_month, $item->due_day);
            } catch (\Throwable $e) {
                $dueDate = \Carbon\Carbon::create($today->year, $item->due_month, 28);
            }

            $daysUntilDue = $today->startOfDay()->diffInDays($dueDate->startOfDay(), false);

            if ($daysUntilDue < 0 || $daysUntilDue > $item->reminder_lead_days) {
                continue;
            }

            $officers = Agent::whereIn('agent_id', DB::table('cbe_node_officers')
                ->where('node_id', $item->cbe_node_id)->where('is_active', true)->pluck('agent_id'))
                ->where('is_deleted', false)->get();
            $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
            $recipients = $officers->merge($admins)->unique('agent_id');

            $notificationService->notify(
                $recipients->all(),
                'CBE_COMPLIANCE_REMINDER',
                'Compliance Deadline Approaching',
                "{$item->label} is due on {$dueDate->format('d M Y')} ({$daysUntilDue} day(s) left)."
            );

            DB::table('cbe_compliance_items')->where('compliance_item_id', $item->compliance_item_id)->update(['last_notified_year' => $today->year, 'updated_at' => now()]);
            $this->info("Notified: {$item->compliance_item_id}");
        }

        $this->info('Compliance reminder check complete.');
    }
}
