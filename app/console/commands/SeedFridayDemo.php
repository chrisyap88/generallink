<?php

namespace App\Console\Commands;

use App\Services\CbeAccountingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// NEW 25 Aug 2026 — per Chris: sets up two concrete demo scenarios for
// his Friday presentation to Klang Tao's Chairman and Rotary Club
// Uptown —
// 1. Rotary Club Uptown: a brand-new, single-unit FREE CBE organization
//    (no HQ/State/Branch — exactly the simple case Chris described),
//    with its own 3 officer logins created fresh.
// 2. 马来西亚道教总会 set to PAID, with 2 real Klang temples getting
//    their 3 officers each — deliberately NOT the whole 591-temple
//    organization, to show the phased-rollout story Chris asked for
//    ("it could be ONLY Klang branch start first").
// Safe to re-run — reuses existing rows/logins instead of duplicating.
class SeedFridayDemo extends Command
{
    protected $signature = 'cbe:seed-friday-demo';
    protected $description = 'Set up Friday demo data: Rotary Club Uptown (FREE) + 2 Klang temples under 马来西亚道教总会 (PAID), each with their 3 officer logins.';

    private function provisionOfficer(string $nodeId, string $groupLabelId, string $role, string $name, string $email, string $password): void
    {
        $agent = DB::table('agents')->where('email', $email)->first();
        if ($agent) {
            $agentId = $agent->agent_id;
            DB::table('agents')->where('agent_id', $agentId)->update(['cbe_node_id' => $nodeId, 'updated_at' => now()]);
        } else {
            $agentId = (string) Str::uuid();
            DB::table('agents')->insert([
                'agent_id' => $agentId,
                'member_code' => null,
                'agent_code' => 'CBE-' . strtoupper(substr($role, 0, 3)) . '-' . strtoupper(Str::random(5)),
                'full_name' => $name,
                'email' => $email,
                'password_hash' => Hash::make($password),
                'phone' => '+60100000000',
                'role' => 'GROUP_LEADER',
                'status' => 'ACTIVE',
                'parent_id' => null,
                'hierarchy_path' => "/{$agentId}/",
                'group_id' => null,
                'cbe_node_id' => $nodeId,
                'recruitable_tier_depth' => 0,
                'recruitment_blocked' => 0,
                'qr_code_token' => Str::random(40),
                'commission_balance' => 0,
                'email_verified_at' => now(),
                'security_phrase_set' => true,
                'is_deleted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('cbe_node_officers')->where('node_id', $nodeId)->where('role', $role)->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('cbe_node_officers')->insert([
            'officer_id' => (string) Str::uuid(),
            'node_id' => $nodeId,
            'group_label_id' => $groupLabelId,
            'role' => $role,
            'agent_id' => $agentId,
            'is_active' => true,
            'appointed_at' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->line("  {$role}: {$email} / {$password}  ({$name})");
    }

    public function handle(): int
    {
        $password = 'Demo@2026';

        // ---------- 1. Rotary Club Uptown (FREE, single unit) ----------
        $this->info('Setting up Rotary Club Uptown (FREE, single-unit)...');

        $rcuGroup = DB::table('group_labels')->where('group_name', 'Rotary Club Uptown')->first();
        if (! $rcuGroup) {
            $rcuGroupId = (string) Str::uuid();
            DB::table('group_labels')->insert([
                'group_label_id' => $rcuGroupId,
                'group_name' => 'Rotary Club Uptown',
                'description' => 'Demo CBE organization — single-unit, no HQ/State/Branch levels.',
                'group_type' => 'CBE',
                'promotion_demotion_enabled' => false,
                'subscription_tier' => 'FREE',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $rcuGroupId = $rcuGroup->group_label_id;
            DB::table('group_labels')->where('group_label_id', $rcuGroupId)->update(['subscription_tier' => 'FREE']);
        }

        $rcuLevel = DB::table('cbe_hierarchy_levels')->where('group_label_id', $rcuGroupId)->first();
        if (! $rcuLevel) {
            DB::table('cbe_hierarchy_levels')->insert([
                'level_id' => (string) Str::uuid(),
                'group_label_id' => $rcuGroupId,
                'level_order' => 1,
                'level_name' => 'Club',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $rcuNode = DB::table('cbe_hierarchy_nodes')->where('group_label_id', $rcuGroupId)->whereNull('parent_node_id')->first();
        if (! $rcuNode) {
            $rcuNodeId = (string) Str::uuid();
            $rcuLevelRow = DB::table('cbe_hierarchy_levels')->where('group_label_id', $rcuGroupId)->first();
            DB::table('cbe_hierarchy_nodes')->insert([
                'node_id' => $rcuNodeId,
                'node_code' => 'RCU-0001',
                'group_label_id' => $rcuGroupId,
                'level_id' => $rcuLevelRow->level_id,
                'parent_node_id' => null,
                'node_name' => 'Rotary Club Uptown',
                'node_name_zh' => null,
                'city' => 'Petaling Jaya',
                'hierarchy_path' => "/{$rcuNodeId}/",
                'display_order' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            $rcuNodeId = $rcuNode->node_id;
        }

        CbeAccountingService::ensureChartOfAccounts($rcuGroupId);

        $this->provisionOfficer($rcuNodeId, $rcuGroupId, 'DIRECTOR', 'Rotary Uptown President (Demo)', 'president@rotaryuptown-demo.my', $password);
        $this->provisionOfficer($rcuNodeId, $rcuGroupId, 'FINANCE', 'Rotary Uptown Treasurer (Demo)', 'treasurer@rotaryuptown-demo.my', $password);
        $this->provisionOfficer($rcuNodeId, $rcuGroupId, 'MEMBERSHIP', 'Rotary Uptown Membership Officer (Demo)', 'membership@rotaryuptown-demo.my', $password);

        // ---------- 2. 马来西亚道教总会 — 2 Klang temples (PAID) ----------
        $this->newLine();
        $this->info('Setting up 马来西亚道教总会 — Klang temples (PAID, phased rollout)...');

        $taoGroup = DB::table('group_labels')->where('group_name', 'like', '%道教总会%')->first();
        if (! $taoGroup) {
            $this->error('Could not find 马来西亚道教总会 — run cbe:import-temples first.');
            return self::FAILURE;
        }
        DB::table('group_labels')->where('group_label_id', $taoGroup->group_label_id)->update(['subscription_tier' => 'PAID']);
        CbeAccountingService::ensureChartOfAccounts($taoGroup->group_label_id);

        $klangTemples = DB::table('cbe_hierarchy_nodes')
            ->where('group_label_id', $taoGroup->group_label_id)
            ->where('city', 'Klang')
            ->orderBy('node_name')
            ->limit(2)
            ->get();

        if ($klangTemples->isEmpty()) {
            $this->error('No temples found with city = KLANG.');
            return self::FAILURE;
        }

        foreach ($klangTemples as $i => $temple) {
            $n = $i + 1;
            $this->line($temple->node_name . ($temple->node_name_zh ? " ({$temple->node_name_zh})" : ''));
            $this->provisionOfficer($temple->node_id, $taoGroup->group_label_id, 'DIRECTOR', "Klang Temple {$n} Chairman (Demo)", "chairman{$n}@klangtao-demo.my", $password);
            $this->provisionOfficer($temple->node_id, $taoGroup->group_label_id, 'FINANCE', "Klang Temple {$n} Treasurer (Demo)", "treasurer{$n}@klangtao-demo.my", $password);
            $this->provisionOfficer($temple->node_id, $taoGroup->group_label_id, 'MEMBERSHIP', "Klang Temple {$n} Membership Officer (Demo)", "membership{$n}@klangtao-demo.my", $password);
        }

        $this->newLine();
        $this->info('All done. Every login above uses the same password: ' . $password);
        $this->line('Log in with any of these emails at your normal agent login page, then open "Executive Dashboard" from the sidebar.');

        return self::SUCCESS;
    }
}
