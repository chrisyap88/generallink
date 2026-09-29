<?php

namespace App\Console\Commands;

use App\Services\CbeMeetingOtpNoticeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 18 Sep 2026 — per Chris: "send 2 notice one 7 days before meeting
// to ask their availability to attend." Runs daily; finds every
// SCHEDULED meeting exactly 7 days out that hasn't had its notice sent
// yet (notice_sent_at is reused from the existing Meeting Notice +
// Agenda + RSVP flow — a Secretary who already manually sent it earlier
// is not double-notified). Sent by the meeting's own organizer
// (uploaded_by), so WhatsApp goes out through THEIR connected number if
// they have one in Integration Hub — see CbeMeetingOtpNoticeService.
class SendMeetingAvailabilityNotices extends Command
{
    protected $signature = 'cbe:send-meeting-availability-notices';
    protected $description = 'Sends the 7-day-before availability/RSVP notice for upcoming SCHEDULED meetings';

    public function handle()
    {
        $targetDate = now()->addDays(7)->toDateString();
        $service = app(CbeMeetingOtpNoticeService::class);

        $meetings = DB::table('cbe_meeting_minutes')
            ->where('status', 'SCHEDULED')
            ->whereNull('notice_sent_at')
            ->whereDate('meeting_date', $targetDate)
            ->get();

        foreach ($meetings as $minute) {
            $service->sendAvailabilityNotice($minute, $minute->uploaded_by);
            $this->info("Availability notice sent for: {$minute->title} ({$minute->meeting_date})");
        }

        $this->info('Done — ' . $meetings->count() . ' meeting(s) notified.');
    }
}
