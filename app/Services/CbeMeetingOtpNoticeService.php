<?php

namespace App\Services;

use App\Services\Integrations\Communication\WhatsAppCloudConnector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 18 Sep 2026 — per Chris's follow-up on the Online Meeting
// Attendance system: "not every time need to input... suggest OTP on
// whatsapp and email otp la, upon respond/acceptance mean attending."
// Two notices, both pushed by WhatsApp + Email + in-app:
//   1) sendAvailabilityNotice() — 7 days before a SCHEDULED meeting,
//      asking members to RSVP (reuses the existing Meeting Notice +
//      Agenda + RSVP flow, just adds WhatsApp on top of the Email/
//      in-app NotificationService already sends).
//   2) openAttendanceCheckin() — Secretary-initiated at the moment a
//      meeting (physical or online) begins. Each roster attendee gets a
//      personal one-tap confirmation link/token. Tapping it while
//      logged in as themselves marks them Present — no code to type,
//      no Join/Leave time. This is genuinely a one-time link per
//      person (an "OTP" in the sense of a one-time confirmation token),
//      not a shared code, so one member can never check in for another.
//
// WhatsApp reuses the SAME Meta WhatsApp Business Cloud connector and
// per-agent Integration Hub credentials already built for Notice Board
// pushes (see NoticeDeliveryService) — whichever officer/Secretary
// initiates the send needs their own WhatsApp connected in Integration
// Hub first; until then this quietly falls back to Email + in-app only
// (never blocks the notice).
class CbeMeetingOtpNoticeService
{
    public function __construct(
        private HubVaultService $vault,
        private WhatsAppCloudConnector $whatsapp,
    ) {}

    public function sendAvailabilityNotice(object $minute, string $senderAgentId): int
    {
        $recipients = \App\Models\Agent::whereIn('agent_id', DB::table('cbe_group_memberships')
            ->where('cbe_node_id', $minute->cbe_node_id)->where('status', 'ACTIVE')->pluck('agent_id'))
            ->where('is_deleted', false)->get();

        $meetingDate = \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y');
        $message = "You are invited to: {$minute->title}\nDate: {$meetingDate}"
            . ($minute->meeting_time ? ' ' . \Carbon\Carbon::parse($minute->meeting_time)->format('g:i A') : '')
            . ($minute->meeting_mode === 'ONLINE' ? "\nOnline Meeting" : ($minute->venue ? "\nVenue: {$minute->venue}" : ''))
            . ($minute->agenda ? "\n\nAgenda:\n{$minute->agenda}" : '')
            . ($minute->quorum_required ? "\n\nPlease RSVP — at least {$minute->quorum_required} confirmed attendees are needed for quorum." : "\n\nPlease RSVP so we know you're coming.");

        app(NotificationService::class)->notify($recipients->all(), 'CBE_MEETING_NOTICE', 'Meeting Notice: ' . $minute->title, $message);

        foreach ($recipients as $agent) {
            $this->sendWhatsApp($senderAgentId, $agent, $message);
        }

        DB::table('cbe_meeting_minutes')->where('minute_id', $minute->minute_id)->update(['notice_sent_at' => now(), 'updated_at' => now()]);

        return $recipients->count();
    }

    public function openAttendanceCheckin(object $minute, string $senderAgentId, int $windowMinutes = 90): int
    {
        $attendees = DB::table('cbe_meeting_minute_attendees as at')
            ->join('agents as a', 'a.agent_id', '=', 'at.agent_id')
            ->where('at.minute_id', $minute->minute_id)
            ->where('a.is_deleted', false)
            ->get(['at.attendee_id', 'a.agent_id', 'a.full_name', 'a.email', 'a.phone']);

        $expiresAt = now()->addMinutes($windowMinutes);
        $sent = 0;

        foreach ($attendees as $at) {
            $token = Str::random(40);
            DB::table('cbe_meeting_minute_attendees')->where('attendee_id', $at->attendee_id)->update([
                'checkin_token' => $token,
                'checkin_token_expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);

            $link = route('cbe.minutes.checkin.show', $token);
            $message = "GeneralLink — Confirm your attendance for \"{$minute->title}\" now that it has begun. Tap to confirm: {$link}\n\nThis link expires at " . $expiresAt->format('g:i A') . '.';

            $agentObj = \App\Models\Agent::find($at->agent_id);
            if ($agentObj) {
                app(NotificationService::class)->notify([$agentObj], 'CBE_ATTENDANCE_CHECKIN', 'Confirm Attendance: ' . $minute->title, $message);
                $this->sendWhatsApp($senderAgentId, $agentObj, $message);
            }
            $sent++;
        }

        DB::table('cbe_meeting_minutes')->where('minute_id', $minute->minute_id)->update(['checkin_opened_at' => now(), 'updated_at' => now()]);

        return $sent;
    }

    private function sendWhatsApp(string $senderAgentId, object $agent, string $message): void
    {
        if (empty($agent->phone)) {
            return;
        }
        if (! $this->vault->isUnlocked($senderAgentId)) {
            return;
        }
        $row = DB::table('agent_integrations')->where('agent_id', $senderAgentId)
            ->where('category', 'communication')->where('provider', 'whatsapp')->where('status', 'CONNECTED')->first();
        if (! $row) {
            return;
        }
        try {
            $credentials = [
                'client_id' => $this->vault->decryptSecret($senderAgentId, $row->client_id_encrypted),
                'client_secret' => $this->vault->decryptSecret($senderAgentId, $row->client_secret_encrypted),
            ];
        } catch (\Throwable $e) {
            return;
        }
        $this->whatsapp->sendMessage($credentials, $agent->phone, $message);
    }
}
