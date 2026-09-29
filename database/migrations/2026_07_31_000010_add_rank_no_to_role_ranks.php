<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Rank system redesign, Phase 1. Per Chris: Organization
// Rank Hierarchy Structure needs a Rank No that Admin types himself
// (alphanumeric — "1", "2A", "3" — not a plain auto-incrementing integer),
// distinct from the existing display_order column (which stays as an
// internal tie-breaker for sorting, no longer shown in the UI). Existing
// rows are backfilled from their current display_order so nothing breaks;
// going forward the app layer requires rank_no on every new/edited rank.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            $table->string('rank_no', 20)->nullable()->after('rank_name');
        });

        DB::table('role_ranks')->whereNull('rank_no')->orderBy('display_order')->get(['rank_id', 'display_order'])
            ->each(function ($row) {
                DB::table('role_ranks')->where('rank_id', $row->rank_id)->update(['rank_no' => (string) $row->display_order]);
            });
    }

    public function down(): void
    {
        Schema::table('role_ranks', function (Blueprint $table) {
            $table->dropColumn('rank_no');
        });
    }
};
