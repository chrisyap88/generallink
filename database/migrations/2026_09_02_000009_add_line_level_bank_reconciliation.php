<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #336) — Bank Reconciliation redesign. The
// previous MVP (30 Aug 2026, Task #319) only compared two totals
// (opening/ending balance vs. the ledger's computed Cash balance) —
// no actual line-by-line matching. This adds:
// 1. bank_account_id on cbe_bank_reconciliations, so reconciliation is
//    scoped to ONE bank account's own GL sub-account — the old
//    node-wide cashBalanceAsOf() ignored per-account sub-accounts
//    entirely (see CbeAccountingService::bankAccountGlAccountId,
//    added 27 Aug 2026, Task #333), which was already a latent bug
//    for any node with more than one bank account. Nullable, for
//    backward compatibility with reconciliations recorded before this
//    feature existed.
// 2. cbe_bank_reconciliation_lines — the treasurer keys in each line
//    off the paper/PDF statement; the system auto-matches against
//    cbe_transactions by date-window + amount, and any leftover lines
//    are matched manually or marked OUTSTANDING (bank activity not
//    yet entered into the books — the most common real reconciling
//    item, e.g. bank fees/interest).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('cbe_node_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
        });

        if (! Schema::hasTable('cbe_bank_reconciliation_lines')) {
            Schema::create('cbe_bank_reconciliation_lines', function (Blueprint $table) {
                $table->uuid('line_id')->primary();
                $table->uuid('reconciliation_id');
                $table->uuid('cbe_node_id');
                $table->date('line_date');
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2); // positive = deposit/credit, negative = withdrawal/debit
                $table->string('status', 20)->default('UNMATCHED'); // UNMATCHED / MATCHED / OUTSTANDING
                $table->uuid('matched_transaction_id')->nullable();
                $table->timestamps();

                $table->foreign('reconciliation_id')->references('reconciliation_id')->on('cbe_bank_reconciliations')->onDelete('cascade');
                $table->foreign('matched_transaction_id')->references('transaction_id')->on('cbe_transactions')->onDelete('set null');
                $table->index('reconciliation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_reconciliation_lines');
        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_reconciliations', 'bank_account_id')) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            }
        });
    }
};
