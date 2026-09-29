<?php

// NEW 28 Sep 2026 — per Chris ("complete all the partial and not done"):
//  - agents.customer_status_id  : Customer Status (Customer Status master) on the member file
//  - member_companies           : ONE company record for all CBEs (SBE / Corporate membership,
//                                 company donors / sponsors); duplicate check by Reg No / name
//  - cbe_member_role_tags.company_id : a Member line on an SBE / Corporate plan belongs to a company
//  - cbe_membership_family      : family members covered by a Family membership line
//  - cbe_donors.company_id      : a company donor linked to its company record
//  - cbe_hierarchy_nodes.join_token : the entity's QR-join code (random, cannot be guessed)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'customer_status_id')) {
            Schema::table('agents', fn (Blueprint $t) => $t->uuid('customer_status_id')->nullable());
        }
        if (! Schema::hasTable('member_companies')) {
            Schema::create('member_companies', function (Blueprint $t) {
                $t->uuid('company_id')->primary();
                $t->string('company_name', 200);
                $t->string('registration_no', 60)->nullable();
                $t->string('phone', 40)->nullable();
                $t->string('email', 150)->nullable();
                $t->text('address')->nullable();
                $t->uuid('contact_agent_id')->nullable();
                $t->uuid('created_by')->nullable();
                $t->timestamps();
                $t->index('company_name');
                $t->index('registration_no');
            });
        }
        if (! Schema::hasColumn('cbe_member_role_tags', 'company_id')) {
            Schema::table('cbe_member_role_tags', fn (Blueprint $t) => $t->uuid('company_id')->nullable());
        }
        if (! Schema::hasTable('cbe_membership_family')) {
            Schema::create('cbe_membership_family', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('principal_tag_id');          // the Family Member line of the main member
                $t->uuid('agent_id');                  // the family member (a person in the member file)
                $t->uuid('member_tag_id')->nullable(); // his own Member line created for it
                $t->string('relationship', 40)->nullable();
                $t->timestamps();
                $t->index('principal_tag_id');
                $t->index('agent_id');
            });
        }
        if (Schema::hasTable('cbe_donors') && ! Schema::hasColumn('cbe_donors', 'company_id')) {
            Schema::table('cbe_donors', fn (Blueprint $t) => $t->uuid('company_id')->nullable());
        }
        if (! Schema::hasColumn('cbe_hierarchy_nodes', 'join_token')) {
            Schema::table('cbe_hierarchy_nodes', fn (Blueprint $t) => $t->string('join_token', 20)->nullable()->unique());
        }
        foreach (DB::table('cbe_hierarchy_nodes')->whereNull('join_token')->pluck('node_id') as $id) {
            DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->update(['join_token' => strtolower(Str::random(12))]);
        }
        // Relationship pick list for family members
        if (Schema::hasTable('member_profile_options') && ! DB::table('member_profile_options')->where('list_code', 'RELATION')->exists()) {
            foreach ([['SPOUSE', 'Spouse', '配偶'], ['CHILD', 'Child', '子女'], ['PARENT', 'Parent', '父母'], ['SIBLING', 'Sibling', '兄弟姐妹'], ['OTHER', 'Other', '其他']] as $i => [$c, $l, $z]) {
                DB::table('member_profile_options')->insert(['id' => (string) Str::uuid(), 'list_code' => 'RELATION', 'code' => $c, 'label' => $l, 'label_zh' => $z,
                    'sort_order' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
    }
};
