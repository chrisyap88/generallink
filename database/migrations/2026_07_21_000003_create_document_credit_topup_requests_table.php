<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Jul 2026 — an agent requests a top-up by paying Chris
// directly (bank transfer) and attaching a photo/PDF of the bank-in
// slip as proof. Admin reviews the slip against the amount requested
// and approves (crediting agents.document_credit_balance + writing a
// TOPUP row to document_credit_transactions) or rejects it. Mirrors
// the existing renewal_quotation_requests / withdrawal_requests
// approval pattern already used elsewhere in this app.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_credit_topup_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->uuid('agent_id');

            $table->decimal('amount_requested', 10, 2);
            $table->string('bank_slip_file_name');
            $table->string('bank_slip_file_path');

            $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED'])->default('PENDING');
            $table->text('admin_note')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            // FIXED 21 Jul 2026 — ->useCurrent() added: this agent's
            // MySQL strict mode rejected a second TIMESTAMP column with
            // no default ("Invalid default value for 'requested_at'").
            // The controller still always passes an explicit value on
            // insert, so this default is just a safety net, never
            // actually relied on.
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_credit_topup_requests');
    }
};
