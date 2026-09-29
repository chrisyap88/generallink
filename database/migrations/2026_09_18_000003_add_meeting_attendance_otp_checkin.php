<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: two automated notices, both via WhatsApp
// + Email (+ in-app):
//   1) 7 days before a SCHEDULED meeting — an availability/RSVP ask
//      (reuses the existing cbe_meeting_rsvps / notice_sent_at flow,
//      just adds WhatsApp and a daily auto-trigger — see
//      SendMeetingAvailabilityNotices).
//   2) When the meeting begins, physical or online — the Secretary taps
//      "Start Attendance Check-In" and every roster attendee gets a
//      personal one-tap confirmation link by WhatsApp/Email. Tapping it
//      (while logged into GeneralLink as themselves) marks them Present
//      — "upon respond/acceptance mean attending". This is a per-agent
//      one-time link/token, NOT the Join Time/Leave Time fields Chris
//      already asked removed — checkin_confirmed_at is only ever set
//      automatically by their own tap, never typed in by the organizer.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'checkin_token')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->string('checkin_token', 64)->nullable()->after('attendance_status');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'checkin_token_expires_at')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->timestamp('checkin_token_expires_at')->nullable()->after('checkin_token');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'checkin_confirmed_at')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->timestamp('checkin_confirmed_at')->nullable()->after('checkin_token_expires_at');
            });
        }

        // Reuses the existing notice_sent_at column (already tracks the
        // Meeting Notice + Agenda / RSVP-ask send) for the 7-day
        // availability notice — no separate column needed.
        if (! Schema::hasColumn('cbe_meeting_minutes', 'checkin_opened_at')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->timestamp('checkin_opened_at')->nullable()->after('minutes_approved_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['checkin_token', 'checkin_token_expires_at', 'checkin_confirmed_at'] as $col) {
            if (Schema::hasColumn('cbe_meeting_minute_attendees', $col)) {
                Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
        foreach (['checkin_opened_at'] as $col) {
            if (Schema::hasColumn('cbe_meeting_minutes', $col)) {
                Schema::table('cbe_meeting_minutes', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
