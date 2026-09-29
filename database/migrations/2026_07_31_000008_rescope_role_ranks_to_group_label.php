<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Chris caught a real design flaw: role_ranks was
// scoped to groups.group_id (each individual Group Leader's OWN
// personal team), which meant Amy Tan's team and Chris Yap's team
// could silently drift onto two different rank ladders/commission
// schemes even though both should run under ONE shared policy for
// their Special Privilege Group (prihatin2u/rela2u/PVATM). Rank
// scoping now moves to group_labels.group_label_id instead — the SAME
// identity promotion_demotion_rules and breakaway_bonus_rules already
// correctly scope by. NULL group_label_id = "System Default", shared
// by every agent not tagged with a Special Privilege Group.
//
// The old group_id column/FK/index is dropped outright rather than
// kept alongside — every existing group_id-scoped rank was either test
// data (Chris Yap's own group, added while exploring this screen) or
// needs to be re-entered under the correct group_label anyway, so
// there is nothing safe to auto-migrate. See RESCOPE_RANKS_MIGRATION.bat
// for the exact steps Chris runs, including what to re-check afterward.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            if (Schema::hasColumn('role_ranks', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropIndex('role_ranks_role_group_active_idx');
                $table->dropColumn('group_id');
            }
        });

        Schema::table('role_ranks', function (Blueprint $table) {
            $table->uuid('group_label_id')->nullable()->after('role');
            $table->foreign('group_label_id')
                  ->references('group_label_id')
                  ->on('group_labels')
                  ->cascadeOnDelete();
            $table->index(['role', 'group_label_id', 'is_active'], 'role_ranks_role_grouplabel_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            $table->dropForeign(['group_label_id']);
            $table->dropIndex('role_ranks_role_grouplabel_active_idx');
            $table->dropColumn('group_label_id');
        });

        Schema::table('role_ranks', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('role');
            $table->foreign('group_id')->references('group_id')->on('groups')->cascadeOnDelete();
            $table->index(['role', 'group_id', 'is_active'], 'role_ranks_role_group_active_idx');
        });
    }
};
