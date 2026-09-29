<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — permanent ledger of every top-up and deduction
// against an agent's document_credit_balance (agents table). Every
// row records the balance immediately after that entry, so the full
// history is auditable without recalculating anything — same idea as
// commission_transactions being the audit trail behind
// agents.commission_balance.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_credit_transactions', function (Blueprint $table) {
            $table->uuid('transaction_id')->primary();
            $table->uuid('agent_id');

            $table->enum('type', ['TOPUP', 'DEDUCTION']);
            $table->decimal('amount', 10, 2); // always positive; type says direction
            $table->decimal('balance_after', 10, 2);

            // DEDUCTION rows: which document/extraction this paid for.
            // TOPUP rows: which top-up request this came from.
            $table->uuid('reference_document_id')->nullable();
            $table->uuid('topup_request_id')->nullable();

            $table->text('note')->nullable();
            // 'SYSTEM' for automatic deductions, or the Admin agent_id
            // who approved a top-up.
            $table->string('created_by', 100)->nullable();

            $table->timestamps();

            $table->index(['agent_id', 'created_at']);
            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_credit_transactions');
    }
};
