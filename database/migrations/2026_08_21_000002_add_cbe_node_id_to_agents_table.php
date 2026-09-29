<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Aug 2026 — per Chris: links an individual agent to their exact
// home node in the real CBE tree (cbe_hierarchy_nodes) — e.g. "this
// person belongs to Klang Temple A", not just "this person is somewhere
// in Klang Branch". Nullable and deliberately NOT required — per Chris,
// a person can join a CBE community (e.g. a charity or business CBE)
// without ever picking a temple, either because they're not sure yet
// (handled by the nearest-temple-suggestion + Pending Assignment flow,
// a later phase) or because they deliberately have no temple affiliation
// (different religion, etc.) — both are valid, permanent states, not
// errors. Only meaningful when the agent's community uses a real tree;
// DSG/ORG agents never touch this column.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'cbe_node_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->uuid('cbe_node_id')->nullable()->after('group_label_id');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('agents', 'cbe_node_id')) {
            Schema::table('agents', function (Blueprint $table) {
                $table->dropForeign(['cbe_node_id']);
                $table->dropColumn('cbe_node_id');
            });
        }
    }
};
