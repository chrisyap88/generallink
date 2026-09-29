<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 1 Sep 2026 (Task #333) — per Chris's Financial Module Phase 1
// redesign: cbe_bank_accounts (27 Aug 2026) was built as a reference
// record for filing bank statements against — it was never linked to
// the General Ledger, so every temple only ever had ONE real ledger
// cash account no matter how many bank accounts they actually hold.
// This upgrades the SAME table (not a duplicate) into a real ledger
// account master: each row gets its own Chart of Accounts entry, an
// account type, and an opening balance, so Daily Transactions, Bill
// Payments, Invoice Payments, and Bank Reconciliation can all specify
// WHICH account money moved through.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_accounts', 'account_code')) {
                $table->string('account_code', 20)->nullable()->after('cbe_node_id');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'account_type')) {
                $table->enum('account_type', ['CASH', 'BANK_CURRENT', 'BANK_SAVINGS', 'FIXED_DEPOSIT', 'PETTY_CASH'])
                    ->default('BANK_CURRENT')->after('account_number');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'gl_account_id')) {
                $table->uuid('gl_account_id')->nullable()->after('account_type');
                $table->foreign('gl_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'opening_balance')) {
                $table->decimal('opening_balance', 14, 2)->default(0)->after('gl_account_id');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'opening_balance_date')) {
                $table->date('opening_balance_date')->nullable()->after('opening_balance');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'signatories')) {
                $table->string('signatories', 255)->nullable()->after('opening_balance_date');
            }
        });

        // Which account the money actually moved through — nullable so
        // existing rows (posted before this upgrade) stay valid; the
        // posting logic falls back to each node's original single Cash/
        // Bank account when this is null, so nothing already posted
        // changes meaning.
        Schema::table('cbe_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_transactions', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('cbe_node_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
        });
        Schema::table('cbe_bill_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bill_payments', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('bill_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
        });
        Schema::table('cbe_invoice_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_invoice_payments', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('invoice_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
        });

        if (! Schema::hasTable('cbe_bank_transfers')) {
            Schema::create('cbe_bank_transfers', function (Blueprint $table) {
                $table->uuid('transfer_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('from_account_id');
                $table->uuid('to_account_id');
                $table->date('transfer_date');
                $table->decimal('amount', 14, 2);
                $table->string('reference_no', 60)->nullable();
                $table->string('purpose', 255)->nullable();
                $table->uuid('prepared_by');
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('from_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('restrict');
                $table->foreign('to_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('restrict');
                $table->foreign('prepared_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->foreign('approved_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_transfers');

        foreach (['cbe_transactions', 'cbe_bill_payments', 'cbe_invoice_payments'] as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'bank_account_id')) {
                    $table->dropForeign(['bank_account_id']);
                    $table->dropColumn('bank_account_id');
                }
            });
        }

        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_bank_accounts', 'gl_account_id')) {
                $table->dropForeign(['gl_account_id']);
            }
            $table->dropColumn(['account_code', 'account_type', 'gl_account_id', 'opening_balance', 'opening_balance_date', 'signatories']);
        });
    }
};
