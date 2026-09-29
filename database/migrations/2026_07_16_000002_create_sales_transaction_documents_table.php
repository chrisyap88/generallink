<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 16 Jul 2026 — supports the Sales Transaction Maintenance module.
// Mirrors the existing claim_documents pattern, but document_type is
// deliberately broader — the proof an agent has on hand may be a
// receipt, a sales invoice, or the policy/cover note itself, not one
// fixed format. No OCR/field-extraction is ever run against these
// files — the agent types the real data into the form themselves; the
// photo is stored purely as supporting evidence for human review.
// file_hash lets us flag (not block) the same image being reused
// across multiple submissions, a weak-but-useful fraud signal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transaction_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('policy_id');
            $table->enum('document_type', ['RECEIPT', 'SALES_INVOICE', 'POLICY_DOCUMENT', 'OTHER'])->default('RECEIPT');
            $table->string('file_name', 255);
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('file_hash', 64)->nullable();
            $table->uuid('uploaded_by');
            $table->timestamps();

            $table->index('policy_id');
            $table->index('file_hash');

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->cascadeOnDelete();

            $table->foreign('uploaded_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transaction_documents');
    }
};
