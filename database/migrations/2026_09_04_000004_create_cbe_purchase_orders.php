<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #393) — Purchase Order. Per Chris: "purchasing is
// compulsory, temple buy a lot of things during events and festival
// prayers seasons" — a genuine gap, since the system had a Purchase
// Request (a pre-approval ask) and a Purchase Bill (what the supplier
// actually charged), but nothing in between representing the order
// actually placed with the supplier before delivery/invoice.
//
// This completes the standard 3-stage procurement chain: Purchase
// Request (internal ask, optional) -> Purchase Order (commitment sent to
// the supplier) -> Purchase Bill (what was actually billed, which may
// differ slightly from the PO). A PO does NOT post to the GL — placing
// an order is not yet a liability in accrual accounting, only receiving
// the bill is (see CbeAccountingService::postBill()). This mirrors how
// cbe_purchase_requests already works (no GL posting until converted to
// a Bill) and is standard commitment-accounting practice.
//
// request_id (nullable) links back to an approved Purchase Request this
// PO was raised from, when the temple used that optional first step;
// null means the PO was raised directly (a quick festival purchase with
// no separate internal request stage — Chris's own example of frequent,
// often time-pressured buying doesn't always warrant two approval
// stages first).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchase_orders')) {
            Schema::create('cbe_purchase_orders', function (Blueprint $table) {
                $table->uuid('po_id')->primary();
                $table->string('doc_ref_no', 40)->nullable();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('request_id')->nullable();
                $table->date('po_date');
                $table->date('expected_delivery_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                // OPEN: sent to supplier, nothing received yet.
                // PARTIALLY_RECEIVED / FULLY_RECEIVED: goods/services in.
                // CLOSED: fully received AND fully billed — done.
                // CANCELLED: order called off before fulfilment.
                $table->enum('status', ['OPEN', 'PARTIALLY_RECEIVED', 'FULLY_RECEIVED', 'CLOSED', 'CANCELLED'])->default('OPEN');
                $table->string('notes', 255)->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('request_id')->references('request_id')->on('cbe_purchase_requests')->onDelete('set null');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }

        if (! Schema::hasTable('cbe_purchase_order_lines')) {
            Schema::create('cbe_purchase_order_lines', function (Blueprint $table) {
                $table->uuid('line_id')->primary();
                $table->uuid('po_id');
                $table->uuid('category_id')->nullable();
                $table->string('description', 255)->nullable();
                $table->decimal('quantity', 10, 2)->default(1);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('line_amount', 12, 2);
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('po_id')->references('po_id')->on('cbe_purchase_orders')->onDelete('cascade');
                $table->index('po_id');
            });
        }

        if (! Schema::hasColumn('cbe_purchase_bills', 'po_id')) {
            Schema::table('cbe_purchase_bills', function (Blueprint $table) {
                $table->uuid('po_id')->nullable()->after('supplier_id');
            });
            Schema::table('cbe_purchase_bills', function (Blueprint $table) {
                $table->foreign('po_id')->references('po_id')->on('cbe_purchase_orders')->onDelete('set null');
            });
        }

        if (! Schema::hasColumn('cbe_purchase_requests', 'converted_po_id')) {
            Schema::table('cbe_purchase_requests', function (Blueprint $table) {
                $table->uuid('converted_po_id')->nullable()->after('converted_bill_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_purchase_requests', 'converted_po_id')) {
            Schema::table('cbe_purchase_requests', function (Blueprint $table) {
                $table->dropColumn('converted_po_id');
            });
        }
        if (Schema::hasColumn('cbe_purchase_bills', 'po_id')) {
            Schema::table('cbe_purchase_bills', function (Blueprint $table) {
                $table->dropForeign(['po_id']);
                $table->dropColumn('po_id');
            });
        }
        Schema::dropIfExists('cbe_purchase_order_lines');
        Schema::dropIfExists('cbe_purchase_orders');
    }
};
