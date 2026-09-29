<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
// Phase 7, spec sections 20 (Approval Control) and 24 (Audit Trail).
//
// 1. Reconciliation Status workflow gains an approval step: when the
//    node's Maker-Checker setting is enabled, completing a
//    reconciliation moves it to PENDING_APPROVAL (not straight to
//    COMPLETED) until a second officer (or an Admin) approves it —
//    same canApprove()/self-approval-block pattern already used for
//    Bill Payments and Bank Transfers. completed_by/approved_by/
//    approved_at/rejection_reason are all nullable so every
//    reconciliation recorded before this feature existed is
//    unaffected and simply shows no approval trail.
//
// 2. cbe_bank_reconciliation_audit_log — exact same append-only
//    event-log pattern as cbe_fixed_asset_audit_log /
//    cbe_purchasing_audit_log (never updated or deleted).
return new class extends Migration
{
    public function up(): void
    {
        // Widen the status enum first — DRAFT/COMPLETED only, until now.
        // Raw ALTER because Laravel's fluent column change needs
        // doctrine/dbal, which this app does not have installed (same
        // approach used elsewhere in this codebase for enum changes).
        DB::statement("ALTER TABLE cbe_bank_reconciliations MODIFY status ENUM('DRAFT','PENDING_APPROVAL','COMPLETED') NOT NULL DEFAULT 'DRAFT'");

        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'completed_by')) {
                $table->uuid('completed_by')->nullable()->after('recorded_by');
            }
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'approved_by')) {
                $table->uuid('approved_by')->nullable()->after('completed_by');
            }
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('cbe_bank_reconciliations', 'rejection_reason')) {
                $table->string('rejection_reason', 255)->nullable()->after('approved_at');
            }
        });

        if (! Schema::hasTable('cbe_bank_reconciliation_audit_log')) {
            Schema::create('cbe_bank_reconciliation_audit_log', function (Blueprint $table) {
                $table->uuid('log_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('reconciliation_id')->nullable();
                $table->string('reconciliation_no', 30)->nullable();
                $table->string('action', 30); // CREATED / TRANSACTION_ADDED / AUTO_MATCHED / MANUALLY_MATCHED / UNMATCHED / ADJUSTMENT_ADDED / COMPLETED / SUBMITTED_FOR_APPROVAL / APPROVED / REJECTED / REOPENED
                $table->uuid('actor_id');
                $table->string('notes', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('actor_id')->references('agent_id')->on('agents')->onDelete('restrict');
                // Explicit short names — the auto-generated default
                // ("cbe_bank_reconciliation_audit_log_cbe_node_id_..._index")
                // is 69+ chars, over MySQL's 64-char identifier limit.
                $table->index(['cbe_node_id', 'reconciliation_id'], 'cbe_br_audit_node_recon_idx');
                $table->index(['cbe_node_id', 'created_at'], 'cbe_br_audit_node_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_reconciliation_audit_log');
        DB::table('cbe_bank_reconciliations')->where('status', 'PENDING_APPROVAL')->update(['status' => 'DRAFT']);
        Schema::table('cbe_bank_reconciliations', function (Blueprint $table) {
            foreach (['completed_by', 'approved_by', 'approved_at', 'rejection_reason'] as $col) {
                if (Schema::hasColumn('cbe_bank_reconciliations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        DB::statement("ALTER TABLE cbe_bank_reconciliations MODIFY status ENUM('DRAFT','COMPLETED') NOT NULL DEFAULT 'DRAFT'");
    }
};
