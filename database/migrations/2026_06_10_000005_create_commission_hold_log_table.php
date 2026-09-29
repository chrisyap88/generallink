<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_hold_log', function (Blueprint $table) {
            $table->char('hold_id', 36)->primary();
            $table->char('claim_id', 36);
            $table->char('commission_txn_id', 36)->nullable()->comment('FK to commission_transactions');
            $table->char('agent_id', 36)->comment('Agent whose commission is held');
            $table->enum('role_at_transaction', [
                'GROUP_LEADER',
                'TEAM_LEADER',
                'INTRODUCER'
            ])->comment('Role when commission was earned');
            $table->decimal('held_amount', 15, 4)->comment('Commission amount frozen');
            $table->enum('hold_reason', [
                'CLAIM_PENDING',
                'VENDOR_UNMATCHED',
                'AGENT_NOT_CONFIRMED'
            ]);
            $table->timestamp('held_at')->comment('When hold was placed — immutable');
            $table->timestamp('released_at')->nullable()->comment('Set when commission released');
            $table->char('released_by', 36)->nullable()->comment('Admin who released');
            $table->enum('status', [
                'HELD',
                'RELEASED',
                'REVERSED'
            ])->default('HELD');
            $table->text('notes')->nullable();

            $table->foreign('claim_id')->references('claim_id')->on('claims');
            $table->foreign('agent_id')->references('agent_id')->on('agents');

            $table->index('agent_id');
            $table->index('status');
            $table->index('claim_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_hold_log');
    }
};
