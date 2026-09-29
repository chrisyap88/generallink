<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394) — per Chris's Purchasing Management module
// spec, section 2: the Supplier / Creditor Master should carry a Default
// AP Control Account, Default Expense Account and Default Tax Account, so
// a Supplier Invoice can pre-fill its GL coding straight from the
// supplier record instead of the user picking accounts from scratch every
// time. All three are optional — if left blank, the user still picks the
// account manually on the invoice, exactly like today.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_suppliers', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_suppliers', 'default_ap_account_id')) {
                $table->uuid('default_ap_account_id')->nullable()->after('bank_account_holder');
                $table->foreign('default_ap_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'default_expense_account_id')) {
                $table->uuid('default_expense_account_id')->nullable()->after('default_ap_account_id');
                $table->foreign('default_expense_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_suppliers', 'default_tax_account_id')) {
                $table->uuid('default_tax_account_id')->nullable()->after('default_expense_account_id');
                $table->foreign('default_tax_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_suppliers', function (Blueprint $table) {
            foreach (['default_ap_account_id', 'default_expense_account_id', 'default_tax_account_id'] as $fk) {
                if (Schema::hasColumn('cbe_suppliers', $fk)) {
                    $table->dropForeign(['cbe_suppliers_' . $fk . '_foreign']);
                }
            }
            $table->dropColumn(['default_ap_account_id', 'default_expense_account_id', 'default_tax_account_id']);
        });
    }
};
