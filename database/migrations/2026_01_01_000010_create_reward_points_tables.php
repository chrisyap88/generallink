<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rate table — configurable per vendor + product
        Schema::create('reward_points_rates', function (Blueprint $table) {
            $table->uuid('rate_id')->primary();

            // NULL = applies to all vendors / all products (cascading priority)
            $table->uuid('vendor_id')->nullable();
            $table->uuid('product_id')->nullable();

            // Points awarded per RM 1.00 of commission received
            $table->decimal('points_per_rm', 10, 4);

            $table->date('valid_from');
            $table->date('valid_to')->nullable();

            $table->boolean('is_active')->default(true);

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'product_id', 'is_active']);

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->nullOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->nullOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Immutable ledger — write-once, corrections via reversal entries only
        Schema::create('reward_points_ledger', function (Blueprint $table) {
            $table->uuid('ledger_id')->primary();

            $table->uuid('agent_id');

            $table->enum('txn_type', [
                'EARNED',       // From commission
                'REDEEMED',     // Used for reward
                'CASHED_OUT',   // Converted to RM wallet
                'EXPIRED',      // Points expiry
                'REVERSED',     // Commission reversal clawback
                'ADJUSTMENT',   // Admin manual correction
                'PURCHASED',    // Bought by member via bank transfer
                'TRANSFERRED',  // Transferred to/from another agent
            ]);

            $table->decimal('points_in', 15, 4)->default(0);    // Credit
            $table->decimal('points_out', 15, 4)->default(0);   // Debit
            $table->decimal('running_balance', 15, 4);          // Cumulative balance

            // Source linkage
            $table->uuid('source_txn_id')->nullable();           // commission_transactions.txn_id
            $table->uuid('transfer_from_agent_id')->nullable();  // For TRANSFERRED entries
            $table->uuid('transfer_to_agent_id')->nullable();    // For TRANSFERRED entries
            $table->string('reference_no', 100)->nullable();     // Bank slip ref / redemption ref
            $table->text('notes')->nullable();

            // Write-once — no updated_at
            $table->uuid('created_by');
            $table->timestamp('created_at')->useCurrent();

            $table->index('agent_id');
            $table->index('txn_type');
            $table->index('created_at');
            $table->index('source_txn_id');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_points_ledger');
        Schema::dropIfExists('reward_points_rates');
    }
};
