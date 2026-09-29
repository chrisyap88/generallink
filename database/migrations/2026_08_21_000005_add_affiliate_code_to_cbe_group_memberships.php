<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Aug 2026 — per Chris: the member/affiliate code, format
// {STATE-3}-{CITY-3}-{TEMPLE-4}-{MEMBER-6}, e.g. SGR-KLG-0001-000003.
// Lives on cbe_group_memberships, NOT on agents, because one person can
// belong to more than one CBE community (Chris Yap's own example —
// Tao Klang Association, Klang Charity, My Broadband Solution) and each
// membership has its own position in its own community's tree, so each
// needs its own independent code. agents.agent_code (GL-00001 style)
// is untouched — this is a separate, CBE-specific identifier.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_group_memberships', 'affiliate_code')) {
            Schema::table('cbe_group_memberships', function (Blueprint $table) {
                $table->string('affiliate_code', 30)->nullable()->unique()->after('cbe_node_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_group_memberships', 'affiliate_code')) {
            Schema::table('cbe_group_memberships', function (Blueprint $table) {
                $table->dropColumn('affiliate_code');
            });
        }
    }
};
