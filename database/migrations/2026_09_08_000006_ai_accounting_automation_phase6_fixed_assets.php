<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 6: Fixed Asset Intelligence.
//
// No new posting pipeline — CbeAccountingService::postAiFixedAssetAcquisition()
// (added alongside this migration) reuses createFixedAssetDirect() (the
// same factored-out insert the manual "Add Asset" screen and the
// approval-queue replay both already share) and posts the acquisition
// journal itself (Dr the category's mapped Fixed Asset account, or the
// same generic fallback account manual entry already uses when no
// category is picked — never a new fabricated account), then links the
// register row to that journal — mirroring exactly how
// createFixedAssetFromBill() already links a capital Bill's existing
// journal instead of posting a second one.
//
// Category is auto-matched from the line's own description text against
// this org's actual configured Asset Category names (a small built-in
// keyword map — Computer/Furniture/Vehicle/Renovation/etc. — never a
// hardcoded business master list); when nothing matches, the asset is
// still created (category_id left null, useful_life_months/depreciation
// method fall back to the default 60 months / Straight-Line, exactly
// what manual entry with no category picked already does today) rather
// than blocked, since Chris can always fill in the category afterward
// from the normal Fixed Asset Register screen.
//
// matched_asset_id records which register entry a line was capitalised
// as, for the review screen and the audit trail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_asset_id')) {
                $table->uuid('matched_asset_id')->nullable()->after('matched_invoice_id');
            }
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->foreign('matched_asset_id', 'cbe_ai_extract_matched_asset_fk')->references('asset_id')->on('cbe_fixed_assets')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropForeign('cbe_ai_extract_matched_asset_fk');
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropColumn('matched_asset_id');
        });
    }
};
