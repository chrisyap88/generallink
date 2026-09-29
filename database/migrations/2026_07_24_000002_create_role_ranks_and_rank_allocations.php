<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 24 Jul 2026 — Configurable Rank System, Phase 1 (schema only).
// Per Chris: on top of the 3 fixed system roles (GROUP_LEADER/TEAM_LEADER/
// INTRODUCER, labelled Mgt/Ope/Aff in this deployment), Admin can define
// any number of Ranks per role (e.g. Regional Director + Master Agency
// under Mgt; Agency Manager/Branch Manager/Unit Manager/Team Leader under
// Ope; Senior Agent/Agent under Aff). Each Earning Income Structure's
// role-level % (group_leader_pct/team_leader_pct/introducer_pct) is then
// broken down further across that role's ranks — the ranks under a role
// must sum to EXACTLY that role's %, never more or less (enforced at
// application layer, same convention as the existing role split rule).
//
// Payout logic (confirmed by Chris — "Option 2"): every distinct rank
// found while walking up an agent's upline chain gets paid its own rank's
// %, not just the single nearest person per role. That change to
// CommissionEngine is Phase 2 — this migration only adds the schema.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_ranks', function (Blueprint $table) {
            $table->uuid('rank_id')->primary();

            // Which of the 3 fixed system roles this rank belongs to.
            $table->enum('role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER']);

            $table->string('rank_name', 100);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['role', 'is_active']);

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        Schema::create('commission_rank_allocations', function (Blueprint $table) {
            $table->uuid('allocation_id')->primary();

            $table->uuid('structure_id');
            $table->uuid('rank_id');

            // This rank's slice of its role's % on this specific Earning
            // Income Structure (e.g. Regional Director = 1.5 out of Mgt's 2.5).
            $table->decimal('rank_pct', 10, 4);

            $table->timestamps();

            $table->unique(['structure_id', 'rank_id']);

            $table->foreign('structure_id')
                  ->references('structure_id')
                  ->on('commission_structures')
                  ->cascadeOnDelete();

            $table->foreign('rank_id')
                  ->references('rank_id')
                  ->on('role_ranks')
                  ->cascadeOnDelete();
        });

        // Which specific rank an agent holds within their system role.
        // Nullable — ADMIN has no rank, and existing agents start
        // unassigned until Admin tags them (Phase 2 screen).
        Schema::table('agents', function (Blueprint $table) {
            $table->uuid('rank_id')->nullable()->after('role');
            $table->foreign('rank_id')
                  ->references('rank_id')
                  ->on('role_ranks')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['rank_id']);
            $table->dropColumn('rank_id');
        });
        Schema::dropIfExists('commission_rank_allocations');
        Schema::dropIfExists('role_ranks');
    }
};
