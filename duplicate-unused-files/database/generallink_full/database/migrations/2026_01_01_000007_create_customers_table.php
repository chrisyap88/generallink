<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('customer_id')->primary();

            // NRIC is unique — prevents duplicate customer records
            $table->text('nric_encrypted')->unique();   // AES-256; index on hash
            $table->string('nric_hash', 64)->unique();  // SHA-256 hash for uniqueness check

            $table->string('full_name', 200);
            $table->string('email', 200)->nullable();
            $table->string('phone', 20);
            $table->text('address')->nullable();
            $table->string('postcode', 10)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();

            // Ownership — immutable after first policy submission
            $table->uuid('owned_by_agent_id');

            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('owned_by_agent_id');
            $table->index('nric_hash');

            $table->foreign('owned_by_agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
