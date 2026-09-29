<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — SST Registration No. is the vendor company's own tax
// registration number — a constant per vendor, never per policy. It was
// briefly added to insurance_renewal_schedules (per-policy) by mistake;
// moved here instead, filled in once via Vendor Maintenance, not
// extracted from every document.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('sst_registration_number', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('sst_registration_number');
        });
    }
};
