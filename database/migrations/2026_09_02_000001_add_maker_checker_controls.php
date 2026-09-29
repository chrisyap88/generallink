<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #334) — Maker-Checker approval. Per Chris's
// treasurer/financial-controller review, this was the #1 governance gap:
// one person could prepare AND release a payment with no second approval.
// This migration adds the approval workflow to the three ways money can
// leave or move inside the ledger — Bill Payments (AP disbursement),
// Bank Transfers (between own accounts), and Journal Vouchers (manual
// postings, which can also move cash). Money coming IN (invoices,
// donations) is left as-is — no approval risk on receipts.
//
// Design: per-node settings row controls whether the workflow is even
// switched on (default OFF so existing single-officer temples are not
// disrupted the moment this ships) and the RM threshold above which a
// second person must approve before the entry posts to the ledger.
// Existing rows default to 'APPROVED' so nothing already posted is
// affected retroactively.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_approval_settings', function (Blueprint $table) {
            $table->uuid('setting_id')->primary();
            $table->uuid('cbe_node_id')->unique();
            $table->boolean('maker_checker_enabled')->default(false);
            $table->decimal('threshold_amount', 12, 2)->default(0);
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('cbe_bill_payments', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('APPROVED')->after('recorded_by');
            $table->uuid('approved_by')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->string('rejection_reason', 255)->nullable()->after('approved_at');
        });

        Schema::table('cbe_bank_transfers', function (Blueprint $table) {
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('APPROVED')->after('prepared_by');
            $table->string('rejection_reason', 255)->nullable()->after('approved_at');
        });

        // Staging table for manual Journal Vouchers awaiting approval.
        // Unlike bill payments/transfers (which already have their own
        // row before posting), a JV today posts straight into
        // cbe_journal_entries with no draft state — this table gives it
        // one, ONLY when approval is required for that entry.
        Schema::create('cbe_journal_voucher_drafts', function (Blueprint $table) {
            $table->uuid('draft_id')->primary();
            $table->uuid('cbe_node_id');
            $table->date('entry_date');
            $table->string('description', 255);
            $table->json('lines');
            $table->decimal('total_amount', 12, 2);
            $table->uuid('prepared_by');
            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->uuid('posted_journal_id')->nullable();
            $table->timestamps();

            $table->index('cbe_node_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_journal_voucher_drafts');
        Schema::table('cbe_bank_transfers', function (Blueprint $table) {
            $table->dropColumn(['status', 'rejection_reason']);
        });
        Schema::table('cbe_bill_payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'approved_by', 'approved_at', 'rejection_reason']);
        });
        Schema::dropIfExists('cbe_approval_settings');
    }
};
