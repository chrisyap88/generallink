<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 22 Jul 2026 — same reminder/escalation shape as
// CheckApprovalReminders: a HIGH/CRITICAL flag already notifies Admin
// immediately when it's created (see FraudDetectionService::flag());
// this command is the safety net for anything still sitting unresolved
// after that — reminds all Admins at the configured hour mark, then
// escalates to the Admin Director specifically if it's still
// unresolved after that. Safe to run hourly — reminder_sent_at/
// escalated_at guard against double-sending, same pattern as approvals.
class CheckFraudReviewReminders extends Command
{
    protected $signature = 'fraud-review:check-reminders';
    protected $description = 'Sends reminder/escalation notifications for fraud review flags that have sat unresolved too long';

    public function handle()
    {
        $reminderHours = (int) (DB::table('system_settings')->where('setting_key', 'fraud_review_reminder_hours')->value('setting_value') ?? 24);
        $escalationHours = (int) (DB::table('system_settings')->where('setting_key', 'fraud_review_escalation_hours')->value('setting_value') ?? 48);

        $notificationService = app(NotificationService::class);
        $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
        $director = Agent::where('role', 'ADMIN')->where('department', 'DIRECTOR')->first();

        $unresolved = DB::table('fraud_review_flags')->whereIn('status', ['OPEN', 'UNDER_REVIEW'])->get();

        foreach ($unresolved as $flag) {
            $hoursElapsed = now()->diffInHours($flag->created_at);

            if ($hoursElapsed >= $reminderHours && !$flag->reminder_sent_at) {
                $notificationService->notify(
                    $admins->all(),
                    'FRAUD_REVIEW_REMINDER',
                    'Reminder: Fraud Review Flag Pending',
                    "A {$flag->risk_level} risk submission (score {$flag->risk_score}/100) has been awaiting review for {$hoursElapsed}h.",
                    $flag->agent_id
                );
                DB::table('fraud_review_flags')->where('flag_id', $flag->flag_id)->update(['reminder_sent_at' => now()]);
                $this->info("Reminded: {$flag->flag_id}");
            }

            if ($hoursElapsed >= $escalationHours && !$flag->escalated_at && $director) {
                $notificationService->notify(
                    [$director],
                    'FRAUD_REVIEW_ESCALATION',
                    'Urgent: Fraud Review Flag Overdue',
                    "A {$flag->risk_level} risk submission (score {$flag->risk_score}/100) has been unresolved for {$hoursElapsed}h. Escalating to you as Admin Director.",
                    $flag->agent_id
                );
                DB::table('fraud_review_flags')->where('flag_id', $flag->flag_id)->update(['escalated_at' => now()]);
                $this->info("Escalated: {$flag->flag_id}");
            }
        }

        $this->info('Fraud review reminder check complete.');
    }
}
