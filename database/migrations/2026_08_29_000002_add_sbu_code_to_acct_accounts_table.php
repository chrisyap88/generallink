<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Aug 2026 — Task #313/#316 (Bank Reconciliation). The engine's
// Chart of Accounts (acct_accounts) is global/shared by design — there
// is only ONE "1100 Bank" account for the whole system. That's fine for
// AR/Fixed Assets (they tag the *transaction* with sbu_code), but Bank
// Reconciliation is scoped by account_id, not by transaction — so
// without each temple having its OWN bank account row, reconciling one
// temple's statement would mix in every other temple's bank activity.
// This column lets CbeAccountingController::ensureNodeBankAccount()
// auto-provision one dedicated bank sub-account per temple (same
// pattern the package itself already uses for Fixed Assets' per-asset
// sub-accounts), keeping each temple's reconciliation genuinely its own.
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('accounting.table_prefix', 'acct_');

        Schema::table($prefix . 'accounts', function (Blueprint $table) use ($prefix): void {
            if (! Schema::hasColumn($prefix . 'accounts', 'sbu_code')) {
                $table->string('sbu_code', 50)->nullable()->after('level');
                $table->index('sbu_code', $prefix . 'accounts_sbu_idx');
            }
        });
    }

    public function down(): void
    {
        $prefix = config('accounting.table_prefix', 'acct_');

        Schema::table($prefix . 'accounts', function (Blueprint $table) use ($prefix): void {
            $table->dropIndex($prefix . 'accounts_sbu_idx');
            $table->dropColumn('sbu_code');
        });
    }
};
