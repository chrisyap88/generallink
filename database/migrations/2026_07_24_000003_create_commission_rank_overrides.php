<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 24 Jul 2026 — Configurable Rank System, Phase 3 ("rank override /
// extra cut"). Lets a single rank (e.g. Regional Director, whose own home
// role is Mgt) ALSO draw an extra carved-out slice from ANOTHER role's pool
// (e.g. Ope, Aff) on top of its own role's rank_pct in commission_rank_
// allocations. This is never additive money out of nowhere — it is carved
// OUT of that other role's own pool, so that role's OWN ranks simply get a
// smaller remaining share. The absolute rule (checked in
// MasterFileController::validateRankAllocations()) is that every role's
// pool — its own ranks' rank_pct PLUS any overrides drawn into it from
// other roles' ranks — must always sum to EXACTLY that role's own % on the
// structure. Total payout can therefore never exceed the structure's Total
// Earning Income %, no matter how overrides are configured.
return new class extends Migration
{
    public function up(): void
    {
        // Self-healing: the first run of this migration failed partway
        // (identifier-too-long error) after the table was already created
        // but before the unique index was added. Drop that leftover table
        // first so re-running this migration starts clean either way.
        Schema::dropIfExists('commission_rank_overrides');

        Schema::create('commission_rank_overrides', function (Blueprint $table) {
            $table->uuid('override_id')->primary();
            $table->uuid('structure_id');
            $table->uuid('rank_id'); // the rank RECEIVING the override (e.g. Regional Director)
            $table->enum('target_role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']); // the OTHER role's pool being drawn from
            $table->decimal('override_pct', 10, 4);
            $table->timestamps();

            $table->foreign('structure_id')->references('structure_id')->on('commission_structures')->cascadeOnDelete();
            $table->foreign('rank_id')->references('rank_id')->on('role_ranks')->cascadeOnDelete();
            // Explicit short name — MySQL's auto-generated name for this
            // 3-column unique index exceeds its 64-character identifier limit.
            $table->unique(['structure_id', 'rank_id', 'target_role'], 'cro_structure_rank_role_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_rank_overrides');
    }
};
