<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 17 Aug 2026 — per Chris: introducing a genuinely new third group
// type, CBE (Community & Business Enterprise Group), alongside the
// existing two (Direct Selling Group / Organization Rewards Group).
// Unlike DSG vs ORG (which are really the SAME mechanism, just flagged
// differently via promotion_demotion_enabled), CBE needs its own
// configurable-depth hierarchy — e.g. a Tao Federation community might
// define HQ -> State -> Branch -> Sub-section -> Temple (5 levels),
// while another CBE community might only need 3. This is NOT the
// fixed 3-role GROUP_LEADER/TEAM_LEADER/INTRODUCER system used by
// DSG/ORG — it's a separate, admin-typed, ordered list of level names
// stored per group_label, only used when group_type = 'CBE'.
//
// group_type is a new, purely descriptive column — existing behavior
// driven by promotion_demotion_enabled is left completely untouched
// for every existing group. Backfilled from that same flag so nothing
// existing changes: promotion_demotion_enabled=1 -> DSG,
// promotion_demotion_enabled=0 -> ORG. New CBE groups are created with
// promotion_demotion_enabled=0 as well (CBE has no promotion/demotion
// rank system either), which also means CBE communities automatically
// get the same mutual isolation-from-each-other behavior that
// GroupIsolationScope already gives every promotion_demotion_enabled=0
// group today — no scope changes needed yet for that part.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('group_labels', 'group_type')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->enum('group_type', ['DSG', 'ORG', 'CBE'])->default('DSG')->after('group_name');
            });
        }

        // Backfill existing rows from the flag already in use today —
        // no existing group's behavior or classification changes.
        DB::table('group_labels')->where('promotion_demotion_enabled', true)->update(['group_type' => 'DSG']);
        DB::table('group_labels')->where('promotion_demotion_enabled', false)->update(['group_type' => 'ORG']);

        // -----------------------------------------------------
        // cbe_hierarchy_levels — one row per level, per CBE group_label,
        // in display order. Only ever populated for group_type='CBE'
        // rows. Admin types the level names in order (e.g. "HQ",
        // "State", "Branch", "Sub-section", "Temple") on the Group Name
        // screen; each becomes one row here. Any number of levels is
        // allowed — nothing here is hardcoded to 3 or to Tao.
        // -----------------------------------------------------
        if (! Schema::hasTable('cbe_hierarchy_levels')) {
            Schema::create('cbe_hierarchy_levels', function (Blueprint $table) {
                $table->uuid('level_id')->primary();
                $table->uuid('group_label_id');
                $table->unsignedInteger('level_order');
                $table->string('level_name', 100);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->unique(['group_label_id', 'level_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_hierarchy_levels');

        if (Schema::hasColumn('group_labels', 'group_type')) {
            Schema::table('group_labels', function (Blueprint $table) {
                $table->dropColumn('group_type');
            });
        }
    }
};
