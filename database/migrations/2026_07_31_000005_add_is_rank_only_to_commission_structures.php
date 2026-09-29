<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — "Rank-Only Structure" mode. Per Chris: a new company
// that doesn't need the Group Leader/Team Leader/Introducer split at all
// should be able to skip it entirely and pay straight by Rank instead —
// e.g. Rank A = 3%, Rank B = 5%, Rank C = 2%, adding up to the Total %
// directly, with no role bucket in between.
//
// Purely an ADMIN SETUP / VALIDATION flag — CommissionEngine needs no
// changes at all. It already auto-switches to the rank-based payout the
// moment ANY commission_rank_allocations rows exist for a structure
// (see CommissionEngine::resolveDistributions()/buildRankPayoutMap()),
// regardless of this flag. is_rank_only only controls how the Earning
// Income Structure screen validates and collects input: when true,
// group_leader_pct/team_leader_pct/introducer_pct are forced to 0 (not
// asked for), and every active rank's % (across all 3 ladders, any
// role) must sum to exactly the structure's Total % instead of each
// role's own bucket.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_structures', function (Blueprint $table) {
            $table->boolean('is_rank_only')->default(false)->after('total_commission_pct');
        });
    }

    public function down(): void
    {
        Schema::table('commission_structures', function (Blueprint $table) {
            $table->dropColumn('is_rank_only');
        });
    }
};
