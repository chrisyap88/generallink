<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — a debit note reduces what's owed to a supplier
// (e.g. returned goods, a billing correction) — posts DEBIT Accounts
// Payable / CREDIT the bill's original expense account, partially
// reversing the bill. See CbeAccountingService::postDebitNote().
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_debit_notes')) {
            Schema::create('cbe_debit_notes', function (Blueprint $table) {
                $table->uuid('debit_note_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('supplier_id');
                $table->uuid('bill_id')->nullable();
                $table->date('note_date');
                $table->decimal('amount', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('supplier_id')->references('supplier_id')->on('cbe_suppliers')->onDelete('restrict');
                $table->foreign('bill_id')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_debit_notes');
    }
};
