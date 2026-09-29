<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 27 Aug 2026 — per Chris: cbe_group_memberships.member_type was a
// single-value enum (VOLUNTEER/FOLLOWER/OFFICER/COMMITTEE) — one label
// per person. Chris explained a real member can simultaneously be a
// Committee member AND an on-duty Consultant AND a Volunteer, which a
// single-value field cannot represent. This table replaces member_type
// with a multi-tag structure: one row per membership+tag, so a person
// can hold several role-tags at once. COMMITTEE is deliberately NOT a
// tag here — committee positions come from cbe_committee_positions
// instead (a term-dated assignment, not a status flag). Old member_type
// data is backfilled into this table (VOLUNTEER/FOLLOWER only — COMMITTEE
// rows are left for Chris to re-enter properly via the new Committee
// Structure screen once term dates are known; OFFICER is dropped here
// since it duplicates cbe_node_officers, the real login-role table).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_member_role_tags')) {
            Schema::create('cbe_member_role_tags', function (Blueprint $table) {
                $table->uuid('tag_id')->primary();
                $table->uuid('membership_id');
                $table->enum('tag', ['VOLUNTEER', 'FOLLOWER', 'CONSULTANT'])->comment('multi-row per membership — a person can hold more than one tag at once');
                $table->timestamps();

                $table->foreign('membership_id')->references('membership_id')->on('cbe_group_memberships')->onDelete('cascade');
                $table->unique(['membership_id', 'tag']);
                $table->index('tag');
            });
        }

        // Backfill from the old single-value member_type column (VOLUNTEER/FOLLOWER only).
        if (Schema::hasColumn('cbe_group_memberships', 'member_type')) {
            $now = now();
            $rows = DB::table('cbe_group_memberships')
                ->whereIn('member_type', ['VOLUNTEER', 'FOLLOWER'])
                ->get(['membership_id', 'member_type']);
            foreach ($rows as $row) {
                DB::table('cbe_member_role_tags')->insertOrIgnore([
                    'tag_id' => (string) Str::uuid(),
                    'membership_id' => $row->membership_id,
                    'tag' => $row->member_type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_member_role_tags');
    }
};
