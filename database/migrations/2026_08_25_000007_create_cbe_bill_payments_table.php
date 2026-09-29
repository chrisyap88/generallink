<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_bill_payments')) {
            Schema::create('cbe_bill_payments', function (Blueprint $table) {
                $table->uuid('payment_id')->primary();
                $table->uuid('bill_id');
                $table->date('payment_date');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 60)->nullable();
                $table->string('reference_no', 60)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bill_payments');
    }
};
