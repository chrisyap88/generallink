<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 1 Sep 2026 — per Chris: a treasurer's month-end close isn't real
// unless a closed month can no longer be edited. Every posting method in
// CbeAccountingService funnels through one private postJournal() — this
// table is checked there, so locking one row here blocks Transactions,
// Bills, Bill Payments, Invoices, Invoice Payments, Fixed Asset
// capitalisation, and Manual Journal Vouchers all at once, for that
// node, for that month. Scoped per cbe_node_id (not per group_label_id)
// because each temple/branch closes its own books on its own schedule,
// same scoping as cbe_journal_entries itself.
//
// A period with no row here at all is implicitly OPEN — locking is an
// explicit "Close Period" action a treasurer takes, not a setup step
// that has to happen first.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_fiscal_periods')) {
            Schema::create('cbe_fiscal_periods', function (Blueprint $table) {
                $table->uuid('period_id')->primary();
                $table->uuid('cbe_node_id');
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
                $table->uuid('closed_by')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('closed_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->unique(['cbe_node_id', 'period_year', 'period_month'], 'cbe_fiscal_periods_node_year_month_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_fiscal_periods');
    }
};
