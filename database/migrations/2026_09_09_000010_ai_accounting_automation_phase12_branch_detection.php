<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
// Automation Management Module, Phase 12: Branch/Bank-Account
// Auto-Detection.
//
// Per Chris: the bank statement itself already prints which bank
// account it belongs to — the uploader should never have to manually
// pre-select the bank account before the AI even looks at the file.
// Moving bank_account_id from the BATCH (one choice for the whole
// upload) down to the DOCUMENT (one per PDF) lets each statement in a
// mixed batch resolve to its own correct account automatically, with
// the batch-level dropdown kept only as an optional fallback for a
// statement whose account number can't be confidently identified.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'bank_account_id')) {
                $table->uuid('bank_account_id')->nullable()->after('cbe_node_id');
                $table->foreign('bank_account_id', 'cbe_ai_doc_bank_fk')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            }
            if (! Schema::hasColumn('cbe_ai_statement_documents', 'branch_detection_note')) {
                $table->string('branch_detection_note', 255)->nullable()->after('bank_account_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_statement_documents', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_ai_statement_documents', 'bank_account_id')) {
                $table->dropForeign('cbe_ai_doc_bank_fk');
                $table->dropColumn(['bank_account_id', 'branch_detection_note']);
            }
        });
    }
};
