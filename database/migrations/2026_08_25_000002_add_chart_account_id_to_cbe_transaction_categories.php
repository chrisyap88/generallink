<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — links every INCOME/EXPENSE category a treasurer
// already picks from (Finance > Transactions) to a real General Ledger
// account, so simple day-to-day entry ("RM500, Donations") keeps
// working exactly as before while silently posting proper double-entry
// underneath. Backfilled by the `cbe:setup-chart-of-accounts` command.
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cbe_transaction_categories') && ! Schema::hasColumn('cbe_transaction_categories', 'chart_account_id')) {
            Schema::table('cbe_transaction_categories', function (Blueprint $table) {
                $table->uuid('chart_account_id')->nullable()->after('type');
                $table->foreign('chart_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cbe_transaction_categories') && Schema::hasColumn('cbe_transaction_categories', 'chart_account_id')) {
            Schema::table('cbe_transaction_categories', function (Blueprint $table) {
                $table->dropForeign(['chart_account_id']);
                $table->dropColumn('chart_account_id');
            });
        }
    }
};
