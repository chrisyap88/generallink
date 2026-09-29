<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: the actual income/expense ledger entries
// for a CBE node. This is what the Income & Expenditure report (Excel,
// part of the Annual Report) is generated from — sum amount grouped by
// category_id and type, for the chosen node + year. bank_statement_id is
// nullable and optional: an entry can point back to the statement that
// backs it (audit trail), but doesn't have to (e.g. cash donations).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_transactions')) {
            Schema::create('cbe_transactions', function (Blueprint $table) {
                $table->uuid('transaction_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('bank_statement_id')->nullable();
                $table->uuid('category_id');
                $table->date('transaction_date');
                $table->string('description', 255)->nullable();
                $table->decimal('amount', 12, 2);
                $table->uuid('entered_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('bank_statement_id')->references('statement_id')->on('cbe_bank_statements')->onDelete('set null');
                $table->foreign('category_id')->references('category_id')->on('cbe_transaction_categories')->onDelete('restrict');
                $table->foreign('entered_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'transaction_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_transactions');
    }
};
