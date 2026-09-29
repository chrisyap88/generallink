<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: bank statement, stored as an attachment
// per month per CBE node (a Temple may have more than one bank account,
// so no unique constraint on month/year alone). This is the supporting
// document only — it does NOT by itself produce the Income & Expenditure
// report. The treasurer records the actual line items in
// cbe_transactions (next migration), optionally tied back to the
// statement that proves them, and THAT ledger is what the report is
// built from — a scanned statement alone has no machine-readable
// categorised amounts to total up.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_bank_statements')) {
            Schema::create('cbe_bank_statements', function (Blueprint $table) {
                $table->uuid('statement_id')->primary();
                $table->uuid('cbe_node_id');
                $table->unsignedTinyInteger('statement_month');
                $table->unsignedSmallInteger('statement_year');
                $table->string('attachment_path', 500);
                $table->string('attachment_original_name', 255)->nullable();
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'statement_year', 'statement_month'], 'cbe_bank_statements_node_period_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_statements');
    }
};
