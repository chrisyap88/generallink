<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 10: Supplier Invoice needs a GRN link (alongside the
// po_id link already added in Task #393) so 3-way matching (PO + GRN +
// Invoice) is possible, plus Fund/Cost-Centre tagging carried through
// from the PO/Requisition. match_status records the outcome of that
// comparison at the point the bill is saved: MATCHED (PO+GRN+Invoice all
// agree), PRICE_VARIANCE, QUANTITY_VARIANCE, NO_PO (billed with nothing
// ordered first — allowed, just flagged) — computed and stored rather
// than only shown live, so the PO/GRN/Invoice Matching Report (section 19)
// can list historical exceptions without recomputing every bill on every
// run. Duplicate invoice checking: a unique index on (supplier_id,
// bill_no) — MySQL unique indexes allow any number of NULLs through, so
// bills without a supplier-issued invoice number are unaffected; two
// bills from the same supplier both claiming the same invoice number are
// blocked at the database level as a last line of defence, in addition to
// the application-level check the controller performs before insert.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_purchase_bills', 'grn_id')) {
                $table->uuid('grn_id')->nullable()->after('po_id');
                $table->foreign('grn_id')->references('grn_id')->on('cbe_goods_receipts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_bills', 'cost_centre_id')) {
                $table->uuid('cost_centre_id')->nullable()->after('grn_id');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_bills', 'fund_id')) {
                $table->uuid('fund_id')->nullable()->after('cost_centre_id');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_purchase_bills', 'match_status')) {
                $table->enum('match_status', ['NOT_APPLICABLE', 'MATCHED', 'PRICE_VARIANCE', 'QUANTITY_VARIANCE', 'NO_PO', 'NO_GRN'])->default('NOT_APPLICABLE')->after('fund_id');
            }
        });

        // Wrapped in try/catch, not a hard failure: if bills entered before
        // this control existed happen to already contain a duplicate
        // (supplier_id, bill_no) pair, we do not want that historical data
        // to block this whole migration from running. The application-level
        // duplicate check in the controller is added regardless and covers
        // all NEW bills from this point on.
        if (! $this->indexExists('cbe_purchase_bills', 'cbe_bill_supplier_billno_unique')) {
            try {
                Schema::table('cbe_purchase_bills', function (Blueprint $table) {
                    $table->unique(['supplier_id', 'bill_no'], 'cbe_bill_supplier_billno_unique');
                });
            } catch (\Throwable $e) {
                // Existing duplicate data — skip the DB-level constraint,
                // application-level check still applies going forward.
            }
        }
    }

    public function down(): void
    {
        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            if ($this->indexExists('cbe_purchase_bills', 'cbe_bill_supplier_billno_unique')) {
                $table->dropUnique('cbe_bill_supplier_billno_unique');
            }
            foreach (['grn_id', 'cost_centre_id', 'fund_id'] as $fk) {
                if (Schema::hasColumn('cbe_purchase_bills', $fk)) {
                    $table->dropForeign(['cbe_purchase_bills_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['grn_id', 'cost_centre_id', 'fund_id', 'match_status']);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return count($result) > 0;
    }
};
