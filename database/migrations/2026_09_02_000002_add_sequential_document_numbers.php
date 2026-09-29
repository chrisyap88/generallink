<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #335) — Sequential document numbering. Per
// Chris's "kindergarten standard" correction: Bills/Invoices/Journal
// Vouchers had no gap-free internal reference number, only the
// supplier's OWN bill number (a free-text field the treasurer types in,
// which stays as-is — this is a SEPARATE, GeneralLink-assigned number
// for the temple's own audit trail, e.g. for ROS submission).
//
// One shared counter table (cbe_document_sequences) generates
// BILL-2026-0001 / INV-2026-0001 / JV-2026-0001 style numbers, gap-free
// per node per year, reused for every document type going forward
// (official receipts, purchase requests, etc. in later tasks).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_document_sequences', function (Blueprint $table) {
            $table->uuid('sequence_id')->primary();
            $table->uuid('cbe_node_id');
            $table->string('doc_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['cbe_node_id', 'doc_type', 'year'], 'cbe_doc_seq_node_type_year_unique');
        });

        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            $table->string('doc_ref_no', 30)->nullable()->after('bill_id');
        });

        Schema::table('cbe_invoices', function (Blueprint $table) {
            $table->string('doc_ref_no', 30)->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_invoices', function (Blueprint $table) {
            $table->dropColumn('doc_ref_no');
        });
        Schema::table('cbe_purchase_bills', function (Blueprint $table) {
            $table->dropColumn('doc_ref_no');
        });
        Schema::dropIfExists('cbe_document_sequences');
    }
};
