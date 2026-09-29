<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

// NEW 25 Aug 2026 — per Chris: "a program for me to generate by phases"
// — the phased officer-provisioning tool. Given a node (by its
// node_code, e.g. SGR-KLA-0001, or HQ/State node codes) and a role
// (DIRECTOR/FINANCE/MEMBERSHIP), creates (or reuses) a login and marks
// them as that node's officer. Never generates all nodes' officers at
// once — run this one node/role at a time, whenever that unit is ready
// to come online, exactly per Chris's phased rollout requirement.
class ProvisionCbeNodeOfficer extends Command
{
    protected $signature = 'cbe:provision-officer
        {node_code : The node_code of the HQ/State/Branch/Temple to provision (see cbe_hierarchy_nodes.node_code)}
        {role : DIRECTOR, FINANCE, or MEMBERSHIP}
        {name : Full name of the officer}
        {email : Login email}
        {--password=Password@123 : Login password}';

    protected $description = "Create or assign this node's Director/Finance/Membership officer login — one node/role at a time.";

    public function handle(): int
    {
        $nodeCode = strtoupper($this->argument('node_code'));
        $role = strtoupper($this->argument('role'));
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password');

        if (! in_array($role, ['DIRECTOR', 'FINANCE', 'MEMBERSHIP'], true)) {
            $this->error('Role must be one of: DIRECTOR, FINANCE, MEMBERSHIP');
            return self::FAILURE;
        }

        $node = DB::table('cbe_hierarchy_nodes')->where('node_code', $nodeCode)->first();
        if (! $node) {
            $this->error("No node found with node_code '{$nodeCode}'.");
            return self::FAILURE;
        }

        $agent = DB::table('agents')->where('email', $email)->first();
        if ($agent) {
            $agentId = $agent->agent_id;
            DB::table('agents')->where('agent_id', $agentId)->update(['cbe_node_id' => $node->node_id, 'updated_at' => now()]);
            $this->info("Reused existing login: {$email}");
        } else {
            $agentId = (string) Str::uuid();
            DB::table('agents')->insert([
                'agent_id'               => $agentId,
                'member_code'            => null,
                'agent_code'             => 'CBE-' . strtoupper(substr($role, 0, 3)) . '-' . strtoupper(Str::random(5)),
                'full_name'              => $name,
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
            $this->info("Created new login: {$email} / {$password}");
        }

        // Only one active officer per (node, role) at a time — deactivate
        // whoever held it before, keep their history, then appoint this one.
        DB::table('cbe_node_officers')
            ->where('node_id', $node->node_id)->where('role', $role)->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);

        DB::table('cbe_node_officers')->insert([
            'officer_id'      => (string) Str::uuid(),
            'node_id'         => $node->node_id,
            'group_label_id'  => $node->group_label_id,
            'role'            => $role,
            'agent_id'        => $agentId,
            'is_active'       => true,
            'appointed_at'    => now()->toDateString(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->newLine();
        $this->info('Done.');
        $this->line('  Node: ' . $node->node_name . ($node->node_name_zh ? " ({$node->node_name_zh})" : ''));
        $this->line('  Role: ' . $role);
        $this->line('  Login email: ' . $email);
        if (! $agent) {
            $this->line('  Login password: ' . $password);
        }

        return self::SUCCESS;
    }
}
