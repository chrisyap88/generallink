<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — undoes the sst_registration_number column added to
// insurance_renewal_schedules in migration 2026_07_18_000002. That was
// the wrong table: SST Registration No. is a constant per vendor
// company, not something that varies per policy. It now lives on
// vendors instead (see 2026_07_18_000003_add_sst_registration_number_to_vendors_table).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('insurance_renewal_schedules', 'sst_registration_number')) {
                $table->dropColumn('sst_registration_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->string('sst_registration_number', 50)->nullable();
        });
    }
};
