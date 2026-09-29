<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #383) — per Chris's Temple/NGO General Ledger
// spec, section on GL Entry: Recurring Journal templates. A treasurer
// sets up a template ONCE (e.g. monthly rent, monthly depreciation-style
// accrual) with its debit/credit lines, then comes back each period and
// clicks "Generate Now" to post that period's journal — no cron/task
// scheduler involved, same manual-trigger pattern as everything else in
// this native ledger (mirrors how Chris runs his own Windows Task
// Scheduler backup rather than Laravel's own scheduler). next_run_date
// is advanced automatically after each generation so the list always
// shows what's due.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_recurring_journal_templates')) {
            Schema::create('cbe_recurring_journal_templates', function (Blueprint $table) {
                $table->uuid('template_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('template_name', 150);
                $table->string('description', 255)->nullable();
                $table->enum('frequency', ['MONTHLY', 'QUARTERLY', 'YEARLY']);
                $table->date('next_run_date');
                $table->date('last_generated_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cbe_recurring_journal_template_lines')) {
            Schema::create('cbe_recurring_journal_template_lines', function (Blueprint $table) {
                $table->uuid('line_id')->primary();
                $table->uuid('template_id');
                $table->uuid('account_id');
                $table->uuid('cost_centre_id')->nullable();
                $table->decimal('debit', 12, 2)->default(0);
                $table->decimal('credit', 12, 2)->default(0);
                $table->string('memo', 255)->nullable();
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('template_id')->references('template_id')->on('cbe_recurring_journal_templates')->onDelete('cascade');
                $table->foreign('account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('restrict');
                $table->foreign('cost_centre_id')->references('centre_id')->on('cbe_cost_centres')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_recurring_journal_template_lines');
        Schema::dropIfExists('cbe_recurring_journal_templates');
    }
};
