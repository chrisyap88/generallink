<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — Accounts Payable: a bill is recognised as owed
// (accrual basis) the moment it's entered, before any cash actually
// leaves — this is what makes "Accounts Payable" / "Bills Due Soon" /
// AP Aging meaningful, distinct from cbe_transactions which only ever
// records cash already paid. See CbeAccountingService::postBill().
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchase_bills')) {
            Schema::create('cbe_purchase_bills', function (Blueprint $table) {
                $table->uuid('bill_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->string('bill_no', 60)->nullable();
                $table->date('bill_date');
                $table->date('due_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->enum('status', ['UNPAID', 'PARTIALLY_PAID', 'PAID', 'CANCELLED'])->default('UNPAID');
                $table->uuid('category_id')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_original_name')->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status', 'due_date'], 'cbe_bills_node_status_due_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchase_bills');
    }
};
