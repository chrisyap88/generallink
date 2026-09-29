<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claim_matching_log', function (Blueprint $table) {
            $table->char('log_id', 36)->primary();
            $table->char('payment_id', 36);
            $table->char('claim_id', 36)->nullable()->comment('Nullable if no match found');
            $table->enum('stage', [
                'REMITTANCE_MATCHED',
                'PARTIAL_EVIDENCE',
                'LAST_ATTEMPT'
            ]);
            $table->string('action', 100)->comment('AUTO_MATCHED·MANUAL_MATCH·MOVED_DOWN·FLAGGED·REVERSED');
            $table->char('performed_by', 36)->comment('Admin who performed action');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->comment('Immutable — never updated');

            $table->foreign('payment_id')->references('payment_id')->on('vendor_payments');
            $table->foreign('performed_by')->references('agent_id')->on('agents');

            $table->index('payment_id');
            $table->index('claim_id');
            $table->index('stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_matching_log');
    }
};
