<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 29 Aug 2026 — Task #313 (GLADE Accounts Receivable screens). The
// centrex/laravel-accounting package already scopes acct_invoices,
// acct_bills, and acct_journal_entries to a per-tenant sbu_code, but
// acct_customers was missed (same gap the package itself later
// patched on vendors/bills via small follow-up migrations — this is
// that same kind of fix, kept in GeneralLink's own migrations folder
// instead of editing the vendor package again). Without this, one
// temple's Customer list would show every other temple's customers.
return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('accounting.table_prefix', 'acct_');

        Schema::table($prefix . 'customers', function (Blueprint $table) use ($prefix): void {
            if (! Schema::hasColumn($prefix . 'customers', 'sbu_code')) {
                $table->string('sbu_code', 50)->nullable()->after('modelable_id');
                $table->index('sbu_code', $prefix . 'customers_sbu_idx');
            }
        });
    }

    public function down(): void
    {
        $prefix = config('accounting.table_prefix', 'acct_');

        Schema::table($prefix . 'customers', function (Blueprint $table) use ($prefix): void {
            $table->dropIndex($prefix . 'customers_sbu_idx');
            $table->dropColumn('sbu_code');
        });
    }
};
