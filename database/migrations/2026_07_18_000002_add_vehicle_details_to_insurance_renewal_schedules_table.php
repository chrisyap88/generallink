<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — full vehicle schedule detail, per Chris's request
// after sharing a real motor policy schedule page. All nullable — only
// motor policies have any of this; every other insurance product (and
// every non-insurance industry) leaves these blank. Stored as plain
// strings, not numbers, since these are printed/typed values copied
// as-is off the document (e.g. "1,468.00 CC"), not used in arithmetic.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->string('vehicle_make_model', 150)->nullable()->after('vehicle_number');
            $table->string('cubic_capacity', 30)->nullable()->after('vehicle_make_model');
            $table->string('year_of_manufacture', 4)->nullable()->after('cubic_capacity');
            $table->string('seating_capacity', 10)->nullable()->after('year_of_manufacture');
            $table->string('engine_number', 50)->nullable()->after('seating_capacity');
            $table->string('chassis_number', 50)->nullable()->after('engine_number');
            $table->string('trailer_chassis_number', 50)->nullable()->after('chassis_number');
            $table->string('sst_registration_number', 50)->nullable()->after('trailer_chassis_number');
            $table->string('named_drivers', 255)->nullable()->after('sst_registration_number');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->dropColumn([
                'vehicle_make_model', 'cubic_capacity', 'year_of_manufacture',
                'seating_capacity', 'engine_number', 'chassis_number',
                'trailer_chassis_number', 'sst_registration_number', 'named_drivers',
            ]);
        });
    }
};
