<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Sep 2026 — per Chris: after keying a Postcode Coverage From/To
// range on Entity Maintenance, show every postcode inside that range
// (Entity Name, Postcode, City, checkbox ticked by default) so he can
// untick the postcodes this entity does NOT cover. Only the TICKED
// postcodes are stored here. The From/To columns on cbe_hierarchy_nodes
// are kept (they still show the overall range); when an entity has rows
// in this table, CbeHierarchyLinkService matches on this exact list
// instead of the plain From/To range.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_node_coverage_postcodes')) {
            Schema::create('cbe_node_coverage_postcodes', function (Blueprint $table) {
                $table->uuid('coverage_id')->primary();
                $table->uuid('node_id')->index();
                $table->string('postcode', 5);
                $table->string('city', 100)->nullable();
                $table->timestamps();
                $table->unique(['node_id', 'postcode', 'city'], 'cbe_ncp_node_pc_city_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_node_coverage_postcodes');
    }
};
