<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Jul 2026 — every quotation PDF ever uploaded against a
// renewal request, kept forever (never overwritten/deleted), so a
// customer's record shows a full quotation history, not just the most
// recent one. Mirrors the existing sales_transaction_documents pattern
// (file stored on disk + one metadata row per upload).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('renewal_quotation_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('request_id');

            $table->string('file_name');
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');

            $table->uuid('uploaded_by');
            $table->timestamps();

            $table->index('request_id');

            $table->foreign('request_id')->references('request_id')->on('renewal_quotation_requests')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('agent_id')->on('agents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('renewal_quotation_documents');
    }
};
