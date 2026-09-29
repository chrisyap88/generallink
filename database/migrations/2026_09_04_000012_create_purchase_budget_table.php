<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, sections 17-18: Budget Control and Purchase Commitment. One
// simple budget line per node/fiscal-year/expense-account, optionally
// narrowed to a Fund and/or Cost Centre/Department/Project — the same
// "light, computed alongside the formal ledger" pattern already used for
// Fund Balance (Task #330), not a full budget-versioning subsystem.
// Actual figures (PO commitment, actual invoice, actual payment,
// remaining budget) are computed live from cbe_purchase_orders,
// cbe_purchase_bills and cbe_bill_payments at report time — this table
// only stores the budget figure itself, entered once per period.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchase_budgets')) {
            Schema::create('cbe_purchase_budgets', function (Blueprint $table) {
                $table->uuid('budget_id')->primary();
                $table->uuid('cbe_node_id');
                $table->unsignedSmallInteger('fiscal_year');
                $table->uuid('account_id')->nullable();
                $table->uuid('cost_centre_id')->nullable();
                $table->uuid('fund_id')->nullable();
                $table->string('budget_name', 150)->nullable();
                $table->decimal('budget_amount', 12, 2)->default(0);
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'fiscal_year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchase_budgets');
    }
};
