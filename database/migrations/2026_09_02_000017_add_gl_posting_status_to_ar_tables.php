<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #365) — per Chris's AR/GL Integration spec:
// every AR document must show its GL Posting Status (Not Posted /
// Posted / Reversed / Error) and its GL Journal Number, so a user can
// drill down from an invoice/payment/note straight to the journal it
// created. Before this, cbe_journal_entries.source_id pointed FROM the
// journal back to the document, but nothing pointed the other way.
return new class extends Migration
{
    private array $tables = ['cbe_invoices', 'cbe_invoice_payments', 'cbe_ar_debit_notes', 'cbe_ar_credit_notes'];

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
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'journal_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropForeign(['journal_id']);
                    $table->dropColumn(['journal_id', 'gl_posting_status']);
                });
            }
        }
    }
};
