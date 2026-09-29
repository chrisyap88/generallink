<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — telephone_1/telephone_2 on cbe_hierarchy_nodes are
// superseded by cbe_hierarchy_node_phones (previous migration) — one row
// per real phone number instead of two wide text columns that couldn't
// cleanly hold a temple with 3+ numbers. Safe to drop outright: nothing
// has been imported into these columns yet.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_1')) {
                $table->dropColumn('telephone_1');
            }
            if (Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_2')) {
                $table->dropColumn('telephone_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_1')) {
                $table->string('telephone_1', 150)->nullable()->after('address');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_2')) {
                $table->string('telephone_2', 50)->nullable()->after('telephone_1');
            }
        });
    }
};
