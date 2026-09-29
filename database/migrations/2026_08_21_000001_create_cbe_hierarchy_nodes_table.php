<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Aug 2026 — per Chris: cbe_hierarchy_levels (17 Aug 2026) only
// stores the ABSTRACT list of level names per CBE community (e.g. "HQ",
// "State", "Branch", "Temple", in order) — it never stored the REAL
// instances. That was fine for defining the shape of a community's
// structure, but not for real data: with a real master list of 589
// actual temples across Malaysia now in hand (Selangor 281, of which
// Klang 137, etc.), we need real rows — "Selangor" the actual State,
// "Klang" the actual Branch, "Klang Temple A" the actual Temple — each
// one linked to its real parent, so reports roll up dynamically instead
// of being duplicated per member. This table is that real tree.
//
// Every node points at exactly one row in cbe_hierarchy_levels to say
// which "rung" it represents (Chris's HQ/State/Branch/Temple example),
// and at its own parent_node_id to say who it reports to — nullable
// only for the single HQ root of each community. Walking parent_node_id
// repeatedly gets you the full chain to HQ; hierarchy_path is a cached
// "/hq-id/state-id/branch-id/" string (same pattern already used for
// DSG/ORG's agents.hierarchy_path) kept in sync by the application so
// "show me everyone under this Branch" doesn't need a recursive query.
//
// node_name_zh added now (not deferred to a later migration) since
// Phase 2 (bilingual CBE names) was already agreed and adding the
// column later would just mean a second ALTER for no reason.
//
// city/postcode are plain descriptive fields, NOT separate hierarchy
// levels — per Chris's Klang discussion, Meru/Kapar/Pulau Ketam/Klang
// City/Port Klang are groupings for browsing and reporting only, not
// separate governance nodes with their own login/leadership. The
// Branch → City breakdown on the drill-down screen is just
// `GROUP BY city` over this column, scoped to one Branch.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_hierarchy_nodes')) {
            Schema::create('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->uuid('node_id')->primary();
                $table->uuid('group_label_id');
                $table->uuid('level_id');
                $table->uuid('parent_node_id')->nullable();
                $table->string('node_name', 200);
                $table->string('node_name_zh', 200)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('postcode', 10)->nullable();
                $table->text('hierarchy_path')->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('level_id')->references('level_id')->on('cbe_hierarchy_levels')->onDelete('restrict');
                $table->foreign('parent_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('restrict');
                $table->index(['group_label_id', 'parent_node_id']);
                $table->index('city');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_hierarchy_nodes');
    }
};
