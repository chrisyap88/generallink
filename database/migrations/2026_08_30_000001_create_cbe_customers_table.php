<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 30 Aug 2026 — Accounts Receivable, rebuilt on the SAME native
// ledger as Accounts Payable (cbe_journal_entries/cbe_chart_of_accounts)
// instead of the separate centrex/laravel-accounting package used
// earlier — that package kept its own Chart of Accounts, so anything
// posted there never showed up in the Trial Balance/Balance Sheet/P&L/
// GL Chris already had. This table is an exact mirror of cbe_suppliers.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_customers')) {
            Schema::create('cbe_customers', function (Blueprint $table) {
                $table->uuid('customer_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('customer_name', 150);
                $table->string('contact_person', 100)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->text('notes')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'customer_name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_customers');
    }
};
