<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, sections 6-7: Purchase Order needs Fund/Cost-Centre tagging (same
// pattern as journal lines), Delivery Address, Discount and Tax amounts,
// an rfq_id link back to the quotation it was awarded from (when one was
// used), and a configurable approval workflow. Approval reuses the
// existing per-node Maker-Checker threshold (cbe_approval_settings,
// already built for Bill Payments/Bank Transfers/Journal Vouchers) rather
// than a new bespoke rules engine — a PO above that node's threshold
// needs a second officer's approval before it can be sent to the
// supplier or converted to a Bill, exactly like any other above-threshold
// transaction in this system. Amendment control: once a PO has moved
// past OPEN (received against, or approved), its lines can no longer be
// edited directly — only cancelled and re-raised — enforced in the
// controller, not a DB constraint.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_purchase_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_purchase_orders', 'rfq_id')) {
                $table->uuid('rfq_id')->nullable()->after('request_id');
                $table->foreign('rfq_id')->references('rfq_id')->on('cbe_purchase_rfqs')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'cost_centre_id')) {
                $table->uuid('cost_centre_id')->nullable()->after('supplier_id');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'fund_id')) {
                $table->uuid('fund_id')->nullable()->after('cost_centre_id');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'delivery_address')) {
                $table->string('delivery_address', 255)->nullable()->after('expected_delivery_date');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('discount_amount');
            }
            // PENDING_APPROVAL / APPROVED / REJECTED — independent from the
            // lifecycle 'status' column (OPEN/RECEIVED/CLOSED/CANCELLED).
            // Defaults to APPROVED so a node with Maker-Checker switched
            // off, or a PO below the threshold, behaves exactly as today.
            if (! Schema::hasColumn('cbe_purchase_orders', 'approval_status')) {
                $table->enum('approval_status', ['PENDING_APPROVAL', 'APPROVED', 'REJECTED'])->default('APPROVED')->after('status');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('approval_status');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('cbe_purchase_orders', 'approval_remarks')) {
                $table->string('approval_remarks', 255)->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_purchase_orders', function (Blueprint $table) {
            foreach (['rfq_id', 'cost_centre_id', 'fund_id'] as $fk) {
                if (Schema::hasColumn('cbe_purchase_orders', $fk)) {
                    $table->dropForeign(['cbe_purchase_orders_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['rfq_id', 'cost_centre_id', 'fund_id', 'delivery_address', 'discount_amount', 'tax_amount', 'approval_status', 'approved_by', 'approved_at', 'approval_remarks']);
        });
    }
};
