<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 30 Aug 2026 — Fixed Asset Register, rebuilt on the native ledger.
// Depreciation (straight-line, based on cost/salvage/useful_life_months)
// is calculated on the fly for display in the register — it is NOT
// posted to the General Ledger automatically in this version (that
// would need a monthly scheduled job). Only the acquisition cost is
// posted (Dr Fixed Assets, Cr Cash) — see
// CbeAccountingService::postFixedAssetCapitalization(). This means the
// Balance Sheet currently shows fixed assets at full cost, not net of
// depreciation. Flagged to Chris; automatic monthly depreciation
// postings can be added later if wanted.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_fixed_assets')) {
            Schema::create('cbe_fixed_assets', function (Blueprint $table) {
                $table->uuid('asset_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('asset_name', 150);
                $table->string('asset_class', 100)->nullable();
                $table->decimal('acquisition_cost', 12, 2);
                $table->decimal('salvage_value', 12, 2)->default(0);
                $table->unsignedInteger('useful_life_months');
                $table->date('acquired_date');
                $table->enum('status', ['ACTIVE', 'FULLY_DEPRECIATED', 'DISPOSED'])->default('ACTIVE');
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'acquired_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_fixed_assets');
    }
};
