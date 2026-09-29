<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Aug 2026 — per Chris: fields needed to import the real 591-temple
// master list (马来西亚道教总会_州分类整理_已处理-GLADE.xlsx) into
// cbe_hierarchy_nodes. node_code is the human-readable identifier agreed
// with Chris — {STATE-3}-{CITY-3}-{TEMPLE-4} for a Temple-level node
// (e.g. SGR-KLG-0001), shorter for State/HQ-level nodes. Unique so it can
// double as a lookup key, not just a display label.
//
// address/telephone/contact fields come straight from the source file's
// Address, Telephone 1/2, and Contact Person 1/2 columns — kept as
// reference info on the node itself (useful later for "temples near me"
// during registration, and as starting contact info before any real
// committee positions are assigned in the app).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'node_code')) {
                $table->string('node_code', 30)->nullable()->unique()->after('node_id');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'address')) {
                $table->string('address', 500)->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_1')) {
                $table->string('telephone_1', 30)->nullable()->after('address');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'telephone_2')) {
                $table->string('telephone_2', 30)->nullable()->after('telephone_1');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'contact_person_1')) {
                $table->string('contact_person_1', 100)->nullable()->after('telephone_2');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'contact_person_2')) {
                $table->string('contact_person_2', 100)->nullable()->after('contact_person_1');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'external_reference_no')) {
                // "Serial Number.1" from the source file — an official
                // registration number where one exists (format like
                // LM0575-24), inconsistently populated so kept as a
                // reference field only, never used to prevent duplicates.
                $table->string('external_reference_no', 30)->nullable()->after('contact_person_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            foreach (['node_code', 'address', 'telephone_1', 'telephone_2', 'contact_person_1', 'contact_person_2', 'external_reference_no'] as $col) {
                if (Schema::hasColumn('cbe_hierarchy_nodes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
