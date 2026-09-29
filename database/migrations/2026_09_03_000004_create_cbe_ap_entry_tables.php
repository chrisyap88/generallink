<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #372) — per Chris's Temple/NGO AP spec, the AP-side
// counterparts to the AR Entry tables built for Task #358.
//
// cbe_ap_debit_notes — a supplier-issued (or buyer-corrected) INCREASE to
// what we owe (e.g. supplier under-billed and later charges more) — posts
// DEBIT the expense account / CREDIT Accounts Payable. This is a genuinely
// NEW feature: the existing cbe_debit_notes table (25 Aug 2026) already
// posts the opposite direction (Dr AP / Cr Expense) and is being
// relabelled "AP Credit Note" in the UI to match its real accounting
// effect — see the lang file comments and CbeAccountingController's
// "Credit Notes (AP)" section for the full explanation. That table/route/
// method names are untouched (no data risk); only the on-screen text
// changes.
//
// cbe_ap_refunds — cash a supplier pays back to us directly (not via a
// credit note against a future bill) — posts DEBIT Bank / CREDIT Accounts
// Payable (a refund reduces what we'd otherwise still owe, or if already
// fully paid, sits as a credit balance).
//
// cbe_ap_adjustments — generic AP write-off/correction, either direction,
// exactly mirroring cbe_ar_adjustments.
//
// cbe_ap_opening_balances — what we already owed a supplier on go-live day.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ap_debit_notes')) {
            Schema::create('cbe_ap_debit_notes', function (Blueprint $table) {
                $table->uuid('debit_note_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('bill_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->date('note_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_ap_refunds')) {
            Schema::create('cbe_ap_refunds', function (Blueprint $table) {
                $table->uuid('refund_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('bill_id')->nullable();
                $table->uuid('bank_account_id')->nullable();
                $table->date('refund_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_ap_adjustments')) {
            Schema::create('cbe_ap_adjustments', function (Blueprint $table) {
                $table->uuid('adjustment_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('bill_id')->nullable();
                $table->uuid('category_id')->nullable();
                $table->date('adjustment_date');
                $table->enum('direction', ['INCREASE', 'DECREASE']);
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }

        if (! Schema::hasTable('cbe_ap_opening_balances')) {
            Schema::create('cbe_ap_opening_balances', function (Blueprint $table) {
                $table->uuid('opening_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('bill_id')->nullable();
                $table->date('opening_date');
                $table->decimal('amount', 12, 2);
                $table->string('notes', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ap_opening_balances');
        Schema::dropIfExists('cbe_ap_adjustments');
        Schema::dropIfExists('cbe_ap_refunds');
        Schema::dropIfExists('cbe_ap_debit_notes');
    }
};
