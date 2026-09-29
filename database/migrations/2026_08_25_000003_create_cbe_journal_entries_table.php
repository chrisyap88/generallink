<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — one journal entry per real-world event that moves
// money or recognises a liability/receivable (a manual transaction, a
// closed event's summary, a purchase bill, a bill payment). source_type
// + source_id trace every journal entry back to the original record it
// came from, for audit purposes — nothing is ever posted "loose".
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_journal_entries')) {
            Schema::create('cbe_journal_entries', function (Blueprint $table) {
                $table->uuid('journal_id')->primary();
                $table->uuid('cbe_node_id');
                $table->date('entry_date');
                $table->string('reference_no', 50)->nullable();
                $table->string('description', 255)->nullable();
                $table->string('source_type', 30); // TRANSACTION, BILL, BILL_PAYMENT, MANUAL
                $table->uuid('source_id')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'entry_date']);
                $table->index(['source_type', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_journal_entries');
    }
};
