<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\ApprovalService;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckApprovalReminders extends Command
{
    protected $signature = 'approvals:check-reminders';
    protected $description = 'Sends reminder/escalation notifications for pending approvals that have sat too long, per Admin Director\'s configured durations';

    public function handle()
    {
        $reminderHours = (int) (DB::table('system_settings')->where('setting_key', 'approval_reminder_hours')->value('setting_value') ?? 24);
        $escalationHours = (int) (DB::table('system_settings')->where('setting_key', 'approval_escalation_hours')->value('setting_value') ?? 48);

        $approvalService = app(ApprovalService::class);
        $notificationService = app(NotificationService::class);

        $pending = DB::table('pending_approvals')->where('status', 'PENDING')->get();

        foreach ($pending as $approval) {
            $hoursElapsed = now()->diffInHours($approval->created_at);

            // Eligible approvers, respecting department routing, minus
            // anyone currently marked On Leave — no point reminding
            // someone who told the system they're away.
            $eligible = $approvalService->getEligibleApprovers($approval->requested_by, $approval->action_type);
            $available = array_filter($eligible, fn ($a) => $a->availability_status === 'AVAILABLE');
            $director = Agent::where('role', 'ADMIN')->where('department', 'DIRECTOR')->first();

            // If EVERYONE eligible is On Leave, escalate immediately —
            // waiting serves no purpose if literally nobody can act,
            // regardless of the configured timer.
            $everyoneUnavailable = count($eligible) > 0 && count($available) === 0;

            if ($everyoneUnavailable && !$approval->escalation_sent && $director) {
                $notificationService->notify(
                    [$director],
                    'APPROVAL_URGENT_ESCALATION',
                    'Urgent: All Eligible Approvers Unavailable',
                    "A pending approval has been waiting {$hoursElapsed}h, but every eligible Admin is currently On Leave. Your action is needed.",
                    $approval->target_agent_id
                );
                DB::table('pending_approvals')->where('approval_id', $approval->approval_id)->update(['escalation_sent' => true, 'reminder_sent' => true]);
                $this->info("Escalated (all unavailable): {$approval->approval_id}");
                continue;
            }

            // Normal reminder — first threshold crossed.
            if ($hoursElapsed >= $reminderHours && !$approval->reminder_sent) {
                if (!empty($available)) {
                    $notificationService->notify(
                        $available,
                        'APPROVAL_NEEDED',
                        'Reminder: Approval Still Pending',
                        "A pending approval has been waiting {$hoursElapsed}h for your decision. Please review it when you can.",
                        $approval->target_agent_id
                    );
                }
                DB::table('pending_approvals')->where('approval_id', $approval->approval_id)->update(['reminder_sent' => true]);
                $this->info("Reminded: {$approval->approval_id}");
            }

            // Escalation — second, longer threshold crossed, still
            // unresolved.
            if ($hoursElapsed >= $escalationHours && !$approval->escalation_sent && $director) {
                $notificationService->notify(
                    [$director],
                    'APPROVAL_URGENT_ESCALATION',
                    'Urgent: Approval Overdue',
                    "A pending approval has been waiting {$hoursElapsed}h with no decision. Escalating to you as Admin Director.",
                    $approval->target_agent_id
                );
                DB::table('pending_approvals')->where('approval_id', $approval->approval_id)->update(['escalation_sent' => true]);
                $this->info("Escalated (overdue): {$approval->approval_id}");
            }
        }

        $this->info('Approval reminder check complete.');
    }
}
