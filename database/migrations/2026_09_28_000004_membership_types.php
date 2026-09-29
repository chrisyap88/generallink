<?php

// NEW 28 Sep 2026 — per Chris: search by MEMBERSHIP TYPE (Individual, Family,
// SBE …) instead of Fee Status. Membership Type is a pick list (Master File ›
// Member Pick Lists › Membership Type, with Add); each Membership Plan of a
// CBE group belongs to one Membership Type.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_profile_options') && ! DB::table('member_profile_options')->where('list_code', 'MEMTYPE')->exists()) {
            foreach ([['INDIVIDUAL', 'Individual', '个人'], ['FAMILY', 'Family', '家庭'], ['SBE', 'SBE', '中小企业'], ['CORPORATE', 'Corporate', '企业']] as $i => [$code, $label, $zh]) {
                DB::table('member_profile_options')->insert([
                    'id' => (string) Str::uuid(), 'list_code' => 'MEMTYPE', 'code' => $code, 'label' => $label, 'label_zh' => $zh,
                    'sort_order' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        if (Schema::hasTable('cbe_membership_plans') && ! Schema::hasColumn('cbe_membership_plans', 'membership_type_id')) {
            Schema::table('cbe_membership_plans', fn (Blueprint $t) => $t->uuid('membership_type_id')->nullable()->after('group_label_id'));
        }
    }

    public function down(): void
    {
    }
};
