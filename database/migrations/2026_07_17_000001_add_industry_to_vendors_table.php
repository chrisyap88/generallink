<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 17 Jul 2026 — GeneralLink is an Affiliate Ecosystem (GL/TL/Introducer),
// NOT insurance-specific — insurance is only the first vendor industry
// onboarded. This column lets the system know, generically, what kind
// of business a vendor is in, so downstream logic (e.g. "should this
// sale auto-create an insurance renewal record?", "should the sales
// form show coverage dates?") never has to hard-code "if insurance"
// against a specific vendor_id — it reads this column instead. New
// vendor industries (workshop, F&B, retail, etc.) just get a different
// value here — no code changes needed to onboard them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('industry', 50)->nullable()->after('vendor_name');
        });

        // Backfill: every vendor that exists today is insurance (the only
        // industry onboarded so far). Future vendors set this explicitly
        // when Admin creates them.
        DB::table('vendors')->whereNull('industry')->update(['industry' => 'INSURANCE']);
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('industry');
        });
    }
};
