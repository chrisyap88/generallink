<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris's uploaded "Online Meeting Attendance &
// Meeting Minutes System" requirement, kept as an EXTENSION of the
// existing Meeting Minutes module (not a separate system — his own
// choice) plus the existing Secretary Activity Report:
//   1) Attendance: each attendee row can now carry an actual Join Time /
//      Leave Time (organizer enters these after the meeting — see the
//      chat log: real Zoom/Meet/Teams auto-capture needs a public HTTPS
//      webhook endpoint, which localhost XAMPP cannot receive, so v1 is
//      manual entry with the table shaped so a real integration can
//      plug in later without rework). duration_minutes and
//      attendance_status (PRESENT/PARTIAL/ABSENT) are computed
//      server-side from join/leave vs the meeting's start/end time.
//   2) Online vs Physical meeting + a meeting link field.
//   3) ai_draft_status / minutes_approved_at — an AI-drafted set of
//      content_sections (Discussion/Decisions/Action Items/Other
//      Matters/Next Meeting) must be reviewed and explicitly approved
//      by the organizer before being treated as final, per Chris's
//      requirement doc section 8.
//   4) cbe_activities and cbe_temple_calendar_events gain a venue (and
//      activities gain a time) so the Secretary Activity Report can show
//      Date, Time, Venue for EVERY activity type, not just meetings —
//      needed because Chris wants this report (his "association diary"
//      / ROS annual activity report) to combine actual records
//      (meetings, activities) with planned/upcoming ones (the temple
//      calendar) into one list, each line summarised to under 100
//      characters — see AnnualReportController::secretaryReport().
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_meeting_minutes', 'meeting_mode')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->enum('meeting_mode', ['ONLINE', 'PHYSICAL'])->default('PHYSICAL')->after('venue');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'meeting_link')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->string('meeting_link', 500)->nullable()->after('meeting_mode');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'meeting_end_time')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->time('meeting_end_time')->nullable()->after('meeting_time');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'transcript_path')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->string('transcript_path', 500)->nullable()->after('meeting_end_time');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'transcript_original_name')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->string('transcript_original_name', 255)->nullable()->after('transcript_path');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'ai_draft_status')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->enum('ai_draft_status', ['NONE', 'DRAFTED', 'APPROVED'])->default('NONE')->after('content_sections');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'minutes_approved_at')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->timestamp('minutes_approved_at')->nullable()->after('ai_draft_status');
            });
        }

        // UPDATED 18 Sep 2026 — per Chris: "remove Join Time and Leave
        // Time completely" — join_time/leave_time columns removed from
        // this migration (see 2026_09_18_000002, which also drops them
        // if this migration already ran and created them). Attendance
        // is now just Participant Name, Attendance Status and Attendance
        // Duration, entered directly.
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'duration_minutes')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->unsignedInteger('duration_minutes')->nullable()->after('attended');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'attendance_status')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->enum('attendance_status', ['PRESENT', 'PARTIAL', 'ABSENT'])->default('ABSENT')->after('duration_minutes');
            });
        }

        if (! Schema::hasColumn('cbe_activities', 'activity_time')) {
            Schema::table('cbe_activities', function (Blueprint $table) {
                $table->time('activity_time')->nullable()->after('activity_date');
            });
        }
        if (! Schema::hasColumn('cbe_activities', 'venue')) {
            Schema::table('cbe_activities', function (Blueprint $table) {
                $table->string('venue', 255)->nullable()->after('title');
            });
        }

        if (! Schema::hasColumn('cbe_temple_calendar_events', 'venue')) {
            Schema::table('cbe_temple_calendar_events', function (Blueprint $table) {
                $table->string('venue', 255)->nullable()->after('event_time');
            });
        }
    }

    public function down(): void
    {
        foreach (['meeting_mode', 'meeting_link', 'meeting_end_time', 'transcript_path', 'transcript_original_name', 'ai_draft_status', 'minutes_approved_at'] as $col) {
            if (Schema::hasColumn('cbe_meeting_minutes', $col)) {
                Schema::table('cbe_meeting_minutes', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
        foreach (['duration_minutes', 'attendance_status'] as $col) {
            if (Schema::hasColumn('cbe_meeting_minute_attendees', $col)) {
                Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
        foreach (['activity_time', 'venue'] as $col) {
            if (Schema::hasColumn('cbe_activities', $col)) {
                Schema::table('cbe_activities', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
        if (Schema::hasColumn('cbe_temple_calendar_events', 'venue')) {
            Schema::table('cbe_temple_calendar_events', function (Blueprint $table) {
                $table->dropColumn('venue');
            });
        }
    }
};
