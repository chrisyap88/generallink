<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 19 Jul 2026 — the Vendor Edit screen (VendorController@update) has
// always tried to save vendor_office_phone, vendor_website, pic_phone
// (and, defensively, vendor_postcode/vendor_city/vendor_state in case a
// fresh install is missing them too) — but no migration ever actually
// added these columns to the vendors table. Saving any change on that
// screen silently fails with a SQL "Unknown column" error, which is why
// Chris couldn't save anything there. Every column is added only if it
// doesn't already exist, so this is safe to run whether or not some of
// them were already added by hand outside of migrations.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'vendor_office_phone')) {
                $table->string('vendor_office_phone', 30)->nullable()->after('vendor_phone');
            }
            if (!Schema::hasColumn('vendors', 'vendor_website')) {
                $table->string('vendor_website', 200)->nullable()->after('vendor_email');
            }
            if (!Schema::hasColumn('vendors', 'vendor_postcode')) {
                $table->string('vendor_postcode', 10)->nullable()->after('vendor_address');
            }
            if (!Schema::hasColumn('vendors', 'vendor_city')) {
                $table->string('vendor_city', 100)->nullable()->after('vendor_postcode');
            }
            if (!Schema::hasColumn('vendors', 'vendor_state')) {
                $table->string('vendor_state', 100)->nullable()->after('vendor_city');
            }
            if (!Schema::hasColumn('vendors', 'pic_phone')) {
                $table->string('pic_phone', 30)->nullable()->after('pic_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            foreach (['vendor_office_phone', 'vendor_website', 'vendor_postcode', 'vendor_city', 'vendor_state', 'pic_phone'] as $col) {
                if (Schema::hasColumn('vendors', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
