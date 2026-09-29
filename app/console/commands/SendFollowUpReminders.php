<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 20 Jul 2026 — per Chris: "Save & Send" on a personal Follow Up
// Reminder previously only ever notified once, immediately, at
// creation time — nothing reminded the agent again when the actual
// reminder_date arrived. This command closes that gap, following the
// exact same pattern already used by renewals:send-reminders — runs
// daily, finds reminders due today (or overdue, e.g. if the scheduler
// was down a day), and sends the on-date bell + email notification
// to the agent who set it. notification_sent_at guards against ever
// sending the same reminder twice, same role reminder_sent_at plays
// on insurance_renewal_schedules.
//
// Recipient is always the agent who created the reminder (the
// "sender") — NOT the upline chain used by Save & Send's immediate
// notification. Save & Send's upline ping is a separate, one-time
// "FYI I've set this" message; this command's job is purely to remind
// the person who set the reminder that today is the day, exactly like
// setting an alarm for yourself.
// -------------------------------------------------------
class SendFollowUpReminders extends Command
{
    protected $signature = 'reminders:send-followups';
    protected $description = 'Sends the on-date bell + email notification for personal follow-up reminders due today (or overdue)';

    public function handle()
    {
        DB::table('personal_reminders as pr')
            ->where('pr.status', 'PENDING')
            ->where('pr.is_deleted', false)
            ->whereNull('pr.notification_sent_at')
            ->whereDate('pr.reminder_date', '<=', now()->toDateString())
            ->select('pr.reminder_id', 'pr.agent_id', 'pr.customer_id', 'pr.reminder_type', 'pr.reminder_date', 'pr.note')
            ->orderBy('pr.reminder_id')
            ->chunkById(500, function ($reminders) {
                foreach ($reminders as $reminder) {
                    $this->sendOne($reminder);
                }
            }, 'pr.reminder_id');

        $this->info('Follow-up reminder sweep complete.');
    }

    private function sendOne(object $reminder): void
    {
        $agent = Agent::find($reminder->agent_id);
        if (!$agent) {
            // Owning agent no longer exists — nothing to notify, but
            // leave notification_sent_at null rather than silently
            // marking it done, same "stay visible" convention used by
            // SendRenewalReminders.
            return;
        }

        $customer = DB::table('customers')->where('customer_id', $reminder->customer_id)->first();
        $customerName = $customer->full_name ?? 'this contact';

        $typeLabels = ['CALL_FOLLOW_UP' => 'Call Follow-up', 'RENEWAL' => 'Renewal', 'OTHER' => 'Other'];
        $typeLabel = $typeLabels[$reminder->reminder_type] ?? $reminder->reminder_type;

        $title = "Reminder due today — {$customerName}";
        $message = "Your {$typeLabel} reminder for {$customerName} is due today (" .
            \Illuminate\Support\Carbon::parse($reminder->reminder_date)->format('d M Y') . ")." .
            (!empty($reminder->note) ? ("\n\nNote: " . $reminder->note) : '');

        (new NotificationService())->notify([$agent], 'FOLLOW_UP_REMINDER', $title, $message, $agent->agent_id);

        DB::table('personal_reminders')
            ->where('reminder_id', $reminder->reminder_id)
            ->update(['notification_sent_at' => now(), 'updated_at' => now()]);
    }
}
