<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 4: GL Intelligence + Double-Entry Journal
// Engine.
//
// No new posting pipeline — CbeAccountingService::postAiClassifiedBankTransaction()
// (added alongside this migration) reuses the exact same private
// postJournal() every other module in this app posts through
// (Bills, Invoices, Manual JV, Bank Adjustments, Fixed Assets), so an
// AI-generated entry gets its own Journal Number, respects the fiscal
// period lock, and shows up in Trial Balance/P&L/Balance Sheet exactly
// like any other entry.
//
// cbe_ai_extracted_transactions only needs two new columns to record
// the outcome: posted_journal_id when a journal really was created, and
// posting_note explaining why one wasn't — never silent, and never a
// guess presented as a real posting. Categories needing a subledger
// (SUPPLIER/CUSTOMER -> Phase 5's AP/AR, ASSET -> Phase 6's fixed asset
// detection) or a second, unknowable leg (TRANSFER/LOAN) are
// deliberately left unposted by this phase; see the comment on
// postAiClassifiedBankTransaction() in CbeAccountingService.php for the
// full reasoning.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'posted_journal_id')) {
                $table->uuid('posted_journal_id')->nullable()->after('matched_donor_id');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'posting_note')) {
                $table->string('posting_note', 150)->nullable()->after('posted_journal_id');
            }
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->foreign('posted_journal_id', 'cbe_ai_extract_posted_journal_fk')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropForeign('cbe_ai_extract_posted_journal_fk');
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropColumn(['posted_journal_id', 'posting_note']);
        });
    }
};
