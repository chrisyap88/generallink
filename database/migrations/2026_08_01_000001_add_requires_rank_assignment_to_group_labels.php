<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 1 Aug 2026 — per Chris: "you should fall back to hierarchy
// category if no rank assignment, you can refer to the group label
// flag." Admin can now switch a Special Privilege Group into STRICT
// mode (breakage until every agent is ranked — the original behavior)
// or leave it OFF (default) so an unranked agent's share of a rank-
// configured structure falls back to being paid to the nearest active
// agent of that rank's own role, instead of going to breakage.
// Defaults to false for every existing group — nobody's payout
// behavior changes until Chris deliberately turns this on somewhere.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            $table->boolean('requires_rank_assignment')->default(false)->after('promotion_demotion_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            $table->dropColumn('requires_rank_assignment');
        });
    }
};
