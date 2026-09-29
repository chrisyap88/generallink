<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 24 Jul 2026 — Per Chris: rank NAMES differ between the Public
// group (insurance-hierarchy style names) and Special Privilege Groups
// like PVATM/NG/GLC/Enterprise (their own naming), but the underlying
// % math is unchanged — a rank still just draws a slice from its role's
// pool exactly as before. This adds group_id to role_ranks so each
// group can define its own named ranks. NULL group_id = "System
// Default" rank, visible/usable by any group that hasn't defined its
// own ranks for that role.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            if (!Schema::hasColumn('role_ranks', 'group_id')) {
                $table->uuid('group_id')->nullable()->after('role');
                $table->foreign('group_id')
                      ->references('group_id')
                      ->on('groups')
                      ->cascadeOnDelete();
                $table->index(['role', 'group_id', 'is_active'], 'role_ranks_role_group_active_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            if (Schema::hasColumn('role_ranks', 'group_id')) {
                $table->dropForeign(['group_id']);
                $table->dropIndex('role_ranks_role_group_active_idx');
                $table->dropColumn('group_id');
            }
        });
    }
};
