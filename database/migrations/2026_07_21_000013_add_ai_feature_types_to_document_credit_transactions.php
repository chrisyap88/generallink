<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// NEW 22 Jul 2026 — per Chris: Help Desk message translation and the
// "Fix Wording" rephrase suggestion both call the Claude API, same as
// document extraction, so both draw from the same Document Credit
// wallet with the same up-front "this will cost RM X, proceed?"
// confirmation the agent already sees before a top-up or transfer.
// Two new ledger types record these deductions distinctly from a
// document DEDUCTION so the ledger stays self-explanatory.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE document_credit_transactions MODIFY type ENUM('TOPUP','DEDUCTION','TRANSFER_OUT','TRANSFER_IN','TRANSLATION','REPHRASE') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE document_credit_transactions MODIFY type ENUM('TOPUP','DEDUCTION','TRANSFER_OUT','TRANSFER_IN') NOT NULL");
    }
};
