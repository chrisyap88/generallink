<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 7: Multi-Year Sequential Processing.
//
// Scoped with Chris before building (AskUserQuestion): the AI-specific
// slice only — automatic continuity checks when statements are
// uploaded, so FY2024 -> FY2025 -> FY2026 sequential processing can be
// trusted. Accruals/prepayments/suspense-account checklist items were
// explicitly deferred as a separate, later request — that's a general
// ledger year-end feature unrelated to bank-statement automation, and
// this app already has a working Year-End Closing screen (AGM/ROS
// tracking + report pack) and Period Control (month-by-month close)
// that this module doesn't need to duplicate or replace.
//
// Two checks, run once per document right after parsing:
//   1. Opening balance vs books — the statement's own detected opening
//      balance is compared against what the GL actually shows for that
//      bank account's own GL account as of the day before the
//      statement period starts (opening_balance_expected). A variance
//      beyond a small tolerance usually means a missing prior
//      statement, an unposted transaction, or the wrong bank account
//      was picked for this batch.
//   2. Sequence gap — compared against the most recent PRIOR statement
//      already uploaded for the same bank account; a gap of more than a
//      few days between the two periods likely means a month's
//      statement is missing.
//
// Both are advisory, never blocking — Chris still decides what to do;
// nothing here stops a document from being reviewed or committed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'opening_balance_expected')) {
                $table->decimal('opening_balance_expected', 14, 2)->nullable()->after('detected_opening_balance');
            }
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'opening_balance_variance')) {
                $table->decimal('opening_balance_variance', 14, 2)->nullable()->after('opening_balance_expected');
            }
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'continuity_note')) {
                $table->string('continuity_note', 500)->nullable()->after('opening_balance_variance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            $table->dropColumn(['opening_balance_expected', 'opening_balance_variance', 'continuity_note']);
        });
    }
};
