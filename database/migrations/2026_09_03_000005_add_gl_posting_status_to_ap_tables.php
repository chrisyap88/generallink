<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #377) — per Chris's Temple/NGO AP spec (sections
// 10-11: GL Posting & Enquiry, AP/GL Reconciliation), every AP document
// must carry a GL journal reference and posting status with drill-down —
// exactly the same linkage already built for AR (migrations
// 2026_09_02_000017/000018). This had never been added to the AP side —
// the 3 original AP tables (Bill, Bill Payment, the existing supplier
// note table) posted to GL from day one but never stored which journal
// they posted to, so there was no drill-down. This migration closes that
// gap for all AP tables, old and new.
//
// Also adds pv_no (Payment Voucher number) to cbe_bill_payments — per
// Chris's spec, a Payment Voucher can cover one payment split across
// several bills for the same supplier (same pattern as AR's Payment
// Allocation, which doesn't use a separate header table either — several
// cbe_bill_payments rows share one pv_no instead of introducing a new
// header/line table pair).
return new class extends Migration
{
    private array $tables = [
        'cbe_purchase_bills', 'cbe_bill_payments', 'cbe_debit_notes',
        'cbe_ap_debit_notes', 'cbe_ap_refunds', 'cbe_ap_adjustments', 'cbe_ap_opening_balances',
    ];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasColumn($t, 'journal_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->uuid('journal_id')->nullable()->after('recorded_by');
                    $table->string('gl_posting_status', 12)->default('NOT_POSTED')->after('journal_id');
                    $table->foreign('journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
                });
            }
        }

        if (! Schema::hasColumn('cbe_bill_payments', 'pv_no')) {
            Schema::table('cbe_bill_payments', function (Blueprint $table) {
                $table->string('pv_no', 30)->nullable()->after('bill_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_bill_payments', 'pv_no')) {
            Schema::table('cbe_bill_payments', function (Blueprint $table) {
                $table->dropColumn('pv_no');
            });
        }
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'journal_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropForeign([$table->getTable() . '_journal_id_foreign']);
                    $table->dropColumn(['journal_id', 'gl_posting_status']);
                });
            }
        }
    }
};
