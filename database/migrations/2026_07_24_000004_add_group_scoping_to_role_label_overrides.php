<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 24 Jul 2026 — per Chris: PVATM needs its own role labels (e.g.
// "Regional Director" / "Agency Manager" / "Sales Agent") while every
// other group keeps seeing the system default (Group Leader / Team
// Leader / Introducer). Previously role_label_overrides had exactly one
// row per role (role = primary key) — this adds a nullable group_id so a
// role can now have MULTIPLE rows: one with group_id NULL (the system
// default, unchanged from before) plus one row per group that has its
// own override. RoleLabelService resolves which one to show based on the
// logged-in agent's own group_id, falling back to the default when their
// group has no override set.
return new class extends Migration
{
    public function up(): void
    {
        // `role` can no longer be the primary key on its own — a role now
        // needs to support more than one row (default + per-group).
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->uuid('override_id')->nullable()->after('role');
            $table->uuid('group_id')->nullable()->after('override_id');
        });

        // Backfill a real UUID for the 3 rows that already exist (the
        // system defaults, group_id stays NULL for these).
        DB::table('role_label_overrides')->get()->each(function ($row) {
            DB::table('role_label_overrides')
                ->where('role', $row->role)
                ->update(['override_id' => (string) Str::uuid()]);
        });

        // Adding a PRIMARY KEY constraint implicitly enforces NOT NULL on
        // override_id — every row already has a value from the backfill
        // above, so this succeeds without a separate column-modify step.
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->primary('override_id');
            $table->foreign('group_id')->references('group_id')->on('groups')->cascadeOnDelete();
            $table->index(['role', 'group_id'], 'rlo_role_group_idx');
        });
    }

    public function down(): void
    {
        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropIndex('rlo_role_group_idx');
            $table->dropPrimary();
            $table->dropColumn(['override_id', 'group_id']);
        });

        Schema::table('role_label_overrides', function (Blueprint $table) {
            $table->primary('role');
        });
    }
};
