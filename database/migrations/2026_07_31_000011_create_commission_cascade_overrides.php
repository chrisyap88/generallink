<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Rank system redesign, Phase 2 (schema only — see
// RankAllocationController/RankAllocationController-adjacent work for
// Phase 1). Per Chris's Legal Business Eco System example: out of many
// Group Leaders under one Special Privilege Group, ONE specific GL (or
// even one specific Team Leader under that GL) might want to run their
// OWN downline % breakdown instead of the group label's shared default —
// e.g. GL keeps 2%, hands a named TL an 8% envelope, and that TL decides
// for himself how much he keeps vs. how much cascades to his Introducers
// (by Rank, since a TL can have many Introducers).
//
// Explicitly confirmed by Chris, NOT the same rule as the flat role-split
// or Rank-Only structure validation elsewhere in this file: a cascade
// override's children must NOT EXCEED the envelope the owner received —
// they do not have to add up to exactly that number. Also explicitly
// confirmed: this is Admin-only configuration, set up per specific GL/TL
// on request — never a self-service screen for the agent, and never
// compulsory for every agent under a label (unconfigured agents simply
// keep using the label's shared default, unaffected).
//
// Two tables:
//   commission_cascade_overrides — one row per (structure, owner agent):
//     how much of the envelope that owner keeps for themselves.
//   commission_cascade_allocations — child rows: the rest of that
//     envelope, handed either to one specific NAMED downline agent (e.g.
//     GL naming a specific TL — "Customer A") or to a Rank (e.g. a TL
//     splitting across Introducer Rank 1/2/3/4, since a TL can have many
//     Introducers and naming each one isn't practical). Exactly one of
//     target_agent_id/target_rank_id is set per row (enforced at the app
//     layer, not the DB, since either can legitimately be null).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_cascade_overrides', function (Blueprint $table) {
            $table->uuid('override_id')->primary();

            $table->uuid('structure_id');
            $table->uuid('owner_agent_id'); // the specific GL or TL this override belongs to

            // What the owner keeps for themselves, out of the envelope
            // they received (from the structure's flat % if they're a
            // GL, or from whichever upline GL's cascade allocation named
            // them, if they're a TL).
            $table->decimal('self_pct', 10, 4)->default(0);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['structure_id', 'owner_agent_id']);

            $table->foreign('structure_id')
                  ->references('structure_id')
                  ->on('commission_structures')
                  ->cascadeOnDelete();

            $table->foreign('owner_agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        Schema::create('commission_cascade_allocations', function (Blueprint $table) {
            $table->uuid('allocation_id')->primary();

            $table->uuid('override_id');
            $table->uuid('target_agent_id')->nullable(); // named downline agent (e.g. a specific TL)
            $table->uuid('target_rank_id')->nullable();  // OR a Rank (e.g. Introducer Rank 1)
            $table->decimal('pct', 10, 4);

            $table->timestamps();

            $table->foreign('override_id')
                  ->references('override_id')
                  ->on('commission_cascade_overrides')
                  ->cascadeOnDelete();

            $table->foreign('target_agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();

            $table->foreign('target_rank_id')
                  ->references('rank_id')
                  ->on('role_ranks')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_cascade_allocations');
        Schema::dropIfExists('commission_cascade_overrides');
    }
};
