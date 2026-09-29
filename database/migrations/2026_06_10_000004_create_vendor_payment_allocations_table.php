<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_payment_allocations', function (Blueprint $table) {
            $table->char('allocation_id', 36)->primary();
            $table->char('payment_id', 36);
            $table->char('claim_id', 36);
            $table->decimal('allocated_amount', 15, 4)->comment('Amount allocated to this claim');
            $table->enum('matching_stage', [
                'REMITTANCE_MATCHED',
                'PARTIAL_EVIDENCE',
                'LAST_ATTEMPT'
            ]);
            $table->enum('match_method', [
                'AUTO',
                'MANUAL',
                'LUMP_SUM'
            ]);
            $table->string('vendor_ref_provided', 100)->nullable()->comment('Vendor own reference if any');
            $table->char('verified_by', 36)->nullable()->comment('Admin who confirmed match');
            $table->timestamp('verified_at')->nullable();
            $table->tinyInteger('agent_confirmed')->default(0)->comment('Agent reconfirmed their claim');
            $table->timestamp('agent_confirmed_at')->nullable();
            $table->timestamp('agent_confirmation_deadline')->nullable()->comment('Deadline before reverting to pending');
            $table->enum('status', [
                'PENDING',
                'AGENT_NOTIFIED',
                'CONFIRMED',
                'REVERSED',
                'NOT_VERIFIED'
            ])->default('PENDING');
            $table->text('reversal_reason')->nullable();
            $table->char('reversed_by', 36)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_id')->references('payment_id')->on('vendor_payments');
            $table->foreign('claim_id')->references('claim_id')->on('claims');
            $table->foreign('verified_by')->references('agent_id')->on('agents')->nullable();
            $table->foreign('reversed_by')->references('agent_id')->on('agents')->nullable();

            $table->unique(['payment_id', 'claim_id'])->comment('One allocation per claim per payment');
            $table->index('status');
            $table->index('matching_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_payment_allocations');
    }
};
