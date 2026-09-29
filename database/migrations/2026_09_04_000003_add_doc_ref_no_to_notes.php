<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #392) — per Chris: every document should have its
// own gap-free reference number, the same as Bills/Invoices/Journal
// Vouchers already do. Debit Notes and Credit Notes were the one
// exception found during verification — they only ever had a UUID
// primary key, nothing a treasurer could write on a physical form or
// quote to a supplier/customer over the phone. Adds doc_ref_no to all 4
// tables (the original cbe_debit_notes, plus the newer cbe_ap_debit_notes/
// cbe_ar_debit_notes/cbe_ar_credit_notes from Tasks #354/#358/#372), each
// with its own distinct document-number series via nextDocumentNumber().
// Prospective only — existing rows keep doc_ref_no null.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cbe_debit_notes', 'cbe_ap_debit_notes', 'cbe_ar_debit_notes', 'cbe_ar_credit_notes'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'doc_ref_no')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->string('doc_ref_no', 30)->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['cbe_debit_notes', 'cbe_ap_debit_notes', 'cbe_ar_debit_notes', 'cbe_ar_credit_notes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'doc_ref_no')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('doc_ref_no');
                });
            }
        }
    }
};
