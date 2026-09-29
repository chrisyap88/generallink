<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per Chris: "remove Join Time and Leave Time
// completely." Simplified attendance to just Participant Name,
// Attendance Status and Attendance Duration (the organizer types the
// duration directly instead of a Join/Leave pair). Guarded so this is
// safe whether or not migration 2026_09_18_000001 (which added these
// two columns minutes earlier) has already run.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['join_time', 'leave_time'] as $col) {
            if (Schema::hasColumn('cbe_meeting_minute_attendees', $col)) {
                Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'join_time')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->dateTime('join_time')->nullable()->after('attended');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minute_attendees', 'leave_time')) {
            Schema::table('cbe_meeting_minute_attendees', function (Blueprint $table) {
                $table->dateTime('leave_time')->nullable()->after('join_time');
            });
        }
    }
};
