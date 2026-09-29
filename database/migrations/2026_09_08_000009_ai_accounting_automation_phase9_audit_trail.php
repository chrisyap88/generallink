<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 9: Complete Audit Trail + Control Against
// Fabrication.
//
// Mirrors the exact append-only action-log pattern already used by
// cbe_purchasing_audit_log / cbe_fixed_asset_audit_log /
// cbe_bank_reconciliation_audit_log (one row per lifecycle action,
// short free-text "notes" carries the human-readable detail, no
// before/after JSON diffing). Nothing here is a new design — it is
// the same table shape, just scoped to this module's own event
// vocabulary (UPLOADED/PARSED/PARSE_FAILED/CONTINUITY_FLAGGED at
// document level; COMMITTED/REJECTED/DUPLICATE_SKIPPED at line
// level; THRESHOLD_CHANGED/RULE_DEACTIVATED/RULE_REACTIVATED for the
// Phase 8 controls).
//
// document_id / extraction_id are both nullable and both carry their
// own FK — a single row only ever populates the one relevant to its
// event_type, so you can always trace a specific extracted line's
// full life story (document.WHERE extraction_id = X) or a whole
// statement's life story (document.WHERE document_id = X) without
// a generic entity_type/entity_id discriminator, consistent with the
// three existing modules never using one either.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ai_audit_log')) {
            Schema::create('cbe_ai_audit_log', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('document_id')->nullable();
                $table->uuid('extraction_id')->nullable();
                $table->string('event_type', 30);
                $table->uuid('actor_id')->nullable();
                $table->string('notes', 500)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('cbe_node_id', 'cbe_ai_audit_node_fk')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('document_id', 'cbe_ai_audit_document_fk')->references('document_id')->on('cbe_ai_statement_documents')->onDelete('set null');
                $table->foreign('extraction_id', 'cbe_ai_audit_extraction_fk')->references('extraction_id')->on('cbe_ai_extracted_transactions')->onDelete('set null');
                $table->foreign('actor_id', 'cbe_ai_audit_actor_fk')->references('agent_id')->on('agents')->onDelete('set null');

                $table->index(['cbe_node_id', 'created_at'], 'cbe_ai_audit_node_created_idx');
                $table->index('document_id', 'cbe_ai_audit_doc_idx');
                $table->index('extraction_id', 'cbe_ai_audit_extraction_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ai_audit_log');
    }
};
