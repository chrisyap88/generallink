<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 30 Aug 2026 — exact mirror of cbe_bill_payments, for the AR side.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_invoice_payments')) {
            Schema::create('cbe_invoice_payments', function (Blueprint $table) {
                $table->uuid('payment_id')->primary();
                $table->uuid('invoice_id');
                $table->date('payment_date');
                $table->decimal('amount', 12, 2);
                $table->string('payment_method', 60)->nullable();
                $table->string('reference_no', 60)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('invoice_id')->references('invoice_id')->on('cbe_invoices')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_invoice_payments');
    }
};
