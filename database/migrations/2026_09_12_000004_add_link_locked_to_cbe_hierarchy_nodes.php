<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Sep 2026 — per Chris: "you have found a temple A created and
// there is a possibility to link to parent id you have to a flag control
// linked to parent (show the parent name) because sometime the Temple A
// management decision does not want to link and stay by itself stand
// alone." The automatic postcode-based linking (CbeHierarchyLinkService,
// added earlier today) is a good default, but a community can genuinely
// decide to stay independent even though it would otherwise match a
// parent. `link_locked` = true means "leave this entity's parent alone —
// never auto-link or auto-relink it", set by an explicit toggle on the
// Profile tab, never automatically.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_hierarchy_nodes', 'link_locked')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->boolean('link_locked')->default(false)->after('parent_node_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'link_locked')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->dropColumn('link_locked');
            });
        }
    }
};
