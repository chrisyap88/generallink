<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #366) — one row per amount actually received
// against a Donation Pledge, mirroring cbe_invoice_payments exactly.
// receipt_no is minted from the SAME shared 'RCPT'/'OR' document
// sequence CbeReceiptService uses for every other official receipt
// (donations, event sales, appointments) — one continuous OR-2026-0001,
// OR-2026-0002... series per node per year for ROS/AGM audit purposes,
// never a second series that could produce a duplicate OR number.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_pledge_receipts')) {
            Schema::create('cbe_pledge_receipts', function (Blueprint $table) {
                $table->uuid('receipt_id')->primary();
                $table->uuid('pledge_id');
                $table->string('receipt_no', 30);
                $table->date('receipt_date');
                $table->decimal('amount', 12, 2);
                $table->uuid('bank_account_id')->nullable();
                $table->string('payment_method', 60)->nullable();
                $table->string('reference_no', 60)->nullable();
                $table->uuid('journal_id')->nullable();
                $table->string('gl_posting_status', 20)->default('NOT_POSTED');
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('pledge_id')->references('pledge_id')->on('cbe_donation_pledges')->onDelete('cascade');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index('pledge_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_pledge_receipts');
    }
};
