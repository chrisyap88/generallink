<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Sep 2026 — per Chris: an entity (e.g. a temple of the HQ CBE
// 马来西亚道教总会) can be affiliated to ONE branch of an affiliated CBE
// (e.g. Persekutuan Pertubuhan Agama Tao Malaysia Cawangan Klang) through
// CBE Entity Affiliation. This is a separate link from the entity's own
// upline (parent_node_id), so the HQ's own HQ -> State -> Temple structure
// is never broken. One branch only per entity.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->uuid('affiliated_node_id')->nullable()->index()->after('parent_node_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->dropColumn('affiliated_node_id');
            });
        }
    }
};
