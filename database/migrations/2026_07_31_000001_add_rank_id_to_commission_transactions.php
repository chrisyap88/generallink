<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Configurable Rank System, Phase 2 (wiring rank into
// actual commission payout). Adds an audit trail column: which specific
// rank (if any) this commission_transactions row was paid for/against.
// Nullable and additive only — every existing row simply gets NULL here,
// meaning "calculated under the old flat role-based model, no rank
// breakdown was configured for that structure at the time." Nothing
// existing is touched or reinterpreted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->uuid('rank_id')->nullable()->after('role_at_transaction');
            $table->foreign('rank_id')->references('rank_id')->on('role_ranks')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropForeign(['rank_id']);
            $table->dropColumn('rank_id');
        });
    }
};
