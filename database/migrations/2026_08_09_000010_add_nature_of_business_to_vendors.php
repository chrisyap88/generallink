<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — Vendor Registration spec update, per Chris. "What Do
// You Sell?" (vendor_type: Product/Service/Event) stays as-is and is now
// labelled "Business Category" — it's still needed structurally for the
// Phase 2 catalogue. Separately, Chris asked for a genuine free-text
// "Nature of Business" field ("Describe your company's primary
// activities, products or services") — that's a different, KYB-style
// field, so it's added here rather than replacing vendor_type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->text('nature_of_business')->nullable()->after('vendor_type');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn('nature_of_business');
        });
    }
};
