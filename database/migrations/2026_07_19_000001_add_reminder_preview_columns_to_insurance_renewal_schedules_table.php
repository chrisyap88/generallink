<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Jul 2026 — Chris asked for the Renewal Reminder preview shown
// on the Submit Sales Transaction screen to be stored immediately when
// the agent clicks Submit, not just computed later by the daily
// renewals:send-reminders job. This is the exact date + message that
// was shown to the agent at submission time, kept as a permanent,
// queryable record — separate from reminder_sent_at, which still marks
// when the reminder actually went out.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->date('reminder_scheduled_date')->nullable()->after('coverage_end');
            $table->text('reminder_message')->nullable()->after('reminder_scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->dropColumn(['reminder_scheduled_date', 'reminder_message']);
        });
    }
};
