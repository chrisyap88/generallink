<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->uuid('policy_id')->primary();

            // Core fields common to all product types
            $table->string('policy_number', 100)->unique();
            $table->uuid('vendor_id');
            $table->uuid('product_id');
            $table->uuid('customer_id');
            $table->uuid('agent_id');                    // Agent who submitted

            $table->decimal('premium_amount', 15, 4);
            $table->decimal('sum_insured', 15, 4)->nullable();

            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->date('renewal_date')->nullable();

            $table->enum('status', [
                'DRAFT',
                'SUBMITTED',
                'ACTIVE',
                'PENDING_RENEWAL',
                'RENEWED',
                'LAPSED',
                'CANCELLED',
            ])->default('DRAFT');

            // Upload tracking
            $table->uuid('upload_batch_id')->nullable();     // Links to batch upload
            $table->uuid('template_id')->nullable();         // Field mapping template used

            // Version control for amendments
            $table->unsignedInteger('version')->default(1);
            $table->uuid('previous_version_id')->nullable(); // Points to prior version

            $table->boolean('is_deleted')->default(false);

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->index('agent_id');
            $table->index('customer_id');
            $table->index('vendor_id');
            $table->index('product_id');
            $table->index('status');
            $table->index('renewal_date');
            $table->index('upload_batch_id');

            $table->foreign('vendor_id')
                  ->references('vendor_id')
                  ->on('vendors')
                  ->restrictOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->restrictOnDelete();

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->restrictOnDelete();

            $table->foreign('agent_id')
                  ->references('agent_id')
                  ->on('agents')
                  ->restrictOnDelete();
        });

        // EAV table for product-specific fields (vehicle reg, property address, etc.)
        Schema::create('sales_transaction_attributes', function (Blueprint $table) {
            $table->uuid('attr_id')->primary();
            $table->uuid('policy_id');
            $table->uuid('product_id');
            $table->string('attribute_name', 200);
            $table->text('attribute_value')->nullable();
            $table->timestamps();

            $table->index(['policy_id', 'attribute_name']);

            $table->foreign('policy_id')
                  ->references('policy_id')
                  ->on('sales_transactions')
                  ->cascadeOnDelete();

            $table->foreign('product_id')
                  ->references('product_id')
                  ->on('products')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transaction_attributes');
        Schema::dropIfExists('sales_transactions');
    }
};
