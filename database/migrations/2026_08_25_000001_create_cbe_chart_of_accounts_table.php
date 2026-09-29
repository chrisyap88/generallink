<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — per Chris: build a real, native double-entry
// accounting engine inside GeneralLink itself (Chart of Accounts,
// General Ledger, Trial Balance, Balance Sheet, Profit & Loss) instead
// of paying for a 3rd-party add-on (Akaunting's "Double-Entry" app is
// $72/year, not part of its free plan) or standing up a whole separate
// server (ERPNext/Odoo need Python/PostgreSQL, which Chris's XAMPP
// stack can't run). This keeps everything on the same PHP/MySQL stack,
// no extra hosting, no per-year fee.
//
// Chart of Accounts is scoped by group_label_id, exactly like
// cbe_transaction_categories already is (null = shared platform-wide
// default set; a specific id = that CBE community's own accounts) —
// each individual node (temple/branch) then keeps its OWN books
// (its own journal entries/GL/Trial Balance) using this shared
// structure, mirroring how cbe_transactions is already scoped per
// cbe_node_id today.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_chart_of_accounts')) {
            Schema::create('cbe_chart_of_accounts', function (Blueprint $table) {
                $table->uuid('account_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('account_code', 20);
                $table->string('account_name', 150);
                $table->string('account_name_zh', 150)->nullable();
                $table->enum('account_type', ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE']);
                $table->uuid('parent_account_id')->nullable();
                // is_system = auto-created core account (Cash, Accounts
                // Payable, Fund Balance) that the UI won't let anyone
                // delete — everything else is a normal admin-manageable row.
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('parent_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->unique(['group_label_id', 'account_code'], 'cbe_coa_group_code_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_chart_of_accounts');
    }
};
