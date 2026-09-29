<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
// Automation Management Module, Phase 15: Strengthened Duplicate
// Detection (GL-level, cross-source).
//
// Per Chris: duplicate detection must still catch a transaction a
// treasurer already recorded manually through ANY screen (Bill Payment,
// Invoice Payment, Journal Voucher, Bank Adjustment) — not just one the
// AI itself extracted before. The existing check only ever looked at
// cbe_bank_transactions with a matching reference_no, which manual
// AP/AR/JV entries never populate at all (confirmed by code search: only
// 4 call sites ever insert into that table). This adds a second,
// broader check straight against the bank account's own GL postings
// (reusing the exact technique the Bank Reconciliation Module's
// "system side" matching already uses — see
// CbeAccountingService::unmatchedSystemEntries()) — same date, same
// amount, posted by ANY module. Because that alone can't always tell
// two genuinely separate same-day, same-amount transactions apart, a
// hit here never silently skips OR silently commits — it's held as
// POSSIBLE_DUPLICATE for Chris to confirm one way or the other.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'possible_duplicate_note')) {
                $table->string('possible_duplicate_note', 255)->nullable()->after('issued_receipt_no');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_ai_extracted_transactions', 'possible_duplicate_note')) {
                $table->dropColumn('possible_duplicate_note');
            }
        });
    }
};
