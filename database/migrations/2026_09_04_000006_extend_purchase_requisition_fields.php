<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 4: a Purchase Requisition needs Department/Project (via
// the existing cbe_cost_centres "one table, a type column" pattern
// already used for GL journal lines), Fund, Required Date, Remarks and a
// supporting-document attachment (same jpg/png/pdf pattern already used
// on Purchase Bills and Journal Vouchers). Note: the spec's "Purpose"
// field is already covered by the existing "description" column, which
// the create-purchase-request screen already labels Purpose — not
// duplicated here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_purchase_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_purchase_requests', 'cost_centre_id')) {
                $table->uuid('cost_centre_id')->nullable()->after('supplier_id');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_requests', 'fund_id')) {
                $table->uuid('fund_id')->nullable()->after('cost_centre_id');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_requests', 'required_date')) {
                $table->date('required_date')->nullable()->after('request_date');
            }
            if (! Schema::hasColumn('cbe_purchase_requests', 'remarks')) {
                $table->string('remarks', 255)->nullable()->after('description');
            }
            if (! Schema::hasColumn('cbe_purchase_requests', 'attachment_path')) {
                $table->string('attachment_path')->nullable()->after('remarks');
            }
            if (! Schema::hasColumn('cbe_purchase_requests', 'attachment_original_name')) {
                $table->string('attachment_original_name')->nullable()->after('attachment_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_purchase_requests', function (Blueprint $table) {
            foreach (['cost_centre_id', 'fund_id'] as $fk) {
                if (Schema::hasColumn('cbe_purchase_requests', $fk)) {
                    $table->dropForeign(['cbe_purchase_requests_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['cost_centre_id', 'fund_id', 'required_date', 'remarks', 'attachment_path', 'attachment_original_name']);
        });
    }
};
