<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, per Chris's
// full written specification section 1.1 (Asset Master). Adds the
// remaining master-file fields the Asset Master requires that the
// register did not have yet: a link to the new Asset Category and
// Asset Location masters, Department (reusing the existing Cost
// Centre master's DEPARTMENT type, same convention already used by
// Purchasing), Fund, Supplier, Invoice Number, a separate
// Capitalisation Date (distinct from Purchase/acquired_date),
// Depreciation Method (configurable — Straight Line already existed
// as the only method; this adds Reducing Balance) and a separate
// Depreciation Start Date. The existing free-text `location` and
// `asset_class` columns are left in place for backward compatibility
// with rows created before this upgrade and as a fallback display
// when an asset has no location_id/category_id set — asset_tag is
// re-labelled "Asset Code" in the UI rather than adding a duplicate
// column, since it already serves exactly that purpose.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_fixed_assets', 'category_id')) {
                $table->uuid('category_id')->nullable()->after('asset_class');
                $table->foreign('category_id')->references('category_id')->on('cbe_asset_categories')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'location_id')) {
                $table->uuid('location_id')->nullable()->after('location');
                $table->foreign('location_id')->references('location_id')->on('cbe_asset_locations')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'cost_centre_id')) {
                $table->uuid('cost_centre_id')->nullable()->after('location_id');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'fund_id')) {
                $table->uuid('fund_id')->nullable()->after('cost_centre_id');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'supplier_id')) {
                $table->uuid('supplier_id')->nullable()->after('fund_id');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'invoice_no')) {
                $table->string('invoice_no', 60)->nullable()->after('supplier_id');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'capitalisation_date')) {
                $table->date('capitalisation_date')->nullable()->after('acquired_date');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'depreciation_method')) {
                $table->enum('depreciation_method', ['STRAIGHT_LINE', 'REDUCING_BALANCE'])->default('STRAIGHT_LINE')->after('useful_life_months');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'depreciation_start_date')) {
                $table->date('depreciation_start_date')->nullable()->after('depreciation_method');
            }
            // Section 13/14 — Disposal vs Write-Off are the same
            // underlying transaction (remove the asset from the books)
            // but need their own type so reports can tell a Sale from a
            // Write-Off. NULL until disposed.
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposal_type')) {
                $table->string('disposal_type', 20)->nullable()->after('disposal_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            foreach (['category_id', 'location_id', 'cost_centre_id', 'fund_id', 'supplier_id'] as $fk) {
                if (Schema::hasColumn('cbe_fixed_assets', $fk)) {
                    $table->dropForeign(['cbe_fixed_assets_'.$fk.'_foreign']);
                }
            }
            $table->dropColumn([
                'category_id', 'location_id', 'cost_centre_id', 'fund_id', 'supplier_id',
                'invoice_no', 'capitalisation_date', 'depreciation_method', 'depreciation_start_date', 'disposal_type',
            ]);
        });
    }
};
