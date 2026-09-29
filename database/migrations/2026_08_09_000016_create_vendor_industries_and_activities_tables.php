<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris's spec: "store Industry and Nature of
// Business as multiple values, not as a single text string." One row per
// selected value (same pattern already used for vendor_documents), so
// this data is genuinely queryable for future vendor search/matching/
// due diligence — not a comma-joined string pretending to be structured.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_industries', function (Blueprint $table) {
            $table->uuid('vendor_industry_id')->primary();
            $table->uuid('vendor_id');
            $table->string('industry_key', 60);
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->index(['vendor_id']);
            $table->index(['industry_key']);
        });

        Schema::create('vendor_business_activities', function (Blueprint $table) {
            $table->uuid('vendor_activity_id')->primary();
            $table->uuid('vendor_id');
            $table->string('activity_key', 60);
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->index(['vendor_id']);
            $table->index(['activity_key']);
        });

        // "Other" free-text specify boxes — one per vendor, not one per
        // selected row (a vendor only ever needs to explain "Other" once).
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('industry_other_text', 200)->nullable()->after('industry');
            $table->string('nature_of_business_other_text', 200)->nullable()->after('nature_of_business');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['industry_other_text', 'nature_of_business_other_text']);
        });
        Schema::dropIfExists('vendor_business_activities');
        Schema::dropIfExists('vendor_industries');
    }
};
