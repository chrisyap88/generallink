<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 25 Sep 2026 -- per Chris: "i already give you the list, position,
// name full details... it never have such position in CBE goup Persatuan
// Tao." The 24 Sep group-scoping change kept the original 6 positions
// (President/Deputy President/Secretary/Treasurer/CEO/Finance Director)
// as ONE shared row every CBE group saw and could never turn off --
// meaning CEO/Finance Director (SME-style titles) were forced onto every
// religious/NGO group too, with no way to remove them for a group like
// Tao without affecting every other group. This corrects that by
// switching to the SAME pattern already used for role_ranks (per
// Chris's original 24 Sep instruction: "follow the method how we set up
// rank in org group") -- every CBE group gets its OWN independent copy
// of these positions, fully add/rename/deactivate-able without
// affecting any other group. A brand new CBE group now starts with NO
// positions and Admin adds whichever apply to it, exactly like Role
// Ranks already works.
return new class extends Migration
{
    public function up(): void
    {
        // 'code' was globally unique (one shared catalog). Now that each
        // CBE group gets its own copy, the same code (e.g. "PRESIDENT")
        // must be allowed to exist once per group.
        Schema::table('cbe_committee_position_types', function (Blueprint $table) {
            $table->dropUnique('cbe_committee_position_types_code_unique');
            $table->unique(['group_label_id', 'code'], 'cbe_committee_position_types_group_code_unique');
        });

        $groups = DB::table('group_labels')->where('group_type', 'CBE')->get(['group_label_id']);
        $sharedDefaults = DB::table('cbe_committee_position_types')->whereNull('group_label_id')->get();

        $now = now();
        foreach ($groups as $g) {
            foreach ($sharedDefaults as $d) {
                $exists = DB::table('cbe_committee_position_types')
                    ->where('group_label_id', $g->group_label_id)
                    ->where('code', $d->code)
                    ->exists();
                if ($exists) {
                    continue;
                }

                DB::table('cbe_committee_position_types')->insert([
                    'id' => (string) Str::uuid(),
                    'group_label_id' => $g->group_label_id,
                    'code' => $d->code,
                    'position_label' => $d->position_label,
                    'is_system' => false,
                    'is_active' => $d->is_active,
                    'sort_order' => $d->sort_order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // The shared "System Default" rows are no longer used by any
        // screen once every group has its own copy -- remove them so
        // they can never resurface as a forced, uneditable default.
        DB::table('cbe_committee_position_types')->whereNull('group_label_id')->delete();
    }

    public function down(): void
    {
        Schema::table('cbe_committee_position_types', function (Blueprint $table) {
            $table->dropUnique('cbe_committee_position_types_group_code_unique');
            $table->unique('code', 'cbe_committee_position_types_code_unique');
        });
    }
};
