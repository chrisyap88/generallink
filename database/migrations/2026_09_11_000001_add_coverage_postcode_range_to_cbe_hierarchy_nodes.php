<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 11 Sep 2026 — per Chris: "branch can configure which city it
// belong to range of post code" — a Branch-level (or any level's) node
// can now optionally declare the postcode range it covers (e.g. Klang
// Branch = 41000-42999). This is separate from the existing `postcode`
// column (that one is the entity's OWN postal address); this pair is a
// coverage range used to help decide which parent a new child entity
// (e.g. a Temple) should be linked under, based on the child's own
// postcode. Both columns are nullable/optional — most nodes (and most
// levels) will never set them; only used where a community actually
// organizes by postcode coverage, same admin-defined flexibility as
// everything else in the CBE hierarchy design.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_hierarchy_nodes', 'coverage_postcode_start')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->string('coverage_postcode_start', 5)->nullable()->after('postcode');
                $table->string('coverage_postcode_end', 5)->nullable()->after('coverage_postcode_start');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_hierarchy_nodes', 'coverage_postcode_start')) {
            Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
                $table->dropColumn(['coverage_postcode_start', 'coverage_postcode_end']);
            });
        }
    }
};
