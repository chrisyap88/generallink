<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #354) — per Chris: AR-side Debit Note and Credit
// Note entry, the customer-facing counterpart to the existing supplier
// cbe_debit_notes table. A CREDIT note reduces what a customer owes
// (returned goods, discount, billing correction) — posts DEBIT the
// income account / CREDIT Accounts Receivable, partially reversing the
// invoice. A DEBIT note increases what a customer owes (additional
// charge after the original invoice) — posts DEBIT Accounts Receivable /
// CREDIT the income account. See CbeAccountingService::postArDebitNote()
// and postArCreditNote(). Two separate tables (mirroring the AP side
// being its own table) rather than one table with a type flag, so each
// is independently listable/auditable exactly as Chris asked for them
// as two distinct menu items.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ar_debit_notes')) {
            Schema::create('cbe_ar_debit_notes', function (Blueprint $table) {
                $table->uuid('debit_note_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->date('note_date');
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

        if (! Schema::hasTable('cbe_ar_credit_notes')) {
            Schema::create('cbe_ar_credit_notes', function (Blueprint $table) {
                $table->uuid('credit_note_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('customer_id');
                $table->uuid('invoice_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->date('note_date');
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
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ar_credit_notes');
        Schema::dropIfExists('cbe_ar_debit_notes');
    }
};
