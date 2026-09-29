<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// NEW 25 Aug 2026 — per Chris: a quick way to get a working test login
// tied to a real Temple, so he can try out the CBE screens (Meeting
// Minutes, Finance, Accounting, etc.) without having to build the full
// CBE member-registration flow first (still pending — see master spec).
// Safe to re-run: if the test email already exists, it just prints the
// existing login instead of creating a duplicate.
class CreateCbeTestAgent extends Command
{
    protected $signature = 'cbe:create-test-agent {--email=cbetest@generallink.my} {--password=Password@123}';
    protected $description = 'Create (or show) a test agent login tied to a real Temple node, for trying out the CBE screens.';

    public function handle(): int
    {
        $email = $this->option('email');
        $password = $this->option('password');

        $existing = DB::table('agents')->where('email', $email)->first();
        if ($existing) {
            $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $existing->cbe_node_id)->first();
            $this->info('Test login already exists:');
            $this->line('  Email: ' . $email);
            $this->line('  Password: (whatever it was set to originally)');
            $this->line('  Assigned to: ' . ($node ? ($node->node_name . ($node->node_name_zh ? " ({$node->node_name_zh})" : '')) : '— no node —'));
            return self::SUCCESS;
        }

        // Pick a real Temple (deepest level) to assign this login to —
        // prefer one that already has a city, so it exercises the Branch
        // drill-down too.
        $node = DB::table('cbe_hierarchy_nodes as n')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->whereNotNull('n.city')
            ->orderBy('n.node_name')
            ->select('n.*')
            ->first();

        if (! $node) {
            $this->error('No Temple nodes found — run cbe:import-temples first.');
            return self::FAILURE;
        }

        $agentId = (string) Str::uuid();
        DB::table('agents')->insert([
            'agent_id'               => $agentId,
            'member_code'            => null,
            'agent_code'             => 'CBE-TEST-01',
            'full_name'              => 'CBE Test Login',
            'email'                  => $email,
            'password_hash'          => Hash::make($password),
            'nric_encrypted'         => encrypt('000000000000'),
            'phone'                  => '+60100000000',
            'role'                   => 'GROUP_LEADER',
            'status'                 => 'ACTIVE',
            'parent_id'              => null,
            'hierarchy_path'         => "/{$agentId}/",
            'group_id'               => null,
            'cbe_node_id'            => $node->node_id,
            'recruitable_tier_depth' => 0,
            'recruitment_blocked'    => 0,
            'qr_code_token'          => Str::random(40),
            'commission_balance'     => 0,
            'email_verified_at'      => now(),
            'security_phrase_set'    => true,
            'is_deleted'             => false,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->info('Test login created:');
        $this->line('  Email: ' . $email);
        $this->line('  Password: ' . $password);
        $this->line('  Assigned to: ' . $node->node_name . ($node->node_name_zh ? " ({$node->node_name_zh})" : '') . ' — ' . $node->city);

        return self::SUCCESS;
    }
}
