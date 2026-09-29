<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 20 Jul 2026 — per Chris: "unclaimed commission" was a discussed
// reminder concept that was never actually built (only a routing
// label existed in ApprovalService, never triggered anywhere). This
// column guards the new commissions:check-unclaimed scheduled command
// — same cooldown pattern as reminder_sent_at elsewhere, but since an
// unclaimed balance is an ongoing condition (not a one-time event),
// this is used as a re-arm cooldown: the command reminds again once
// this timestamp is older than the configured threshold, rather than
// only ever once.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('earning_wallets', function (Blueprint $table) {
            $table->timestamp('unclaimed_reminder_sent_at')->nullable()->after('last_updated');
        });
    }

    public function down(): void
    {
        Schema::table('earning_wallets', function (Blueprint $table) {
            $table->dropColumn('unclaimed_reminder_sent_at');
        });
    }
};
