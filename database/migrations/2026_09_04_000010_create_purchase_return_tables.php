<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 9: Purchase Return — goods returned to the supplier,
// referencing the original PO and/or GRN. The return itself does not
// post to the GL directly; where a supplier owes a credit back, the
// existing AP Credit Note screen (cbe_ar_credit_notes' AP counterpart —
// see ap_credit_note_id below) is where that credit is actually recorded
// against the supplier's balance, exactly like the rest of this system's
// division of labour between a source document (Purchasing) and the
// ledger-facing document (AP). ap_credit_note_id is filled in once the
// user raises that credit note, linking the two records together.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchase_returns')) {
            Schema::create('cbe_purchase_returns', function (Blueprint $table) {
                $table->uuid('return_id')->primary();
                $table->string('doc_ref_no', 40)->nullable();
                $table->uuid('cbe_node_id');
                $table->uuid('po_id')->nullable();
                $table->uuid('grn_id')->nullable();
                $table->uuid('supplier_id');
                $table->date('return_date');
                $table->string('reason', 255)->nullable();
                $table->enum('status', ['DRAFT', 'CONFIRMED', 'CREDIT_RAISED', 'CANCELLED'])->default('CONFIRMED');
                $table->uuid('ap_credit_note_id')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('po_id')->references('po_id')->on('cbe_purchase_orders')->onDelete('set null');
                $table->foreign('grn_id')->references('grn_id')->on('cbe_goods_receipts')->onDelete('set null');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }

        if (! Schema::hasTable('cbe_purchase_return_lines')) {
            Schema::create('cbe_purchase_return_lines', function (Blueprint $table) {
                $table->uuid('return_line_id')->primary();
                $table->uuid('return_id');
                $table->string('description', 255)->nullable();
                $table->decimal('return_quantity', 10, 2)->default(0);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->decimal('line_amount', 12, 2)->default(0);
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('return_id')->references('return_id')->on('cbe_purchase_returns')->onDelete('cascade');
                $table->index('return_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchase_return_lines');
        Schema::dropIfExists('cbe_purchase_returns');
    }
};
