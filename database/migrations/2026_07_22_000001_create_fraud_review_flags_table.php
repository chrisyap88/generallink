<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Jul 2026 — per Chris: Universal Document Fraud Detection,
// scoped-down version (business-rule checks only — duplicate file
// hashes, duplicate reference numbers, math/date anomalies — NOT the
// AI tamper-detection or merchant-fingerprinting parts, which were
// deliberately deferred as unreliable/oversized for this project; see
// FraudDetectionService for the full reasoning).
//
// Deliberately a NEW, dedicated table rather than reusing
// pending_approvals — pending_approvals' model is "approve this and
// an action executes" (role change, withdrawal, etc.); a fraud flag
// has no action to execute on approval, just an investigate-then-clear
// workflow, so forcing it into that table would mean fighting its
// action_type/executeAction() coupling for no benefit.
//
// flaggable_type/flaggable_id is a lightweight polymorphic reference
// (plain strings, not Eloquent morphs, matching this codebase's
// raw-DB::table() convention) — e.g. flaggable_type='SALES_TRANSACTION',
// flaggable_id=sales_transactions.transaction_id. This lets the SAME
// review queue cover Sales Transactions, Document Credit top-ups, and
// Renewal Quotation documents without a separate table per type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_review_flags', function (Blueprint $table) {
            $table->uuid('flag_id')->primary();
            $table->string('flaggable_type', 40);
            $table->uuid('flaggable_id');
            $table->uuid('agent_id'); // who submitted the flagged item — for context/notification, not blame
            $table->unsignedInteger('risk_score')->default(0);
            $table->enum('risk_level', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])->default('LOW');
            $table->json('anomalies'); // [{code, label, detail}, ...] — always at least one, or the row wouldn't exist
            $table->enum('status', ['OPEN', 'UNDER_REVIEW', 'CLEARED', 'CONFIRMED_FRAUD'])->default('OPEN');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['flaggable_type', 'flaggable_id']);
            $table->index(['status', 'risk_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_review_flags');
    }
};
