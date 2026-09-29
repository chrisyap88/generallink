<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, per Chris's
// full written specification. Section 2 (Asset Category) and section 6
// (GL Account Mapping) ask for each Asset Category to map to its own
// Fixed Asset / Accumulated Depreciation / Depreciation Expense
// accounts (e.g. Building -> Building Asset Account, Computer
// Equipment -> Computer Equipment Account, etc.) instead of every
// asset sharing one single global set of 3 accounts as today. This
// mirrors cbe_transaction_categories exactly (group_label_id-scoped,
// admin-configurable list, never hardcoded business data) but adds the
// 3 GL mapping columns section 6 asks for. All three accounts are
// nullable — an unmapped category still works, falling back to the
// existing global Fixed Asset/Accum. Depreciation/Depreciation Expense
// accounts, so nothing already posted is disturbed.
//
// Section 4 (Asset Location) is a simpler admin list with no GL
// mapping. Locations are physical rooms/buildings specific to one
// temple/branch, so — unlike categories, which make sense shared
// across an entire organisation's chart of accounts — this is scoped
// per cbe_node_id, the same convention already used for Funds.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_asset_categories')) {
            Schema::create('cbe_asset_categories', function (Blueprint $table) {
                $table->uuid('category_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('category_name', 150);
                $table->string('category_name_zh', 150)->nullable();
                $table->uuid('fixed_asset_account_id')->nullable();
                $table->uuid('accum_depreciation_account_id')->nullable();
                $table->uuid('depreciation_expense_account_id')->nullable();
                $table->unsignedInteger('default_useful_life_months')->nullable();
                $table->enum('default_depreciation_method', ['STRAIGHT_LINE', 'REDUCING_BALANCE'])->default('STRAIGHT_LINE');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('fixed_asset_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->foreign('accum_depreciation_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->foreign('depreciation_expense_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->index(['group_label_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cbe_asset_locations')) {
            Schema::create('cbe_asset_locations', function (Blueprint $table) {
                $table->uuid('location_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('location_name', 150);
                $table->string('location_name_zh', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['cbe_node_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_asset_locations');
        Schema::dropIfExists('cbe_asset_categories');
    }
};
