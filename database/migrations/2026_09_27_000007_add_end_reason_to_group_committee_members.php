<?php

// NEW 27 Sep 2026 — per Chris: Undo End Term. end_reason tells HOW a term
// was closed (END = End Term button, CONTINUE = Continue New Term,
// NEWTERM = New Term for the whole committee); only END can be undone.
// original_term_end_date keeps the end date the term had before End Term,
// so Undo restores it exactly.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_committee_members')) {
            return;
        }
        Schema::table('group_committee_members', function (Blueprint $t) {
            if (! Schema::hasColumn('group_committee_members', 'ended_at')) {
                $t->timestamp('ended_at')->nullable()->after('term_end_date');
            }
            if (! Schema::hasColumn('group_committee_members', 'end_reason')) {
                $t->string('end_reason', 10)->nullable();
            }
            if (! Schema::hasColumn('group_committee_members', 'original_term_end_date')) {
                $t->date('original_term_end_date')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('group_committee_members', function (Blueprint $t) {
            foreach (['end_reason', 'original_term_end_date'] as $c) {
                if (Schema::hasColumn('group_committee_members', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
