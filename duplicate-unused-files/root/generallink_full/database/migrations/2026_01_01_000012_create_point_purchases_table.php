<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_purchases', function (Blueprint $table) {
            $table->uuid('purchase_id')->primary();

            // Who is buying points
            $table->uuid('agent_id');

            // Amount paid and points to be credited
            $table->decimal('amount_paid_rm', 15, 4);
            $table->decimal('points_to_credit', 15, 4);

            // Bank-in slip evidence
            $table->string('bank_slip_path', 500)->nullable(); // Storage path
            $table->string('bank_slip_ref', 100)->nullable();  // Reference on slip
            $table->date('bank_in_date')->nullable();

            $table->enum('status', [
                'PENDING',    // Submitted, awaiting admin review
                'APPROVED',   // Admin approved, points credited
                'REJECTED',   // Admin rejected with reason
            ])->default('PENDING');

            // Admin action
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();

            // Links to the ledger entry created on approval
            $table->uuid('ledger_id')->nullable();

            $table->timestamps();

            $table->index('agent_id');
            $table->index('status');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('reviewed_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_purchases');
    }
};
