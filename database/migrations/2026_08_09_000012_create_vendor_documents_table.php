<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris's spec: "Show verification status for each
// file" + "documents required based on business entity type". Replaces
// the old fixed ssm_document_path / company_profile_document_path pair
// (still left in place on `vendors` for existing records) with one row
// per uploaded document, so the checklist can vary by entity type and
// each file can be verified/rejected individually by Admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('vendor_document_id')->primary();
            $table->uuid('vendor_id');
            $table->string('document_key', 50);   // e.g. constitutional_doc, directors_info
            $table->string('document_label', 255); // human label at time of upload (kept even if checklist wording changes later)
            $table->string('file_path');
            $table->string('file_name');
            $table->string('verification_status', 20)->default('PENDING'); // PENDING, VERIFIED, REJECTED
            $table->string('verification_note', 255)->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('verified_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
    }
};
