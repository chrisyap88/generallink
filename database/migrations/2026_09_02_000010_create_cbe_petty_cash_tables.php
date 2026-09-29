<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #337) — Petty Cash (imprest system), the last
// missing everyday cash-handling flow: small day-to-day spending
// (stationery, cleaning, transport) that a treasurer doesn't want to
// write a full Purchase Bill for every time. Modelled the same way Bank
// Accounts (Task #333) already are — each fund gets its own GL asset
// sub-account, so every peso/ringgit in the tin is still inside the
// formal double-entry books, not a shoebox outside them:
//
// - Establish/Top-up: Dr Petty Cash (this fund's GL account), Cr the
//   source bank account — money moves FROM the bank INTO the custodian's
//   hands, exactly like a Bank Transfer.
// - Voucher (a spend): Dr the expense category's own account, Cr Petty
//   Cash — the custodian records each small purchase as it happens; no
//   maker-checker step, this is intentionally lightweight (the imprest
//   ceiling itself is the control — a custodian can never be holding
//   more than float_amount was ever topped up).
//
// cbe_petty_cash_funds.gl_account_id is created via
// CbeAccountingService::createPettyCashGlLink() the same way
// createBankAccountGlLink() already works for bank accounts, using a
// reserved code block (1150-1199) so the two never collide — see
// nextPettyCashCode() / the narrowed nextBankAccountCode() upper bound.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_petty_cash_funds')) {
            Schema::create('cbe_petty_cash_funds', function (Blueprint $table) {
                $table->uuid('fund_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('fund_name', 150);
                $table->string('custodian_name', 150);
                $table->uuid('gl_account_id');
                $table->decimal('float_amount', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('gl_account_id')->references('account_id')->on('cbe_chart_of_accounts');
                $table->index('cbe_node_id');
            });
        }

        if (! Schema::hasTable('cbe_petty_cash_vouchers')) {
            Schema::create('cbe_petty_cash_vouchers', function (Blueprint $table) {
                $table->uuid('voucher_id')->primary();
                $table->uuid('fund_id');
                $table->uuid('cbe_node_id');
                $table->string('doc_ref_no', 30)->nullable();
                $table->date('voucher_date');
                $table->string('payee', 150);
                $table->string('description', 255);
                $table->uuid('category_id');
                $table->decimal('amount', 12, 2);
                $table->uuid('journal_id')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('fund_id')->references('fund_id')->on('cbe_petty_cash_funds')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories');
                $table->foreign('journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
                $table->index(['fund_id', 'voucher_date']);
            });
        }

        if (! Schema::hasTable('cbe_petty_cash_topups')) {
            Schema::create('cbe_petty_cash_topups', function (Blueprint $table) {
                $table->uuid('topup_id')->primary();
                $table->uuid('fund_id');
                $table->uuid('cbe_node_id');
                $table->string('doc_ref_no', 30)->nullable();
                $table->date('topup_date');
                $table->decimal('amount', 12, 2);
                $table->uuid('bank_account_id')->nullable();
                $table->string('notes', 255)->nullable();
                $table->uuid('journal_id')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('fund_id')->references('fund_id')->on('cbe_petty_cash_funds')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
                $table->foreign('journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
                $table->index(['fund_id', 'topup_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_petty_cash_topups');
        Schema::dropIfExists('cbe_petty_cash_vouchers');
        Schema::dropIfExists('cbe_petty_cash_funds');
    }
};
