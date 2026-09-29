<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #330) — Fund Accounting (light). Chris asked for
// "fund tagging" — this is deliberately NOT a full multi-equity-account
// redesign (that would mean splitting Fund Balance 3000 into one GL
// account per fund, touching every posting method in
// CbeAccountingService). Instead, cbe_transactions gets an optional
// fund_id tag, and the Fund Balance report (a management report, not a
// formal GL statement) sums income-minus-expense per fund straight from
// cbe_transactions. This is the same "light" pattern already used for
// AR receivables — a live computed figure alongside the formal
// double-entry books, not folded into them. Funds are scoped per node,
// same reasoning as Tax Rates: one temple's "Building Fund" is not
// another temple's.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cbe_funds', function (Blueprint $table) {
            $table->uuid('fund_id')->primary();
            $table->uuid('cbe_node_id');
            $table->string('fund_name', 150);
            $table->enum('fund_type', ['RESTRICTED', 'UNRESTRICTED'])->default('UNRESTRICTED');
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
            $table->index('cbe_node_id');
        });

        Schema::table('cbe_transactions', function (Blueprint $table) {
            $table->uuid('fund_id')->nullable()->after('category_id');
            $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_transactions', function (Blueprint $table) {
            $table->dropForeign(['fund_id']);
            $table->dropColumn('fund_id');
        });
        Schema::dropIfExists('cbe_funds');
    }
};
