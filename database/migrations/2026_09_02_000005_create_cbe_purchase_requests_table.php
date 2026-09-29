<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #335) — Purchase Request: a pre-bill approval step.
// An officer raises a request for planned spending (supplier + line items,
// no money moved yet); a DIFFERENT officer approves or rejects it (same
// self-approval block as Maker-Checker, ADMIN exempt); once APPROVED, one
// click converts it straight into a real Purchase Bill (with its own
// sequential doc_ref_no) and posts the journal exactly like any other bill.
// Kept separate from cbe_bill_lines — a request's lines never touch the
// ledger until conversion.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_purchase_requests', function (Blueprint $table) {
            $table->uuid('request_id')->primary();
            $table->string('doc_ref_no', 40)->nullable();
            $table->uuid('cbe_node_id');
            $table->uuid('supplier_id')->nullable();
            $table->date('request_date');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('PENDING'); // PENDING / APPROVED / REJECTED / CONVERTED
            $table->uuid('requested_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->uuid('converted_bill_id')->nullable();
            $table->timestamps();

            $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
            $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('set null');
            $table->index(['cbe_node_id', 'status']);
        });

        Schema::create('cbe_purchase_request_lines', function (Blueprint $table) {
            $table->uuid('line_id')->primary();
            $table->uuid('request_id');
            $table->uuid('category_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_amount', 12, 2);
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('request_id')->references('request_id')->on('cbe_purchase_requests')->onDelete('cascade');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_purchase_request_lines');
        Schema::dropIfExists('cbe_purchase_requests');
    }
};
