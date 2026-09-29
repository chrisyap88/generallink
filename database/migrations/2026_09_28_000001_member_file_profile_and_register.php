<?php

// NEW 28 Sep 2026 — per Chris (master spec §96.21 / §96.22): ONE member file
// for all CBEs.
//  1. agents (the person) gets the full Customer-profile fields plus Nick
//     Name, Gender, Race, Religion, Nationality, Marital Status, Dietary
//     preference, Personal Instructions and PDPA consent.
//  2. member_profile_options — the pick lists (Gender, Race, Religion,
//     Nationality, Marital Status, Dietary, Affiliation Type), maintained
//     in Master File › Member Pick Lists.
//  3. cbe_membership_plans — per CBE group (Public free, Ordinary yearly …).
//  4. Affiliation Register = the existing tables, extended:
//       cbe_group_memberships  = the person ↔ entity link (one per entity)
//       cbe_member_role_tags   = one line per TYPE at that entity, now with
//                                status / from / to / source / plan / paid until
//     Every existing link without a type line gets a MEMBER line (from its
//     joined date), so nothing already recorded is lost or counted twice.
//  5. cbe_membership_payments — fee payments recorded on a Member line.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. person profile
        Schema::table('agents', function (Blueprint $t) {
            $add = function (string $col, callable $def) use ($t) {
                if (! Schema::hasColumn('agents', $col)) {
                    $def($t);
                }
            };
            $add('nick_name', fn ($t) => $t->string('nick_name', 100)->nullable());
            $add('gender_id', fn ($t) => $t->uuid('gender_id')->nullable());
            $add('race_id', fn ($t) => $t->uuid('race_id')->nullable());
            $add('religion_id', fn ($t) => $t->uuid('religion_id')->nullable());
            $add('nationality_id', fn ($t) => $t->uuid('nationality_id')->nullable());
            $add('marital_status_id', fn ($t) => $t->uuid('marital_status_id')->nullable());
            $add('dietary_id', fn ($t) => $t->uuid('dietary_id')->nullable());
            $add('occupation_group_id', fn ($t) => $t->uuid('occupation_group_id')->nullable());
            $add('customer_type_id', fn ($t) => $t->uuid('customer_type_id')->nullable());
            $add('customer_category_id', fn ($t) => $t->uuid('customer_category_id')->nullable());
            $add('source_id', fn ($t) => $t->uuid('source_id')->nullable());
            $add('postcode', fn ($t) => $t->string('postcode', 10)->nullable());
            $add('city', fn ($t) => $t->string('city', 100)->nullable());
            $add('state', fn ($t) => $t->string('state', 100)->nullable());
            $add('personal_instructions', fn ($t) => $t->text('personal_instructions')->nullable());
            $add('pdpa_consent_at', fn ($t) => $t->timestamp('pdpa_consent_at')->nullable());
        });

        // 2. pick lists
        if (! Schema::hasTable('member_profile_options')) {
            Schema::create('member_profile_options', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('list_code', 20);            // GENDER RACE RELIGION NATIONALITY MARITAL DIET AFFTYPE
                $t->string('code', 30);
                $t->string('label', 120);
                $t->string('label_zh', 120)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['list_code', 'code']);
                $t->index(['list_code', 'is_active']);
            });
            $seed = [
                'GENDER' => [['MALE', 'Male', '男'], ['FEMALE', 'Female', '女']],
                'RACE' => [['CHINESE', 'Chinese', '华人'], ['MALAY', 'Malay', '马来人'], ['INDIAN', 'Indian', '印度人'], ['BUMIPUTERA_OTHER', 'Other Bumiputera', '其他土著'], ['OTHER', 'Other', '其他']],
                'RELIGION' => [['TAOISM', 'Taoism', '道教'], ['BUDDHISM', 'Buddhism', '佛教'], ['CHRISTIANITY', 'Christianity', '基督教'], ['CATHOLIC', 'Catholic', '天主教'], ['ISLAM', 'Islam', '伊斯兰教'], ['HINDUISM', 'Hinduism', '印度教'], ['NONE', 'No religion', '无宗教'], ['OTHER', 'Other', '其他']],
                'NATIONALITY' => [['MALAYSIAN', 'Malaysian', '马来西亚'], ['SINGAPOREAN', 'Singaporean', '新加坡'], ['OTHER', 'Other', '其他']],
                'MARITAL' => [['SINGLE', 'Single', '单身'], ['MARRIED', 'Married', '已婚'], ['DIVORCED', 'Divorced', '离婚'], ['WIDOWED', 'Widowed', '丧偶']],
                'DIET' => [['NONE', 'No restriction', '无限制'], ['VEGETARIAN', 'Vegetarian', '素食'], ['VEGAN', 'Vegan', '纯素'], ['NO_BEEF', 'No beef', '不吃牛肉'], ['NO_PORK', 'No pork', '不吃猪肉'], ['HALAL', 'Halal', '清真'], ['OTHER', 'Other', '其他']],
                'AFFTYPE' => [['MEMBER', 'Member', '会员'], ['FOLLOWER', 'Follower', '信众'], ['BELIEVER', 'Believer', '信徒'], ['DONOR', 'Donor', '捐款人'], ['SPONSOR', 'Sponsor', '赞助人'], ['VOLUNTEER', 'Volunteer', '义工'], ['CONSULTANT', 'Advisor', '顾问']],
            ];
            foreach ($seed as $list => $rows) {
                foreach ($rows as $i => [$code, $label, $zh]) {
                    DB::table('member_profile_options')->insert([
                        'id' => (string) Str::uuid(), 'list_code' => $list, 'code' => $code, 'label' => $label, 'label_zh' => $zh,
                        'sort_order' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        // 3. membership plans (per CBE group)
        if (! Schema::hasTable('cbe_membership_plans')) {
            Schema::create('cbe_membership_plans', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('group_label_id');
                $t->string('plan_name', 120);
                $t->decimal('fee', 12, 2)->default(0);
                $t->string('period', 12)->default('FREE');   // FREE YEARLY ONE_TIME LIFETIME
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->index('group_label_id');
            });
        }

        // 4. affiliation register lines (types) on the existing tag table
        Schema::table('cbe_member_role_tags', function (Blueprint $t) {
            $add = function (string $col, callable $def) use ($t) {
                if (! Schema::hasColumn('cbe_member_role_tags', $col)) {
                    $def($t);
                }
            };
            $add('status', fn ($t) => $t->string('status', 10)->default('ACTIVE'));
            $add('from_date', fn ($t) => $t->date('from_date')->nullable());
            $add('to_date', fn ($t) => $t->date('to_date')->nullable());
            $add('source', fn ($t) => $t->string('source', 20)->nullable());   // STAFF QR REGISTRATION IMPORT
            $add('plan_id', fn ($t) => $t->uuid('plan_id')->nullable());
            $add('paid_until', fn ($t) => $t->date('paid_until')->nullable());
            $add('fee_waived', fn ($t) => $t->boolean('fee_waived')->default(false));
        });
        // tag was an ENUM on some installs — widen it to a plain string so the
        // new types (MEMBER, DONOR, SPONSOR, BELIEVER …) fit.
        try {
            DB::statement("ALTER TABLE cbe_member_role_tags MODIFY tag VARCHAR(20) NOT NULL");
        } catch (\Throwable $e) {
            // already a string column
        }
        // every existing link gets a MEMBER line unless it already has one
        foreach (DB::table('cbe_group_memberships')->get(['membership_id', 'status', 'joined_at']) as $m) {
            DB::table('cbe_member_role_tags')->where('membership_id', $m->membership_id)->whereNull('from_date')
                ->update(['from_date' => $m->joined_at ? substr((string) $m->joined_at, 0, 10) : null, 'source' => 'IMPORT']);
            if (! DB::table('cbe_member_role_tags')->where('membership_id', $m->membership_id)->where('tag', 'MEMBER')->exists()) {
                DB::table('cbe_member_role_tags')->insert([
                    'tag_id' => (string) Str::uuid(), 'membership_id' => $m->membership_id, 'tag' => 'MEMBER',
                    'status' => $m->status === 'ACTIVE' ? 'ACTIVE' : 'ENDED',
                    'from_date' => $m->joined_at ? substr((string) $m->joined_at, 0, 10) : null,
                    'source' => 'IMPORT', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // 5. fee payments
        if (! Schema::hasTable('cbe_membership_payments')) {
            Schema::create('cbe_membership_payments', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('tag_id');                       // the Member line
                $t->decimal('amount', 12, 2);
                $t->date('paid_on');
                $t->string('receipt_no', 60)->nullable();
                $t->date('paid_until')->nullable();
                $t->uuid('created_by')->nullable();
                $t->timestamps();
                $t->index('tag_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_membership_payments');
        Schema::dropIfExists('cbe_membership_plans');
        Schema::dropIfExists('member_profile_options');
    }
};
