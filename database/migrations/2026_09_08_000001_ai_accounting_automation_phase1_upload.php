<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 1: Document Upload + PDF Statement
// Extraction, per Chris's 24-section written specification.
//
// Scope confirmed with Chris before building: rule-based classification
// (no external AI API), digital-text PDF bank statements only (no OCR
// service — a scanned/image PDF with no extractable text is flagged
// PARSE_FAILED with a clear message, not silently skipped), and no live
// internet lookups to verify suppliers/customers (drafts are created
// from statement data and flagged for the user to confirm instead).
//
// Architecture: this module does NOT duplicate the Bank Reconciliation
// Module's transaction storage. cbe_ai_statement_batches/_documents
// capture the raw upload + extraction lineage (source PDF, page number,
// extraction confidence) that spec sections 4 and 20 require for
// traceability; cbe_ai_extracted_transactions is the staging table a
// human reviews before committing. Committing writes straight into the
// EXISTING cbe_bank_transactions table (source='AI_EXTRACT', a new
// value on that column — it's a plain string(20), not an enum, so no
// change needed there), which means every already-built Bank
// Reconciliation Module feature — matching, GL drill-down, duplicate
// detection, reports — picks these transactions up automatically with
// no extra wiring, the same reasoning already used for AP/AR/Fixed
// Asset postings in that module's Phase 3.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ai_statement_batches')) {
            Schema::create('cbe_ai_statement_batches', function (Blueprint $table) {
                $table->uuid('batch_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('label', 150); // e.g. "FY2024 Bank Statements"
                $table->uuid('bank_account_id')->nullable(); // set if the whole batch is one known account
                $table->string('status', 20)->default('UPLOADED'); // UPLOADED / REVIEWED / COMMITTED
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status'], 'cbe_ai_batches_node_status_idx');
            });
        }

        if (! Schema::hasTable('cbe_ai_statement_documents')) {
            Schema::create('cbe_ai_statement_documents', function (Blueprint $table) {
                $table->uuid('document_id')->primary();
                $table->uuid('batch_id');
                $table->uuid('cbe_node_id');
                $table->string('original_filename', 255);
                $table->string('stored_path', 255);
                $table->unsignedInteger('page_count')->nullable();
                $table->string('detected_bank_name', 100)->nullable();
                $table->string('detected_account_number', 50)->nullable();
                $table->date('statement_period_from')->nullable();
                $table->date('statement_period_to')->nullable();
                $table->decimal('detected_opening_balance', 14, 2)->nullable();
                $table->decimal('detected_closing_balance', 14, 2)->nullable();
                $table->unsignedInteger('extracted_transaction_count')->default(0);
                $table->string('status', 20)->default('UPLOADED'); // UPLOADED / PARSED / PARSE_FAILED / CONFIRMED
                $table->string('parse_error_note', 255)->nullable();
                $table->timestamps();

                $table->foreign('batch_id', 'cbe_ai_docs_batch_fk')->references('batch_id')->on('cbe_ai_statement_batches')->onDelete('cascade');
                $table->foreign('cbe_node_id', 'cbe_ai_docs_node_fk')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->index(['cbe_node_id', 'batch_id'], 'cbe_ai_docs_node_batch_idx');
            });
        }

        if (! Schema::hasTable('cbe_ai_extracted_transactions')) {
            Schema::create('cbe_ai_extracted_transactions', function (Blueprint $table) {
                $table->uuid('extraction_id')->primary();
                $table->uuid('document_id');
                $table->uuid('cbe_node_id');
                $table->unsignedInteger('line_no');
                $table->unsignedInteger('page_number')->nullable();
                $table->date('transaction_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->decimal('debit', 14, 2)->nullable();
                $table->decimal('credit', 14, 2)->nullable();
                $table->decimal('running_balance', 14, 2)->nullable();
                $table->text('raw_line_text')->nullable(); // exact extracted line, for the audit trail (spec section 20)
                $table->unsignedTinyInteger('extraction_confidence')->default(0); // 0-100, rule-based: how cleanly this line matched the expected pattern
                $table->string('status', 20)->default('PENDING'); // PENDING / COMMITTED / REJECTED / DUPLICATE
                $table->uuid('committed_bank_transaction_id')->nullable();
                $table->timestamps();

                $table->foreign('document_id', 'cbe_ai_extract_doc_fk')->references('document_id')->on('cbe_ai_statement_documents')->onDelete('cascade');
                $table->foreign('cbe_node_id', 'cbe_ai_extract_node_fk')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('committed_bank_transaction_id', 'cbe_ai_extract_committed_txn_fk')->references('transaction_id')->on('cbe_bank_transactions')->onDelete('set null');
                $table->index(['cbe_node_id', 'document_id'], 'cbe_ai_extract_node_doc_idx');
                $table->index(['document_id', 'status'], 'cbe_ai_extract_doc_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ai_extracted_transactions');
        Schema::dropIfExists('cbe_ai_statement_documents');
        Schema::dropIfExists('cbe_ai_statement_batches');
    }
};
