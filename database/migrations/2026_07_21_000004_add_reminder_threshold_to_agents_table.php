<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — per Chris: each agent can set their own personal
// low-balance reminder for their Document Credit balance ("remind me
// if my balance drops below RM X"). Deliberately a whole-number RM
// value >= 1 (enforced in the controller, not the DB — validation
// message needs to be friendly), nullable = agent hasn't set one, no
// reminder fires. reminder_sent_at follows the same
// set-once-then-reset pattern as earning_wallets.unclaimed_reminder_sent_at
// (see 2026_07_20_000002): set when the reminder fires so it never
// double-sends while still below threshold, cleared back to null once
// the balance rises back above the threshold so it can fire again the
// next time it dips.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->unsignedInteger('document_credit_reminder_threshold')->nullable()->after('document_credit_balance');
            $table->timestamp('document_credit_reminder_sent_at')->nullable()->after('document_credit_reminder_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['document_credit_reminder_threshold', 'document_credit_reminder_sent_at']);
        });
    }
};
