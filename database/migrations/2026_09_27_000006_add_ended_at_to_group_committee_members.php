<?php

// NEW 27 Sep 2026 — per Chris: "End Term" moves the person to Previous
// Terms straight away. ended_at marks a term that was ended by hand (the
// date-only rule kept a term ending TODAY in Current Term until tomorrow).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('group_committee_members') && ! Schema::hasColumn('group_committee_members', 'ended_at')) {
            Schema::table('group_committee_members', function (Blueprint $t) {
                $t->timestamp('ended_at')->nullable()->after('term_end_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('group_committee_members', 'ended_at')) {
            Schema::table('group_committee_members', function (Blueprint $t) {
                $t->dropColumn('ended_at');
            });
        }
    }
};
