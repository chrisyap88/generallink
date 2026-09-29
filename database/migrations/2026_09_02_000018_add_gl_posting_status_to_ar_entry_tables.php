<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #358) — extends the same GL Posting Status
// linkage built for Invoice/Payment/Debit Note/Credit Note (migration
// 2026_09_02_000017) to the 3 new AR Entry tables added alongside it:
// Adjustment, Refund, Opening Balance. Doc A's spec explicitly lists
// these three among the AR documents that must auto-post to GL and
// show a posting status, so they get the exact same two columns.
return new class extends Migration
{
    private array $tables = ['cbe_ar_adjustments', 'cbe_ar_refunds', 'cbe_ar_opening_balances'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            if (! Schema::hasColumn($t, 'journal_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->uuid('journal_id')->nullable()->after('recorded_by');
                    $table->string('gl_posting_status', 12)->default('NOT_POSTED')->after('journal_id');
                    $table->foreign('journal_id')->references('journal_id')->on('cbe_journal_entries')->onDelete('set null');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            if (Schema::hasColumn($t, 'journal_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropForeign(['journal_id']);
                    $table->dropColumn(['journal_id', 'gl_posting_status']);
                });
            }
        }
    }
};
