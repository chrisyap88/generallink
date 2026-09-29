<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 20 Jul 2026 — per Chris: "Save & Send" only ever notified once,
// immediately, at creation time — nothing pinged the agent again when
// the reminder's actual date arrived. This column is the same guard
// pattern already used for the automatic renewal reminders
// (insurance_renewal_schedules.reminder_sent_at) — the new
// reminders:send-followups scheduled command (see
// app/Console/Commands/SendFollowUpReminders.php) sets this the one
// time it sends the on-date notification, so re-running the command
// (it runs daily) never sends the same reminder twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->timestamp('notification_sent_at')->nullable()->after('escalated');
        });
    }

    public function down(): void
    {
        Schema::table('personal_reminders', function (Blueprint $table) {
            $table->dropColumn('notification_sent_at');
        });
    }
};
