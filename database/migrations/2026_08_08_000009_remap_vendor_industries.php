<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 8 Aug 2026 — Vendor Registration rebuild, per Chris's explicit
// industry category list. VendorController::INDUSTRIES was replaced
// with 19 standard economic-sector categories (banking and finance,
// construction, engineering, government, etc.) instead of the old
// consumer-retail-focused list. This is a data-only migration: the
// `industry` column itself is a plain varchar (no enum constraint), so
// existing vendor rows just get their old key remapped to the closest
// new one. Nothing is guessed beyond "closest reasonable category" —
// Admin can always re-open a vendor and correct it if the mapping isn't
// quite right for that specific business.
return new class extends Migration
{
    public function up(): void
    {
        $map = [
            'PROFESSIONAL_SVCS' => 'PROFESSIONAL_SERVICES',
            'LEISURE_TRAVEL'    => 'HOSPITALITY_TOURISM',
            'RETAIL_GROCERY'    => 'RETAIL_WHOLESALE',
            'RETAIL_DEPARTMENT' => 'RETAIL_WHOLESALE',
            'RETAIL_FASHION'    => 'RETAIL_WHOLESALE',
            'HEALTH_BEAUTY'     => 'HEALTHCARE',
            'HOME_LIVING'       => 'RETAIL_WHOLESALE',
            'ELECTRONICS'       => 'RETAIL_WHOLESALE',
            'TELCO_UTILITIES'   => 'TELECOMMUNICATIONS',
            // INSURANCE, FOOD_BEVERAGE, AUTOMOTIVE, EDUCATION, OTHER keep the same key — already in the new list.
        ];

        foreach ($map as $old => $new) {
            DB::table('vendors')->where('industry', $old)->update(['industry' => $new]);
        }
    }

    public function down(): void
    {
        // Deliberately not reversed — the reverse mapping is lossy
        // (e.g. RETAIL_WHOLESALE could have come from 4 different old
        // categories) and old vendor rows kept their industry value
        // either way, just possibly re-categorized by an Admin since.
    }
};
