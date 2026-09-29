<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 8: Goods Receipt / Service Receipt records what actually
// arrived against a Purchase Order, distinct from the PO's ordered
// quantity and the eventual Supplier Invoice's billed quantity — this is
// what makes 3-way matching (PO + GRN + Invoice) possible. One GRN can
// cover some or all lines of a PO (partial receipt); a PO can have
// several GRNs over time until fully received. received_quantity on the
// PO line itself is a running cumulative total, updated every time a new
// GRN line posts against it, so "outstanding" is always PO ordered_qty
// minus that running total without re-summing every GRN each time.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_goods_receipts')) {
            Schema::create('cbe_goods_receipts', function (Blueprint $table) {
                $table->uuid('grn_id')->primary();
                $table->string('doc_ref_no', 40)->nullable();
                $table->uuid('cbe_node_id');
                $table->uuid('po_id');
                $table->uuid('supplier_id');
                $table->date('grn_date');
                // GOODS: physical delivery. SERVICE: service completion
                // confirmation (spec section 8's "Service Completion
                // Confirmation") — same table, a type flag, since both
                // follow the identical receive-against-PO workflow.
                $table->enum('receipt_type', ['GOODS', 'SERVICE'])->default('GOODS');
                $table->enum('status', ['DRAFT', 'CONFIRMED', 'POSTED', 'CANCELLED'])->default('CONFIRMED');
                $table->string('remarks', 255)->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_original_name')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('po_id')->references('po_id')->on('cbe_purchase_orders')->onDelete('restrict');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'po_id']);
            });
        }

        if (! Schema::hasTable('cbe_goods_receipt_lines')) {
            Schema::create('cbe_goods_receipt_lines', function (Blueprint $table) {
                $table->uuid('grn_line_id')->primary();
                $table->uuid('grn_id');
                $table->uuid('po_line_id')->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('ordered_quantity', 10, 2)->default(0);
                $table->decimal('received_quantity', 10, 2)->default(0);
                $table->decimal('rejected_quantity', 10, 2)->default(0);
                // GOOD / DAMAGED / SHORT / OVER — free-form condition note
                // kept short and code-like so it can be filtered in reports.
                $table->string('condition_note', 20)->default('GOOD');
                $table->string('remarks', 255)->nullable();
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('grn_id')->references('grn_id')->on('cbe_goods_receipts')->onDelete('cascade');
                $table->foreign('po_line_id')->references('line_id')->on('cbe_purchase_order_lines')->onDelete('set null');
                $table->index('grn_id');
            });
        }

        // Running cumulative received qty on the PO line itself — see
        // migration comment above for why this is kept as a running total
        // rather than summed live from cbe_goods_receipt_lines every time.
        Schema::table('cbe_purchase_order_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_purchase_order_lines', 'received_quantity')) {
                $table->decimal('received_quantity', 10, 2)->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_purchase_order_lines', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_purchase_order_lines', 'received_quantity')) {
                $table->dropColumn('received_quantity');
            }
        });
        Schema::dropIfExists('cbe_goods_receipt_lines');
        Schema::dropIfExists('cbe_goods_receipts');
    }
};
