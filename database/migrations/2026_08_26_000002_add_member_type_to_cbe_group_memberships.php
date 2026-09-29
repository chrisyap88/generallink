<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026, 15th pass — per Chris: "does the member profile flag
// that the status is a volunteer or follower?" — a temple/club member
// registered under a specific node (cbe_group_memberships.cbe_node_id)
// previously had no way to say WHAT KIND of member they are beyond the
// generic ACTIVE/INACTIVE status. This adds that distinct flag —
// nullable so nothing existing breaks, and set independently of
// status (a member can be ACTIVE + FOLLOWER, INACTIVE + VOLUNTEER,
// etc. — two different questions).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_group_memberships', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_group_memberships', 'member_type')) {
                $table->enum('member_type', ['VOLUNTEER', 'FOLLOWER', 'OFFICER', 'COMMITTEE'])
                    ->nullable()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_group_memberships', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_group_memberships', 'member_type')) {
                $table->dropColumn('member_type');
            }
        });
    }
};
