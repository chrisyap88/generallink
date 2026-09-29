<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "if cannot check then you allow the admin
// upload the report separately, and can upload multiple file because
// many director and many shareholder." GeneralLink has no connection to
// Jabatan Insolvensi Malaysia (JIM) or any bankruptcy registry, so this
// check can never run automatically (see VendorDueDiligenceService::
// runBankruptcyCheck). Instead Admin uploads whatever search report(s)
// they obtained themselves — one row per uploaded file, one file per
// director/shareholder — and Claude reads each one (same document-reading
// capability used for SSM document identity cross-check) to produce a
// genuine finding, never a guess.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_bankruptcy_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('vendor_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->uuid('uploaded_by_admin_id')->nullable();
            $table->string('ai_person_name')->nullable();
            $table->string('ai_conclusion', 20)->nullable();
            $table->text('ai_finding')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->onDelete('cascade');
            $table->foreign('uploaded_by_admin_id')->references('agent_id')->on('agents')->onDelete('set null');
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_bankruptcy_documents');
    }
};
