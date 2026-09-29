<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center follow-up. Per Chris:
// "i can create the blasting in advance example english tomorrow,
// Chinese every wednesday ... system auto send for me." Adds recurrence
// so a campaign isn't only ever a single one-time scheduled_at — it can
// repeat daily or weekly, indefinitely, until Admin disables/deletes it.
// recurrence_type=NONE keeps the exact one-time behaviour that already
// existed (scheduled_at fires once, then status becomes SENT forever).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->string('recurrence_type', 20)->default('NONE')->after('scheduled_at');
            $table->unsignedTinyInteger('recurrence_day_of_week')->nullable()->after('recurrence_type');
            $table->timestamp('last_sent_at')->nullable()->after('sent_at');
            $table->unsignedInteger('send_count')->default(0)->after('last_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn(['recurrence_type', 'recurrence_day_of_week', 'last_sent_at', 'send_count']);
        });
    }
};
