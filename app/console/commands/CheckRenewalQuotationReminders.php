<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 18 Jul 2026 — escalating reminders for renewal quotation
// requests still sitting on REQUESTED (customer said "Yes Renew,
// please give me the renewal insurance coverage quotation" but the
// Introducer hasn't marked it sent yet). Mirrors the existing
// CheckApprovalReminders command exactly: per-row timestamps guard
// against sending the same escalation level twice, ever.
//
// Timeline (days since the customer's request, not calendar days):
//   Day 3  -> remind the Introducer only
//   Day 5  -> notify the Team Leader (Introducer missed the nudge)
//   Day 7  -> notify the Group Leader
//   Day 10 -> escalate to Admin as overdue
// -------------------------------------------------------
class CheckRenewalQuotationReminders extends Command
{
    protected $signature = 'renewals:check-quotation-reminders';
    protected $description = 'Escalates renewal quotation requests that have sat unfulfilled too long (Introducer -> TL -> GL -> Admin)';

    private int $day1 = 3;
    private int $day2 = 5;
    private int $day3 = 7;
    private int $day4 = 10;

    public function handle()
    {
        $notificationService = app(NotificationService::class);

        // CHANGED 20 Jul 2026 — per Chris: these 4 escalation days are
        // now configurable under Admin > Notification Setup
        // (system_settings keys below) instead of hardcoded, falling
        // back to the original 3/5/7/10 if never set.
        $this->day1 = (int) (DB::table('system_settings')->where('setting_key', 'quotation_escalation_day1')->value('setting_value') ?? 3);
        $this->day2 = (int) (DB::table('system_settings')->where('setting_key', 'quotation_escalation_day2')->value('setting_value') ?? 5);
        $this->day3 = (int) (DB::table('system_settings')->where('setting_key', 'quotation_escalation_day3')->value('setting_value') ?? 7);
        $this->day4 = (int) (DB::table('system_settings')->where('setting_key', 'quotation_escalation_day4')->value('setting_value') ?? 10);

        DB::table('renewal_quotation_requests as rqr')
            ->join('sales_transactions as st', 'rqr.policy_id', '=', 'st.policy_id')
            ->join('customers as c', 'rqr.customer_id', '=', 'c.customer_id')
            ->where('rqr.status', 'REQUESTED')
            ->select('rqr.*', 'st.document_reference_number', 'c.full_name as customer_name')
            ->orderBy('rqr.request_id')
            ->chunkById(500, function ($requests) use ($notificationService) {
                foreach ($requests as $req) {
                    $this->processOne($req, $notificationService);
                }
            }, 'rqr.request_id');

        $this->info('Renewal quotation reminder check complete.');
    }

    private function processOne(object $req, NotificationService $notificationService): void
    {
        $daysElapsed = now()->diffInDays($req->requested_at);
        $owner = Agent::find($req->agent_id);
        if (!$owner) return;

        $subjectLine = "Customer {$req->customer_name} requested a renewal quotation for policy {$req->document_reference_number} {$daysElapsed} day(s) ago and it hasn't been sent yet.";

        if ($daysElapsed >= $this->day1 && !$req->introducer_reminder_sent_at) {
            $notificationService->notify([$owner], 'RENEWAL_QUOTATION_REMINDER',
                'Reminder: Renewal Quotation Still Owed', $subjectLine, $req->agent_id);
            DB::table('renewal_quotation_requests')->where('request_id', $req->request_id)
                ->update(['introducer_reminder_sent_at' => now(), 'updated_at' => now()]);
            $this->info("Introducer reminded: {$req->request_id}");
        }

        if ($daysElapsed >= $this->day2 && !$req->tl_reminder_sent_at) {
            $tl = $owner->parent_id ? Agent::find($owner->parent_id) : null;
            if ($tl) {
                $notificationService->notify([$tl], 'RENEWAL_QUOTATION_REMINDER',
                    'Renewal Quotation Overdue — Your Introducer Hasn\'t Sent It',
                    $subjectLine . " Introducer: {$owner->full_name} ({$owner->agent_code}).", $req->agent_id);
            }
            DB::table('renewal_quotation_requests')->where('request_id', $req->request_id)
                ->update(['tl_reminder_sent_at' => now(), 'updated_at' => now()]);
            $this->info("TL notified: {$req->request_id}");
        }

        if ($daysElapsed >= $this->day3 && !$req->gl_reminder_sent_at) {
            $gl = $owner->group_id
                ? Agent::where('group_id', $owner->group_id)->where('role', 'GROUP_LEADER')->where('is_deleted', false)->first()
                : null;
            if ($gl) {
                $notificationService->notify([$gl], 'RENEWAL_QUOTATION_REMINDER',
                    'Renewal Quotation Overdue — Group Escalation',
                    $subjectLine . " Introducer: {$owner->full_name} ({$owner->agent_code}).", $req->agent_id);
            }
            DB::table('renewal_quotation_requests')->where('request_id', $req->request_id)
                ->update(['gl_reminder_sent_at' => now(), 'updated_at' => now()]);
            $this->info("GL notified: {$req->request_id}");
        }

        if ($daysElapsed >= $this->day4 && !$req->admin_escalation_sent_at) {
            $admins = Agent::where('role', 'ADMIN')->where('is_deleted', false)->get();
            if ($admins->isNotEmpty()) {
                $notificationService->notify($admins->all(), 'RENEWAL_QUOTATION_URGENT_ESCALATION',
                    'Urgent: Renewal Quotation Overdue 10+ Days',
                    $subjectLine . " Introducer: {$owner->full_name} ({$owner->agent_code}). No one in the chain has acted.", $req->agent_id);
            }
            DB::table('renewal_quotation_requests')->where('request_id', $req->request_id)
                ->update(['admin_escalation_sent_at' => now(), 'updated_at' => now()]);
            $this->info("Admin escalated: {$req->request_id}");
        }
    }
}
