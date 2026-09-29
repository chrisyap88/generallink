<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_transactions', function (Blueprint $table) {
            $table->uuid('txn_id')->primary();

            $table->uuid('policy_id');
            $table->uuid('agent_id');                        // Who receives this commission
            $table->uuid('structure_id');                    // commission_structures record used

            $table->enum('role_at_transaction', [            // Agent's role at time of sale
                'INTRODUCER',
                'TEAM_LEADER',
                'GROUP_LEADER',
            ]);

            // Commission amounts — all derived from structure, never hard-coded
            $table->decimal('policy_premium', 15, 4);        // Snapshot of premium
            $table->decimal('total_pool_amount', 15, 4);     // Gross pool in RM
            $table->decimal('entitlement_pct', 10, 4);       // This agent's % of pool
            $table->decimal('commission_amount', 15, 4);     // RM amount credited

            // Breakage tracking
            $table->boolean('is_breakage')->default(false);  // True if credited to SYSTEM account
            $table->text('redistribution_reason')->nullable(); // Why tier was absent/skipped

            // Reward points (Module 10)
            $table->uuid('reward_points_rate_id')->nullable();
            $table->decimal('reward_points_earned', 15, 4)->default(0);

            $table->enum('status', [
                'PENDING',
                'CONFIRMED',
                'REVERSED',
            ])->default('PENDING');

            $table->uuid('reversed_by_txn_id')->nullable();  // Points to reversal txn

            // Audit — write-once
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('policy_id');
            $table->index('status');
            $table->index('created_at');

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->restrictOnDelete();

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('structure_id')
                  ->references('structure_id')
                  ->on('commission_structures')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_transactions');
    }
};
