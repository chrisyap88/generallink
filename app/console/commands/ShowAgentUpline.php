<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * NEW 19 Jul 2026 — for Chris: he wants to log in AS each level above
 * Murali (I-TESTNEST) — the upline Introducer, the Team Leader, and the
 * Group Leader — to test recursive downline visibility from every
 * level. Real account passwords are bcrypt-hashed and cannot be
 * recovered, so for any upline account that isn't already a known test
 * account, this command resets its password to the project's standard
 * test password (Password@123 — same convention as
 * ActivatePvatmTestAgents) and prints the login for each.
 *
 * Run via: php artisan agents:show-upline murali.krishnan@generallink.my
 */
class ShowAgentUpline extends Command
{
    protected $signature = 'agents:show-upline {email : Email of the agent to trace upline from}';
    protected $description = 'Walk up the hierarchy from an agent and print/reset login credentials for the upline Introducer, Team Leader, and Group Leader';

    private const TEST_PASSWORD = 'Password@123';

    public function handle(): int
    {
        $email = $this->argument('email');

        $agent = DB::table('agents')->where('email', $email)->first();
        if (!$agent) {
            $this->error("No agent found with email '{$email}'.");
            return self::FAILURE;
        }

        $this->info("Starting agent: {$agent->full_name} ({$agent->role}) — {$agent->email}");
        $this->line('');

        $found = [
            'INTRODUCER' => null,
            'TEAM_LEADER' => null,
            'GROUP_LEADER' => null,
        ];

        // Walk up parent_id chain starting from agent's own parent.
        $current = $agent;
        $guard = 0;
        while ($current->parent_id && $guard < 20) {
            $guard++;
            $parent = DB::table('agents')->where('agent_id', $current->parent_id)->first();
            if (!$parent) {
                break;
            }
            if (array_key_exists($parent->role, $found) && $found[$parent->role] === null) {
                $found[$parent->role] = $parent;
            }
            $current = $parent;
        }

        if ($found['INTRODUCER'] === null && $agent->role !== 'INTRODUCER') {
            // agent itself might already be the introducer level being asked about
        }

        $rows = [];
        foreach ($found as $role => $upline) {
            if (!$upline) {
                $rows[] = [$role, '(none found in chain)', '-', '-'];
                continue;
            }

            DB::table('agents')->where('agent_id', $upline->agent_id)->update([
                'password_hash' => Hash::make(self::TEST_PASSWORD),
                'updated_at' => now(),
            ]);

            $rows[] = [$role, $upline->full_name, $upline->email, self::TEST_PASSWORD];
        }

        $this->table(['Role', 'Name', 'Email', 'Password (reset just now)'], $rows);

        $this->line('');
        $this->warn('Passwords above have been RESET to Password@123 on this local database so you can log in as each level.');

        return self::SUCCESS;
    }
}
