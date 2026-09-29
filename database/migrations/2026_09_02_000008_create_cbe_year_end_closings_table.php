<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #332) — Year-End Rollforward + Checklist.
// DELIBERATELY does NOT post a physical "closing journal entry" to
// zero out Income/Expense accounts into Fund Balance — the Balance
// Sheet already computes accumulated Net Surplus live, from the full
// journal, as-of any date (see CbeAccountingController::balanceSheet
// — "Net Surplus (accumulated Income − Expense to date)"). Posting a
// destructive closing entry on top of that live calculation would
// double-count and break Balance Sheet history. Instead, this table
// is a soft per-year checklist (all months locked, AGM held, ROS
// submitted) plus a marker for when the year was formally closed —
// tracking only, no ledger impact.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_year_end_closings', function (Blueprint $table) {
            $table->uuid('closing_id')->primary();
            $table->uuid('cbe_node_id');
            $table->unsignedSmallInteger('fiscal_year');
            $table->boolean('agm_held')->default(false);
            $table->date('agm_date')->nullable();
            $table->boolean('ros_submitted')->default(false);
            $table->date('ros_submission_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('year_closed')->default(false);
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
            $table->unique(['cbe_node_id', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_year_end_closings');
    }
};
