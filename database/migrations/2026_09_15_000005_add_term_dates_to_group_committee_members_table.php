<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per the master spec's earlier committee-structure
// decision (Section 53, Box 2): every committee/management assignment
// carries a mandatory term-of-service date range, so "current term" vs
// "previous terms" is simply a query by date range — past assignments
// are never deleted, only "ended" (term_end_date set), and stay as
// permanent history. term_start_date defaults to the day the row is
// created; term_end_date null means "still serving" (open-ended term).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_committee_members', function (Blueprint $table) {
            if (! Schema::hasColumn('group_committee_members', 'term_start_date')) {
                $table->date('term_start_date')->nullable()->after('agent_id');
            }
            if (! Schema::hasColumn('group_committee_members', 'term_end_date')) {
                $table->date('term_end_date')->nullable()->after('term_start_date');
            }
        });

        // Backfill any row that already exists (e.g. created before this
        // migration ran) with today as its term start, so it shows up
        // correctly under "Current Term" rather than being mistaken for
        // missing data.
        \Illuminate\Support\Facades\DB::table('group_committee_members')
            ->whereNull('term_start_date')
            ->update(['term_start_date' => now()->toDateString()]);
    }

    public function down(): void
    {
        Schema::table('group_committee_members', function (Blueprint $table) {
            if (Schema::hasColumn('group_committee_members', 'term_end_date')) {
                $table->dropColumn('term_end_date');
            }
            if (Schema::hasColumn('group_committee_members', 'term_start_date')) {
                $table->dropColumn('term_start_date');
            }
        });
    }
};
