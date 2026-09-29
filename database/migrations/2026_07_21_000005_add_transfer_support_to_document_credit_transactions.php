<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — per Chris: GL/TL/Introducer can transfer part of
// their own Document Credit balance to a specific downline agent
// (their own choice of who + how much). This adds two new ledger
// types (TRANSFER_OUT for the sender's row, TRANSFER_IN for the
// receiver's row) plus a related_agent_id column so the ledger can
// show "Transferred to X" / "Received from Y" without parsing the
// note text. Existing TOPUP/DEDUCTION rows are untouched.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE document_credit_transactions MODIFY type ENUM('TOPUP','DEDUCTION','TRANSFER_OUT','TRANSFER_IN') NOT NULL");

        if (!Schema::hasColumn('document_credit_transactions', 'related_agent_id')) {
            Schema::table('document_credit_transactions', function ($table) {
                $table->uuid('related_agent_id')->nullable()->after('agent_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('document_credit_transactions', 'related_agent_id')) {
            Schema::table('document_credit_transactions', function ($table) {
                $table->dropColumn('related_agent_id');
            });
        }

        DB::statement("ALTER TABLE document_credit_transactions MODIFY type ENUM('TOPUP','DEDUCTION') NOT NULL");
    }
};
