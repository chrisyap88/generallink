<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
// Phase 3: Reconciliation Entry rebuild, spec sections 3-6.
//
// 1. reconciliation_no — a formatted document number (BR-YYYY-MMNNN),
//    same monthly-band pattern as every other document in this app
//    (see CbeAccountingService::nextDocumentNumber). Nullable/backfilled
//    as null for reconciliations already recorded before this feature —
//    they simply show no number, same convention used when Debit/Credit
//    Notes gained numbering (3 Sep 2026).
//
// 2. cbe_bank_reconciliation_matches — the real fix for 1:1 / 1:many /
//    many:1 matching (spec 3.4). One row per participant on either side
//    of a match; every row sharing the same match_group_id is one
//    match (their signed amounts must sum to zero, i.e. bank side total
//    = system side total). "System side" here is a GL journal LINE on
//    the bank account's own chart-of-accounts entry — not the older,
//    narrower cbe_transactions table, so a match can cover ANY GL
//    posting that hit this bank account (transfers, AP payments, AR
//    receipts, bill payments, opening balances, fixed asset disposal
//    proceeds — not just simple treasurer entries). journal_lines
//    itself gets no new column: whether a line is "already matched" is
//    always derived by checking this table, since journal_lines is
//    shared by every posting module in the app and must not carry
//    reconciliation-specific state.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'reconciliation_no')) {
                $table->string('reconciliation_no', 30)->nullable()->after('reconciliation_id');
            }
        });

        if (! Schema::hasTable('cbe_bank_reconciliation_matches')) {
            Schema::create('cbe_bank_reconciliation_matches', function (Blueprint $table) {
                $table->uuid('match_id')->primary();
                $table->uuid('reconciliation_id');
                $table->uuid('match_group_id');
                $table->string('side', 10); // BANK / SYSTEM
                $table->uuid('bank_transaction_id')->nullable();
                $table->uuid('journal_line_id')->nullable();
                $table->decimal('amount', 12, 2); // signed, denormalised for fast group-sum display
                $table->uuid('created_by');
                $table->timestamp('created_at')->nullable();

                $table->foreign('reconciliation_id')->references('reconciliation_id')->on('cbe_bank_reconciliations')->onDelete('cascade');
                $table->foreign('bank_transaction_id')->references('transaction_id')->on('cbe_bank_transactions')->onDelete('cascade');
                $table->foreign('journal_line_id')->references('line_id')->on('cbe_journal_lines')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index('match_group_id');
                $table->index(['reconciliation_id', 'side']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_reconciliation_matches');
        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_reconciliations', 'reconciliation_no')) {
                $table->dropColumn('reconciliation_no');
            }
        });
    }
};
