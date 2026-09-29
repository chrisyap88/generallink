<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

// -------------------------------------------------------
// NEW 16 Jul 2026 — one-time bulk shortcut for testing: sets every
// PVATM agent's password to a known value and marks them fully
// activated (verified email + ACTIVE status), skipping the normal
// one-by-one "click email link, then set password" flow. Requested by
// Chris to avoid manually verifying ~297 test accounts (14 TLs + ~283
// seeded Introducers, plus CA Teow's own GL account) one at a time.
//
// Deliberately does NOT run the real activation side-effects
// (HierarchyService::evaluateRankChange promotion checks, group-wide
// "New Introducer Joined" notifications) that the real setPassword()
// flow triggers — this is a direct DB shortcut for local testing only,
// not a simulation of real user activations.
//
// Run once via: php artisan pvatm:activate-test-agents
// Add --dry-run to preview without writing anything.
// -------------------------------------------------------
class ActivatePvatmTestAgents extends Command
{
    protected $signature = 'pvatm:activate-test-agents {--dry-run : Preview without changing anything}';
    protected $description = 'Set every PVATM agent\'s password to Password@123 and mark them fully verified + ACTIVE (testing shortcut)';

    private const TEST_PASSWORD = 'Password@123';

    public function handle(): int
    {
        $label = DB::table('group_labels')->where('slug', 'pvatm')->first();
        if (!$label) {
            $this->error('Could not find the PVATM group_label (slug: pvatm).');
            return self::FAILURE;
        }

        $agents = DB::table('agents')
            ->where('group_label_id', $label->group_label_id)
            ->where('is_deleted', false)
            ->get(['agent_id', 'full_name', 'email', 'role', 'status']);

        if ($agents->isEmpty()) {
            $this->error('No agents found under PVATM.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info(($dryRun ? '[DRY RUN] ' : '') . 'Activating ' . $agents->count() . ' PVATM agent(s) with password "' . self::TEST_PASSWORD . '"...');

        $hashed = Hash::make(self::TEST_PASSWORD);
        $count = 0;

        foreach ($agents as $agent) {
            $this->line("  {$agent->full_name} <{$agent->email}> ({$agent->role}) — was {$agent->status}");

            if (!$dryRun) {
                DB::table('agents')->where('agent_id', $agent->agent_id)->update([
                    'password_hash'             => $hashed,
                    'status'                    => 'ACTIVE',
                    'email_verified_at'         => now(),
                    'email_verification_token'  => null,
                    'updated_at'                => now(),
                ]);
            }
            $count++;
        }

        $this->info(($dryRun ? '[DRY RUN] Would update' : 'Updated') . " {$count} agent(s). Password for all: " . self::TEST_PASSWORD);
        return self::SUCCESS;
    }
}
