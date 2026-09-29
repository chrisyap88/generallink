<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #329) — Fixed Asset upgrade. Adds tag/location/
// funding-source fields for the register, disposal fields, and a
// depreciation posting log. Depreciation was previously calculated
// on the fly for DISPLAY ONLY (see the original cbe_fixed_assets
// migration comment) and never touched the ledger — this closes that
// gap: an officer clicks "Post This Month's Depreciation" on an
// asset, which writes one row here AND one journal entry (Dr
// Depreciation Expense, Cr Accumulated Depreciation). The unique key
// on (asset_id, period_month) makes it impossible to double-post the
// same asset for the same month.
return new class extends Migration
{
    public function up(): void
    {
        // NEW — idempotent column-by-column: an earlier run of this
        // migration partially applied (the ALTER TABLE succeeded, then
        // the CREATE TABLE below failed on a too-long constraint name).
        // MySQL DDL auto-commits, so those columns stayed even though
        // Laravel didn't mark the migration as run. Checking hasColumn()
        // first makes it safe to run again either way.
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_fixed_assets', 'asset_tag')) {
                $table->string('asset_tag', 60)->nullable()->after('asset_class');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'location')) {
                $table->string('location', 150)->nullable()->after('asset_tag');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'funding_source')) {
                $table->string('funding_source', 150)->nullable()->after('location');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposed_date')) {
                $table->date('disposed_date')->nullable()->after('status');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposal_proceeds')) {
                $table->decimal('disposal_proceeds', 12, 2)->nullable()->after('disposed_date');
            }
            if (! Schema::hasColumn('cbe_fixed_assets', 'disposal_reason')) {
                $table->string('disposal_reason', 255)->nullable()->after('disposal_proceeds');
            }
        });

        if (! Schema::hasTable('cbe_fixed_asset_depreciation_entries')) {
            Schema::create('cbe_fixed_asset_depreciation_entries', function (Blueprint $table) {
                $table->uuid('entry_id')->primary();
                $table->uuid('asset_id');
                $table->string('period_month', 7); // 'YYYY-MM'
                $table->decimal('amount', 12, 2);
                $table->uuid('journal_id')->nullable();
                $table->uuid('posted_by');
                $table->timestamps();

                $table->foreign('asset_id')->references('asset_id')->on('cbe_fixed_assets')->onDelete('cascade');
                // Explicit short name: MySQL's 64-char identifier limit
                // rejects the auto-generated name for this table/column pair.
                $table->unique(['asset_id', 'period_month'], 'cbe_fa_depr_asset_period_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_fixed_asset_depreciation_entries');
        Schema::table('cbe_fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['asset_tag', 'location', 'funding_source', 'disposed_date', 'disposal_proceeds', 'disposal_reason']);
        });
    }
};
