<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — Document Credit Wallet, per Chris. Every AI
// document-extraction call (ClaudeDocumentExtractionService) costs
// real money via the Anthropic API. To stop repeated/careless use
// from costing Chris money indefinitely, each agent now has their own
// prepaid balance, in Ringgit, kept deliberately separate from
// commission_balance (an agent's real earnings) so the two are never
// confused. A flat amount (set by Admin, see system_settings key
// 'document_credit_deduction_amount') is deducted from this balance
// every time an extraction succeeds; extraction is blocked outright
// if the balance is insufficient to cover that amount. Agents top up
// by paying Chris directly (bank-in slip) and Admin approves the
// top-up, crediting this balance — see
// document_credit_topup_requests and document_credit_transactions.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->decimal('document_credit_balance', 10, 2)->default(0)->after('commission_balance');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('document_credit_balance');
        });
    }
};
