<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #358) — per Chris's Temple/NGO AR spec, 3 more
// Entry functions beyond Invoice/Receipt/Debit Note/Credit Note:
// Adjustment (write-off or balance correction, kept separate from the
// Debit/Credit Note tables so it has its own menu item + audit trail,
// exactly as Chris listed it), Refund (money paid back to a customer),
// and Opening Balance (what a customer already owed on go-live day).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ar_adjustments')) {
            Schema::create('cbe_ar_adjustments', function (Blueprint $table) {
                $table->uuid('adjustment_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->date('adjustment_date');
                $table->enum('direction', ['INCREASE', 'DECREASE']);
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('cbe_customers')->onDelete('restrict');
                $table->foreign('invoice_id')->references('invoice_id')->on('cbe_invoices')->onDelete('set null');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_ar_refunds')) {
            Schema::create('cbe_ar_refunds', function (Blueprint $table) {
                $table->uuid('refund_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('bank_account_id')->nullable();
                $table->date('refund_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('cbe_customers')->onDelete('restrict');
                $table->foreign('invoice_id')->references('invoice_id')->on('cbe_invoices')->onDelete('set null');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_ar_opening_balances')) {
            Schema::create('cbe_ar_opening_balances', function (Blueprint $table) {
                $table->uuid('opening_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->date('opening_date');
                $table->decimal('amount', 12, 2);
                $table->string('notes', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('cbe_customers')->onDelete('restrict');
                $table->foreign('invoice_id')->references('invoice_id')->on('cbe_invoices')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ar_opening_balances');
        Schema::dropIfExists('cbe_ar_refunds');
        Schema::dropIfExists('cbe_ar_adjustments');
    }
};
