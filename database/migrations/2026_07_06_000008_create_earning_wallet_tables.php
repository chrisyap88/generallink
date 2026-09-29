<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // earning_wallets — one row per agent. No bank info here at
        // all — purely balance tracking.
        // -----------------------------------------------------
        if (! Schema::hasTable('earning_wallets')) {
            Schema::create('earning_wallets', function (Blueprint $table) {
                $table->uuid('wallet_id')->primary();
                $table->uuid('agent_id')->unique();
                $table->decimal('wallet_balance', 12, 2)->default(0);
                $table->decimal('pending_commission', 12, 2)->default(0);
                $table->decimal('approved_commission', 12, 2)->default(0);
                $table->decimal('paid_commission', 12, 2)->default(0);
                $table->timestamp('last_updated')->nullable();
                $table->timestamps();
            });
        }

        // -----------------------------------------------------
        // withdrawal_requests — bank details collected FRESH here only,
        // per withdrawal, never saved to the agent's profile. Account
        // number is encrypted; only masked version ever shown on
        // screen. Finance-only decrypt access, enforced in code.
        // -----------------------------------------------------
        if (! Schema::hasTable('withdrawal_requests')) {
            Schema::create('withdrawal_requests', function (Blueprint $table) {
                $table->uuid('request_id')->primary();
                $table->uuid('agent_id');
                $table->decimal('withdrawal_amount', 12, 2);
                $table->string('bank_name');
                $table->string('account_holder_name');
                $table->text('encrypted_bank_account'); // AES-256, never stored elsewhere
                $table->string('status')->default('PENDING'); // PENDING, APPROVED, REJECTED, PAID
                $table->timestamp('submitted_date')->useCurrent();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_date')->nullable();
                $table->timestamp('paid_date')->nullable();
                $table->string('payment_reference')->nullable();
                $table->text('remarks')->nullable();

                // Email-confirmation step (replaces SMS OTP, per
                // confirmed decision) — must be verified before the
                // request is even considered submitted.
                $table->string('email_confirmation_code')->nullable();
                $table->boolean('email_confirmed')->default(false);
                $table->timestamp('email_confirmed_at')->nullable();

                $table->timestamps();

                $table->index(['agent_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        Schema::dropIfExists('earning_wallets');
    }
};
