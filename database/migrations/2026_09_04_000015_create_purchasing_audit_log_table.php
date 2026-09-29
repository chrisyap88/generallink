<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #394 gap-fix) — Purchasing Audit Trail (spec
// section 26). Every purchasing document already records who created it
// and, where relevant, who approved/rejected it (single created_by /
// approved_by columns) — but that only ever shows the LATEST action, not
// a full history, and there was nowhere to see everything that happened
// across a document's life (or across the whole module) in one place.
// This is a lightweight, append-only event log: one row per action, never
// updated or deleted, written alongside (not instead of) each document's
// own status columns.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_purchasing_audit_log')) {
            Schema::create('cbe_purchasing_audit_log', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('doc_type', 10); // PR / RFQ / PO / GRN / PRN / BILL
                $table->uuid('doc_id');
                $table->string('doc_ref_no', 40)->nullable();
                $table->string('action', 30); // CREATED / APPROVED / REJECTED / CONVERTED / CANCELLED / RECEIVED / MATCHED / SELECTED_SUPPLIER / QUOTE_SAVED
                $table->uuid('actor_id');
                $table->string('notes', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('actor_id')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'doc_type', 'doc_id']);
                $table->index(['cbe_node_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchasing_audit_log');
    }
};
