<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per Chris: "setting up group name does not have the
// full contacts details, you should add full contact details... address,
// contact 1 2, add contact etc." This is the GROUP's own head-office /
// main contact — separate from each individual Temple/Branch/State/HQ
// entity's own Address + Contact 1/2 + phones (already on cbe_hierarchy_
// nodes / cbe_hierarchy_node_phones via Entity Maintenance's Profile
// tab). A community's overall head-office contact and one entity's own
// site contact are two different things and are kept as two different
// records on purpose — Chris confirmed this scope explicitly.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            if (! Schema::hasColumn('group_labels', 'address')) {
                $table->string('address', 255)->nullable()->after('description');
            }
            if (! Schema::hasColumn('group_labels', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
            if (! Schema::hasColumn('group_labels', 'postcode')) {
                $table->string('postcode', 20)->nullable()->after('city');
            }
            if (! Schema::hasColumn('group_labels', 'contact_person_1')) {
                $table->string('contact_person_1', 150)->nullable()->after('postcode');
            }
            if (! Schema::hasColumn('group_labels', 'contact_person_2')) {
                $table->string('contact_person_2', 150)->nullable()->after('contact_person_1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_labels', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'postcode', 'contact_person_1', 'contact_person_2']);
        });
    }
};
