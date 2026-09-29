<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394 gap-fix) — two bugs found while wiring the
// Purchasing spec's Capital Asset Purchase flow (Purchasing -> Invoice ->
// AP -> Fixed Asset -> GL):
//
// 1. CbeAccountingService::postFixedAssetCapitalization() has checked
//    `$asset->journal_id` as a duplicate-posting guard since Task #386,
//    but cbe_fixed_assets was never given a journal_id/gl_posting_status
//    column — the guard has silently been a no-op this whole time (a
//    second call would have posted a second Dr/Cr journal for the same
//    asset). This migration adds the missing columns and the service is
//    fixed in the same session to actually populate them via
//    markGlPosted(), matching every other GL-integrated document.
// 2. There was no way to trace a Fixed Asset back to the Supplier Bill
//    that purchased it. bill_id closes that: when a capital-item Bill is
//    used to create a Fixed Asset register entry, the asset is linked to
//    the Bill instead of posting a second acquisition journal (the
//    Bill's own journal already recorded the Dr Fixed Asset / Cr AP
//    entry via its line's category-to-GL-account mapping).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_fixed_assets', 'journal_id')) {
                $table->uuid('journal_id')->nullable()->after('recorded_by');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'gl_posting_status')) {
                $table->string('gl_posting_status', 20)->default('NOT_POSTED')->after('journal_id');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'bill_id')) {
                $table->uuid('bill_id')->nullable()->after('gl_posting_status');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_fixed_assets', 'bill_id')) {
                $table->dropForeign(['bill_id']);
                $table->dropColumn('bill_id');
            }
            if (Schema::hasColumn('cbe_fixed_assets', 'gl_posting_status')) {
                $table->dropColumn('gl_posting_status');
            }
            if (Schema::hasColumn('cbe_fixed_assets', 'journal_id')) {
                $table->dropColumn('journal_id');
            }
        });
    }
};
