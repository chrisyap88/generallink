<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Aug 2026 — per Chris: "for pending approval on due diligent
// report add the 9th folder call Ctos report, same as bankruptcy, choose
// file method." CTOS (Malaysia's main credit reporting agency) has no
// automated GeneralLink connection either, so this follows the exact
// same honest pattern as the bankruptcy check — Admin uploads whatever
// CTOS report(s) they obtained, Claude reads each one for a genuine
// finding, never a guess.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_due_diligence_assessments', function (Blueprint $table) {
            $table->string('ctos_status', 20)->nullable()->after('bankruptcy_note');
            $table->text('ctos_note')->nullable()->after('ctos_status');
        });

        Schema::create('vendor_ctos_documents', function (Blueprint $table) {
            $table->uuid('document_id')->primary();
            $table->uuid('vendor_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->uuid('uploaded_by_admin_id')->nullable();
            $table->string('ai_entity_name')->nullable();
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
        Schema::dropIfExists('vendor_ctos_documents');
        Schema::table('vendor_due_diligence_assessments', function (Blueprint $table) {
            $table->dropColumn(['ctos_status', 'ctos_note']);
        });
    }
};
