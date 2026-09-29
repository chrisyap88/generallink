<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #335) — Multi-line Bills/Invoices with tax, per
// Chris's "kindergarten standard" correction: a real bill has several
// expense lines against different GL accounts, not one description and
// one amount. These tables are an ADDITIONAL detail layer alongside the
// existing cbe_purchase_bills/cbe_invoices header row — the header's
// own amount/category_id columns are untouched and still exist, so
// every bill/invoice created before this migration keeps working
// exactly as it did (CbeAccountingService checks for lines first, falls
// back to the header fields when none exist — see postBill()/postInvoice()).
//
// Tax note: Malaysian SST (unlike the old GST) is generally NOT
// reclaimable by the paying organisation — there is no "Input Tax"
// asset account to net off against for a temple/NGO. tax_rate and
// tax_amount are captured here for the record (what the supplier
// charged, for audit/ROS purposes) but roll into the SAME expense
// account as the line itself when posted — not a separate tax GL line.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_bill_lines', function (Blueprint $table) {
            $table->uuid('line_id')->primary();
            $table->uuid('bill_id');
            $table->uuid('category_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('cascade');
            $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
            $table->index('bill_id');
        });

        Schema::create('cbe_invoice_lines', function (Blueprint $table) {
            $table->uuid('line_id')->primary();
            $table->uuid('invoice_id');
            $table->uuid('category_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('invoice_id')->references('invoice_id')->on('cbe_invoices')->onDelete('cascade');
            $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('set null');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_invoice_lines');
        Schema::dropIfExists('cbe_bill_lines');
    }
};
