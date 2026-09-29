<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 30 Aug 2026 — Bank Reconciliation, rebuilt on the native ledger.
// No per-temple sub-account trick needed here (that was only required
// for the old centrex package, whose Chart of Accounts was fully
// global). Here, cbe_journal_entries is already scoped by cbe_node_id,
// so each temple's own Cash account balance is already isolated — this
// table just records the monthly check against that figure.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_bank_reconciliations')) {
            Schema::create('cbe_bank_reconciliations', function (Blueprint $table) {
                $table->uuid('reconciliation_id')->primary();
                $table->uuid('cbe_node_id');
                $table->date('statement_date');
                $table->decimal('opening_balance', 12, 2);
                $table->decimal('ending_balance', 12, 2);
                $table->text('notes')->nullable();
                $table->enum('status', ['DRAFT', 'COMPLETED'])->default('DRAFT');
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'statement_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_reconciliations');
    }
};
