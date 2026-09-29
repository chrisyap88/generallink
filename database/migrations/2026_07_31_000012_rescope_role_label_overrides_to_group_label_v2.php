<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — completes the rescope that migration 000009
// deliberately deferred. Per Chris, viewing PVATM/prihatin2u/rela2u on
// the Organization Rank Hierarchy Structure and Rank Allocation screens
// showed the generic GL/TL/INT short labels instead of each Special
// Privilege Group's own configured ones. Root cause: role_label_overrides
// was scoped to groups.group_id (one individual GL's own personal team),
// the exact same design flaw already fixed for role_ranks in 000008 —
// two GLs under the same group_label could see two different label sets,
// and there was no way to set "PVATM's" labels as a single shared thing
// at all. Rescoped to group_labels.group_label_id instead, same as ranks.
//
// The old group_id column/FK/index is dropped outright, same reasoning
// as 000008: whatever was set under group_id was scoped to the wrong
// concept anyway (an individual GL team, not the shared Special
// Privilege Group), so there's nothing safe to auto-migrate. Chris will
// need to re-type each group's short labels once via Organization
// Category Maintenance after this migration runs.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_label_overrides', function (Blueprint $table) {
            if (Schema::hasColumn('role_label_overrides', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropIndex('rlo_role_group_idx');
                $table->dropColumn('group_id');
            }
        });

        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->uuid('group_label_id')->nullable()->after('override_id');
            $table->foreign('group_label_id')
                  ->references('group_label_id')
                  ->on('group_labels')
                  ->cascadeOnDelete();
            $table->index(['role', 'group_label_id'], 'rlo_role_grouplabel_idx');
        });
    }

    public function down(): void
    {
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->dropForeign(['group_label_id']);
            $table->dropIndex('rlo_role_grouplabel_idx');
            $table->dropColumn('group_label_id');
        });

        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->uuid('group_id')->nullable()->after('override_id');
            $table->foreign('group_id')->references('group_id')->on('groups')->cascadeOnDelete();
            $table->index(['role', 'group_id'], 'rlo_role_group_idx');
        });
    }
};
