<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 3 Sep 2026 (Task #389) — per Chris's explicit instruction to
// follow proper nonprofit fund-accounting practice without cutting
// corners: the original "Fund Accounting (light)" design (see
// cbe_funds migration comment, Task #330) deliberately limited fund
// tagging to cbe_transactions only, so a manual Journal Voucher, an
// Adjustment/Accrual entry, or any other GL-posted document could never
// be attributed to a fund — meaning the Fund Balance report was
// silently incomplete for anything that didn't go through the cashbook.
// This closes that gap: fund_id becomes a first-class, optional tag on
// every journal LINE (parallel to the existing cost_centre_id), so ANY
// posting — cashbook or manual JV — can be traced back to the fund it
// affects. cbe_funds remains the single source of truth for fund names
// (never duplicated into cbe_cost_centres, which would risk the two
// lists drifting apart).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_journal_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_journal_lines', 'fund_id')) {
                $table->uuid('fund_id')->nullable()->after('cost_centre_id');
                $table->foreign('fund_id')->references('fund_id')->on('cbe_funds')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_journal_lines', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_journal_lines', 'fund_id')) {
                $table->dropForeign(['cbe_journal_lines_fund_id_foreign']);
                $table->dropColumn('fund_id');
            }
        });
    }
};
