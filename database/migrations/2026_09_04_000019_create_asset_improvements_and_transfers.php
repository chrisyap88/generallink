<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, spec
// sections 9 (Asset Improvement / Additional Cost) and 12 (Asset
// Transfer).
//
// Improvements keep Original Cost, Additional Cost and (computed)
// Revised Cost separate rather than overwriting cbe_fixed_assets.
// acquisition_cost directly — the spec explicitly asks for all three
// to remain visible, and depreciation/disposal calculations now read
// a computed "revised cost" (original + all posted improvements) via
// CbeAccountingService::revisedAssetCost() instead of the raw column.
// Each improvement posts its own GL entry (Dr Fixed Asset, Cr Cash)
// the same way the original acquisition does.
//
// Transfers are a location/department/fund change on the SAME asset
// row — "must not duplicate the asset record" per spec — so this is a
// history log only; no GL entry (moving an asset between rooms/funds
// is not a monetary event), transfer_date/from-values/to-values/
// reason/authorized_by keep it fully traceable per spec section 16
// (Asset History).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_fixed_asset_improvements')) {
            Schema::create('cbe_fixed_asset_improvements', function (Blueprint $table) {
                $table->uuid('improvement_id')->primary();
                $table->uuid('asset_id');
                $table->uuid('cbe_node_id');
                $table->string('description', 255);
                $table->decimal('additional_cost', 12, 2);
                $table->date('transaction_date');
                $table->string('source_reference', 100)->nullable();
                $table->uuid('journal_id')->nullable();
                $table->string('gl_posting_status', 20)->default('NOT_POSTED');
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('asset_id')->references('asset_id')->on('cbe_fixed_assets')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['asset_id', 'transaction_date']);
            });
        }

        if (! Schema::hasTable('cbe_fixed_asset_transfers')) {
            Schema::create('cbe_fixed_asset_transfers', function (Blueprint $table) {
                $table->uuid('transfer_id')->primary();
                $table->uuid('asset_id');
                $table->uuid('cbe_node_id');
                $table->date('transfer_date');
                $table->uuid('from_location_id')->nullable();
                $table->uuid('to_location_id')->nullable();
                $table->uuid('from_cost_centre_id')->nullable();
                $table->uuid('to_cost_centre_id')->nullable();
                $table->uuid('from_fund_id')->nullable();
                $table->uuid('to_fund_id')->nullable();
                $table->string('reason', 255)->nullable();
                $table->uuid('authorized_by');
                $table->timestamps();

                $table->foreign('asset_id')->references('asset_id')->on('cbe_fixed_assets')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['asset_id', 'transfer_date']);
            });
        }

        // Section 14 (Asset Write-Off) — Asset Condition and Supporting
        // Document are only meaningful at disposal/write-off time, so
        // they live on cbe_fixed_assets alongside the existing
        // disposal_* columns rather than a new table.
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_fixed_assets', 'asset_condition')) {
                $table->string('asset_condition', 30)->nullable()->after('disposal_type');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposal_attachment_path')) {
                $table->string('disposal_attachment_path', 255)->nullable()->after('asset_condition');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposal_attachment_original_name')) {
                $table->string('disposal_attachment_original_name', 255)->nullable()->after('disposal_attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            foreach (['asset_condition', 'disposal_attachment_path', 'disposal_attachment_original_name'] as $col) {
                if (Schema::hasColumn('cbe_fixed_assets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('cbe_fixed_asset_transfers');
        Schema::dropIfExists('cbe_fixed_asset_improvements');
    }
};
