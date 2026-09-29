<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FIXED 12 Aug 2026 — per Chris's registration error: "Data too long for
// column 'industry'." vendors.industry was created back when a vendor
// could only pick ONE industry (varchar 50). Since industries became a
// multi-select (Task #55/#63 — one row per selection, stored properly in
// vendor_industries), this column is now just a comma-joined DISPLAY
// summary of every industry picked (VendorAuthController::register()'s
// $industrySummary) — a vendor selecting 3-4 industries with longer
// names easily blows past 50 characters and crashes the whole
// registration. Widened to text so the summary can never be too long
// again, regardless of how many industries someone selects.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('industry')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('industry', 50)->nullable()->change();
        });
    }
};
