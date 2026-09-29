<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — vehicle_number added per Chris's request: renewal
// reminders for motor policies need the vehicle registration number to
// be useful (agents/customers identify a motor renewal by vehicle, not
// just by policy number). Nullable — most insurance products (fire,
// medical, travel, etc.) have no vehicle at all, so this only gets
// filled in for motor policies. Non-destructive: existing renewal rows
// simply get NULL here until re-saved or backfilled.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->string('vehicle_number', 20)->nullable()->after('policy_id');
        });
    }

    public function down(): void
    {
        Schema::table('insurance_renewal_schedules', function (Blueprint $table) {
            $table->dropColumn('vehicle_number');
        });
    }
};
