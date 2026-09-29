<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_audit_log', function (Blueprint $table) {
            $table->char('audit_id', 36)->primary();
            $table->string('event_type', 100)->comment('CLAIM_SUBMITTED·MATCHED·COMMISSION_HELD·COMMISSION_RELEASED·PAYMENT_RECORDED·ALLOCATION_CONFIRMED·ALLOCATION_REVERSED·VENDOR_FLAGGED·DISPUTE_RAISED');
            $table->string('entity_type', 50)->comment('claim·vendor_payment·allocation·commission_hold');
            $table->char('entity_id', 36)->comment('ID of the affected record');
            $table->decimal('amount_before', 15, 4)->nullable();
            $table->decimal('amount_after', 15, 4)->nullable();
            $table->json('metadata')->nullable()->comment('Any extra context as JSON');
            $table->char('performed_by', 36)->comment('Who performed the action');
            $table->string('ip_address', 45)->nullable()->comment('For security audit');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->comment('Immutable — append only, never updated');

            $table->foreign('performed_by')->references('agent_id')->on('agents');

            $table->index('event_type');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('performed_by');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_audit_log');
    }
};
