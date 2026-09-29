<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 23 Jul 2026 — fixes a real, site-wide data bug found while
// building the demo transaction seeder for Chris Yap.
//
// ROOT CAUSE: two Admin-facing agent-creation paths —
// SpecialGroupController::createAgent() (Organization Rewards Group
// "Add Team Leader" / "Add Introducer") and
// BatchRegistrationService::commitBatch() (batch upload) — never set
// hierarchy_path on the new agent row. It silently defaulted to the
// column default '/'. parent_id was always set correctly, but
// hierarchy_path was not — and hierarchy_path is what several
// features use to find "everyone under me" via a
// LIKE '%/{my_id}/%' match: Renewal Forecast, Data Scope visibility,
// Document Credit Wallet, and this new demo seeder. That's why
// Chris Yap's real, already-established Team Leaders/Introducers
// were invisible to those features even though they show up fine
// everywhere else (list screens, direct parent/child lookups).
//
// Only agents self-registered via a referral link/QR
// (HierarchyService::registerUnderSponsor(), the ONLY other creation
// path) had hierarchy_path set correctly all along — that's why this
// bug wasn't caught earlier.
//
// This command is the one-time repair: it recomputes hierarchy_path
// for EVERY agent by walking each one's parent_id chain from
// scratch (not by trusting any existing hierarchy_path), so it fixes
// the problem regardless of how broken the current data is. The two
// creation paths above have already been fixed to set it correctly
// on every NEW agent going forward — this command only needs to be
// run once to repair EXISTING agents.
//
// Run via: php artisan agents:rebuild-hierarchy-paths
// Shows a preview of how many agents will change (with a few named
// examples) and asks for confirmation before writing anything.
// Safe to re-run any time — agents that are already correct are
// simply skipped.
// -------------------------------------------------------
class RebuildAgentHierarchyPaths extends Command
{
    protected $signature = 'agents:rebuild-hierarchy-paths';
    protected $description = 'Recompute hierarchy_path for every agent by walking parent_id chains (one-time repair for agents created via Admin screens that never set it)';

    public function handle(): int
    {
        $agents = DB::table('agents')->get(['agent_id', 'full_name', 'role', 'parent_id', 'hierarchy_path']);

        if ($agents->isEmpty()) {
            $this->info('No agents found.');
            return self::SUCCESS;
        }

        $byId = $agents->keyBy('agent_id');
        $newPaths = [];
        $changes = [];
        $cycles = [];

        foreach ($agents as $agent) {
            $chain = [];
            $seen = [];
            $current = $agent;

            while ($current) {
                if (isset($seen[$current->agent_id])) {
                    // Circular parent_id reference — should never happen,
                    // but guard against an infinite loop and flag it
                    // instead of silently corrupting data.
                    $cycles[] = $agent->full_name . ' (' . $agent->agent_id . ')';
                    $chain = null;
                    break;
                }
                $seen[$current->agent_id] = true;
                $chain[] = $current->agent_id;
                $current = $current->parent_id ? ($byId[$current->parent_id] ?? null) : null;
            }

            if ($chain === null) {
                continue;
            }

            $path = '/' . implode('/', array_reverse($chain)) . '/';
            $newPaths[$agent->agent_id] = $path;

            if ($path !== $agent->hierarchy_path) {
                $changes[] = [
                    'agent_id' => $agent->agent_id,
                    'name'     => $agent->full_name,
                    'role'     => $agent->role,
                    'old'      => $agent->hierarchy_path,
                    'new'      => $path,
                ];
            }
        }

        if (!empty($cycles)) {
            $this->error('Found ' . count($cycles) . ' agent(s) with a circular parent_id reference — skipped, not touched:');
            foreach (array_slice($cycles, 0, 10) as $c) {
                $this->line('  - ' . $c);
            }
            $this->line('');
        }

        if (empty($changes)) {
            $this->info('All agents already have a correct hierarchy_path — nothing to fix.');
            return self::SUCCESS;
        }

        $this->info(count($changes) . ' agent(s) will have their hierarchy_path corrected:');
        $this->line('');
        foreach (array_slice($changes, 0, 15) as $c) {
            $this->line("  {$c['name']} ({$c['role']})");
            $this->line("    old: " . ($c['old'] === '/' ? '/ (never set)' : $c['old']));
            $this->line("    new: {$c['new']}");
        }
        if (count($changes) > 15) {
            $this->line('  ... and ' . (count($changes) - 15) . ' more.');
        }
        $this->line('');
        $this->warn('This only corrects hierarchy_path. It does not change parent_id, role, status, or anything else.');
        $this->line('');

        if (!$this->confirm('Apply these ' . count($changes) . ' correction(s) now?', false)) {
            $this->warn('Cancelled — nothing changed.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                DB::table('agents')->where('agent_id', $c['agent_id'])->update([
                    'hierarchy_path' => $c['new'],
                    'updated_at'     => now(),
                ]);
            }
        });

        $this->info('Done — ' . count($changes) . ' agent(s) corrected.');
        return self::SUCCESS;
    }
}
