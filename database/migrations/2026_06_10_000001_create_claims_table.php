<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->char('claim_id', 36)->primary();
            $table->string('claim_reference', 50)->unique();
            $table->char('policy_id', 36);
            $table->char('customer_id', 36);
            $table->char('agent_id', 36);
            $table->char('vendor_id', 36);
            $table->char('product_id', 36);
            $table->decimal('claim_amount', 15, 4);
            $table->decimal('approved_amount', 15, 4)->nullable();
            $table->date('sales_transaction_date');
            $table->timestamp('claim_upload_date')->nullable();
            $table->enum('status', [
                'PENDING',
                'MATCHED',
                'CONFIRMED',
                'PAID',
                'REJECTED',
                'DISPUTED'
            ])->default('PENDING');
            $table->tinyInteger('commission_held')->default(1)->comment('1 = commission frozen on submission');
            $table->timestamp('commission_released_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->tinyInteger('is_deleted')->default(0);
            $table->char('created_by', 36)->nullable();
            $table->char('updated_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('policy_id')->references('policy_id')->on('sales_transactions');
            $table->foreign('customer_id')->references('customer_id')->on('customers');
            $table->foreign('agent_id')->references('agent_id')->on('agents');
            $table->foreign('vendor_id')->references('vendor_id')->on('vendors');
            $table->foreign('product_id')->references('product_id')->on('products');

            $table->index('status');
            $table->index('vendor_id');
            $table->index('agent_id');
            $table->index('commission_held');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
