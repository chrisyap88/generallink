<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->uuid('beneficiary_id')->primary();

            // The account this beneficiary belongs to
            $table->uuid('agent_id');

            // Beneficiary personal details (mirrors affiliate profile)
            $table->string('full_name', 200);
            $table->text('nric_encrypted');              // AES-256
            $table->string('relationship', 100);         // e.g. Spouse, Child, Parent
            $table->string('phone', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->text('address')->nullable();

            // Bank details for payout transfer on takeover
            $table->string('bank_name', 100)->nullable();
            $table->text('bank_account_encrypted')->nullable(); // AES-256

            // Priority order when multiple beneficiaries exist
            $table->tinyInteger('priority_order')->default(1);

            // Takeover tracking
            $table->boolean('takeover_triggered')->default(false);
            $table->timestamp('takeover_at')->nullable();
            $table->uuid('takeover_by')->nullable();          // Admin who triggered
            $table->text('takeover_notes')->nullable();

            $table->boolean('is_active')->default(true);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('takeover_triggered');

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->cascadeOnDelete();

            $table->foreign('takeover_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
