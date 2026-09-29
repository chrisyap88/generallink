<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // -------------------------------------------------------
        // 1. ADMIN AGENT
        // -------------------------------------------------------
        $adminId = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $adminId,
            'member_code'           => null,
            'agent_code'            => 'ADMIN-001',
            'full_name'             => 'GeneralLink Admin',
            'email'                 => 'admin@generallink.my',
            'password_hash'         => Hash::make('Admin@12345'),
            'nric_encrypted'        => encrypt('000000000000'),
            'phone'                 => '+60123456789',
            'role'                  => 'ADMIN',
            'status'                => 'ACTIVE',
            'parent_id'             => null,
            'hierarchy_path'        => '/',
            'group_id'              => null,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'admin_bank_name'       => 'Maybank',
            'admin_bank_account_encrypted' => encrypt('5621234567890'),
            'commission_balance'    => 0,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // -------------------------------------------------------
        // 2. GROUP & GROUP LEADER
        // -------------------------------------------------------
        $groupId = Str::uuid()->toString();
        $glId    = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $glId,
            'member_code'           => null,   // assigned when group is set up
            'agent_code'            => 'GL-00001',
            'full_name'             => 'Chris Yap',
            'email'                 => 'chrisyap@generallink.my',
            'password_hash'         => Hash::make('Password@123'),
            'nric_encrypted'        => encrypt('800101015678'),
            'phone'                 => '+60112345678',
            'role'                  => 'GROUP_LEADER',
            'status'                => 'ACTIVE',
            'parent_id'             => null,
            'hierarchy_path'        => "/{$glId}/",
            'group_id'              => $groupId,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'bank_name'             => 'CIMB Bank',
            'bank_account_encrypted'=> encrypt('7081234567'),
            'commission_balance'    => 1250.00,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_by'            => $adminId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        DB::table('groups')->insert([
            'group_id'           => $groupId,
            'group_name'         => 'Chris Yap',
            'group_code'         => 'C0001',
            'group_email'        => 'chrisyap@generallink.my',
            'separator_char'     => '-',
            'root_member_suffix' => '0',
            'is_active'          => true,
            'created_by'         => $adminId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // -------------------------------------------------------
        // 3. TEAM LEADER (under GL)
        // -------------------------------------------------------
        $tlId = Str::uuid()->toString();

        DB::table('agents')->insert([
            'agent_id'              => $tlId,
            'member_code'           => 'C0001-0',
            'agent_code'            => 'TL-00001',
            'full_name'             => 'Ahmad Razif',
            'email'                 => 'ahmad.razif@generallink.my',
            'password_hash'         => Hash::make('Password@123'),
            'nric_encrypted'        => encrypt('850215086543'),
            'phone'                 => '+60198765432',
            'role'                  => 'TEAM_LEADER',
            'status'                => 'ACTIVE',
            'parent_id'             => $glId,
            'hierarchy_path'        => "/{$glId}/{$tlId}/",
            'group_id'              => $groupId,
            'recruitable_tier_depth'=> 0,
            'recruitment_blocked'   => 0,
            'qr_code_token'         => Str::random(40),
            'bank_name'             => 'Public Bank',
            'bank_account_encrypted'=> encrypt('3141234567'),
            'commission_balance'    => 680.00,
            'email_verified_at'     => now(),
            'security_phrase_set'   => true,
            'created_by'            => $adminId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        // -------------------------------------------------------
        // 4. INTRODUCERS (under TL)
        // -------------------------------------------------------
        $i1Id = Str::uuid()->toString();
        $i2Id = Str::uuid()->toString();

        $introducers = [
            [
                'agent_id'              => $i1Id,
                'member_code'           => 'C0001-0-1',
                'agent_code'            => 'I-00001',
                'full_name'             => 'Siti Nurhaliza',
                'email'                 => 'siti.nurhaliza@generallink.my',
                'phone'                 => '+60171234567',
                'nric_encrypted'        => encrypt('900303075432'),
                'commission_balance'    => 320.00,
                'recruitable_tier_depth'=> 1,
            ],
            [
                'agent_id'              => $i2Id,
                'member_code'           => 'C0001-0-2',
                'agent_code'            => 'I-00002',
                'full_name'             => 'Rajan Pillai',
                'email'                 => 'rajan.pillai@generallink.my',
                'phone'                 => '+60162345678',
                'nric_encrypted'        => encrypt('880912085321'),
                'commission_balance'    => 190.50,
                'recruitable_tier_depth'=> 1,
            ],
        ];

        foreach ($introducers as $intro) {
            DB::table('agents')->insert(array_merge($intro, [
                'password_hash'         => Hash::make('Password@123'),
                'role'                  => 'INTRODUCER',
                'status'                => 'ACTIVE',
                'parent_id'             => $tlId,
                'hierarchy_path'        => "/{$glId}/{$tlId}/{$intro['agent_id']}/",
                'group_id'              => $groupId,
                'recruitment_blocked'   => 0,
                'qr_code_token'         => Str::random(40),
                'bank_name'             => 'Maybank',
                'bank_account_encrypted'=> encrypt('1234567890'),
                'email_verified_at'     => now(),
                'security_phrase_set'   => true,
                'created_by'            => $adminId,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]));
        }

        // -------------------------------------------------------
        // 5. TIER RECRUITMENT CONFIG (global default = 2)
        // -------------------------------------------------------
        DB::table('tier_recruitment_config')->insert([
            'config_id'    => Str::uuid()->toString(),
            'group_id'     => null,        // Global rule
            'max_tier_limit'=> 2,
            'is_active'    => true,
            'created_by'   => $adminId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // -------------------------------------------------------
        // 6. VENDORS
        // -------------------------------------------------------
        $allianzId = Str::uuid()->toString();
        $aiaId     = Str::uuid()->toString();
        $zurichId  = Str::uuid()->toString();

        $vendors = [
            ['vendor_id' => $allianzId, 'vendor_name' => 'Allianz Malaysia', 'vendor_code' => 'ALZ'],
            ['vendor_id' => $aiaId,     'vendor_name' => 'AIA Malaysia',     'vendor_code' => 'AIA'],
            ['vendor_id' => $zurichId,  'vendor_name' => 'Zurich Insurance',  'vendor_code' => 'ZUR'],
        ];

        foreach ($vendors as $vendor) {
            DB::table('vendors')->insert(array_merge($vendor, [
                'is_active'  => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // -------------------------------------------------------
        // 7. PRODUCTS
        // -------------------------------------------------------
        $motorId = Str::uuid()->toString();
        $paId    = Str::uuid()->toString();
        $fireId  = Str::uuid()->toString();

        $products = [
            ['product_id' => $motorId, 'vendor_id' => $allianzId, 'product_name' => 'Motor Comprehensive', 'product_code' => 'ALZ-MCOMP', 'product_type' => 'MOTOR'],
            ['product_id' => $paId,    'vendor_id' => $aiaId,     'product_name' => 'PA Plus',             'product_code' => 'AIA-PAPLUS','product_type' => 'PERSONAL_ACCIDENT'],
            ['product_id' => $fireId,  'vendor_id' => $zurichId,  'product_name' => 'Householder Fire',    'product_code' => 'ZUR-FIRE',  'product_type' => 'FIRE'],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, [
                'is_active'  => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // -------------------------------------------------------
        // 8. COMMISSION STRUCTURES (fully flexible — no hard-coded %)
        //    Examples only — admin can change these via Master File UI
        // -------------------------------------------------------
        $structures = [
            [
                // Motor: 10% of premium | I=50%, TL=25%, GL=25%
                'vendor_id'           => $allianzId,
                'product_id'          => $motorId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 10.0000,
                'introducer_pct'      => 50.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 25.0000,
            ],
            [
                // PA: 25% of premium | I=60%, TL=25%, GL=15%
                'vendor_id'           => $aiaId,
                'product_id'          => $paId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 25.0000,
                'introducer_pct'      => 60.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 15.0000,
            ],
            [
                // Fire: 15% of premium | I=55%, TL=25%, GL=20%
                'vendor_id'           => $zurichId,
                'product_id'          => $fireId,
                'commission_basis'    => 'PREMIUM_PCT',
                'total_commission_pct'=> 15.0000,
                'introducer_pct'      => 55.0000,
                'team_leader_pct'     => 25.0000,
                'group_leader_pct'    => 20.0000,
            ],
        ];

        foreach ($structures as $structure) {
            DB::table('commission_structures')->insert(array_merge($structure, [
                'structure_id' => Str::uuid()->toString(),
                'valid_from'   => '2026-01-01',
                'valid_to'     => null,
                'is_active'    => true,
                'created_by'   => $adminId,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]));
        }

        // -------------------------------------------------------
        // 9. REWARD POINTS RATES
        // -------------------------------------------------------
        DB::table('reward_points_rates')->insert([
            'rate_id'      => Str::uuid()->toString(),
            'vendor_id'    => null,     // Global rate — applies to all
            'product_id'   => null,
            'points_per_rm'=> 2.5000,   // 2.5 pts per RM1 commission
            'valid_from'   => '2026-01-01',
            'valid_to'     => null,
            'is_active'    => true,
            'created_by'   => $adminId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // -------------------------------------------------------
        // 10. NOTIFICATION TEMPLATES (sample)
        // -------------------------------------------------------
        $templates = [
            ['channel' => 'EMAIL', 'event_type' => 'EMAIL_VERIFICATION',  'subject' => 'Verify your GeneralLink account',      'body_template' => "Hi {name},\n\nPlease click the link below to verify your email:\n{verification_link}\n\nThis link expires in 24 hours."],
            ['channel' => 'EMAIL', 'event_type' => 'RENEWAL_30_DAYS',     'subject' => 'Your policy renews in 30 days',         'body_template' => "Hi {customer_name},\n\nYour policy {policy_number} is due for renewal on {renewal_date}.\n\nContact your agent {agent_name} at {agent_phone} to renew."],
            ['channel' => 'EMAIL', 'event_type' => 'COMMISSION_CREDITED',  'subject' => 'Commission credited to your account',   'body_template' => "Hi {agent_name},\n\nRM {amount} commission has been credited for policy {policy_number}.\n\nYour current balance: RM {balance}"],
            ['channel' => 'SMS',   'event_type' => 'RENEWAL_7_DAYS',      'subject' => null,                                    'body_template' => "GeneralLink: Policy {policy_number} renews in 7 days. Call {agent_phone} to renew now."],
        ];

        foreach ($templates as $tpl) {
            DB::table('notification_templates')->insert(array_merge($tpl, [
                'template_id' => Str::uuid()->toString(),
                'template_name' => $tpl['event_type'].'_'.$tpl['channel'],
                'is_active'   => true,
                'created_by'  => $adminId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]));
        }

        $this->command->info('✅ GeneralLink database seeded successfully.');
        $this->command->info('   Admin login: admin@generallink.my / Admin@12345');
        $this->command->info('   GL login:    chrisyap@generallink.my / Password@123');
        $this->command->info('   TL login:    ahmad.razif@generallink.my / Password@123');
    }
}
