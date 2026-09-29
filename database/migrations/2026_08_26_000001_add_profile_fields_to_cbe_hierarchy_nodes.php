<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026 — per Chris: "from temple drill down you should
// display view details which the profile meaning contact person,
// address, contact number... under this temple profile you have 2 tap
// folder on top once is profile, one is kpi." Any CBE node (most
// usefully a Temple, but the fields work at any level) can now carry
// basic contact/profile info alongside its KPI numbers. All nullable —
// existing 591 imported temples have none of this yet; it gets filled
// in over time from the new Profile tab on the Admin CBE KPI screen.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'contact_person')) {
                $table->string('contact_person', 150)->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'contact_phone')) {
                $table->string('contact_phone', 30)->nullable()->after('contact_person');
            }
            if (! Schema::hasColumn('cbe_hierarchy_nodes', 'address')) {
                $table->text('address')->nullable()->after('contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_hierarchy_nodes', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'contact_phone', 'address']);
        });
    }
};
