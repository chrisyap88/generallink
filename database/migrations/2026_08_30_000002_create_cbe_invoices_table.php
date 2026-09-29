<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 30 Aug 2026 — Accounts Receivable, accrual basis, exact mirror of
// cbe_purchase_bills (same 4-state status enum, same paid_amount
// pattern) so AR behaves exactly like AP already does — one convention
// across the whole Financial Module. See CbeAccountingService::postInvoice().
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_invoices')) {
            Schema::create('cbe_invoices', function (Blueprint $table) {
                $table->uuid('invoice_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->string('invoice_no', 60)->nullable();
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->enum('status', ['UNPAID', 'PARTIALLY_PAID', 'PAID', 'CANCELLED'])->default('UNPAID');
                $table->uuid('category_id')->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('cbe_customers')->onDelete('restrict');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status', 'due_date'], 'cbe_invoices_node_status_due_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_invoices');
    }
};
