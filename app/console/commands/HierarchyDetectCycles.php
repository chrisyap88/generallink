<?php

namespace App\Console\Commands;

use App\Models\Agent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 30 Jul 2026 — built after commission:recalculate-all appeared to
// hang at 0% on the full 2510-transaction dataset. resolveDistributions()
// in CommissionEngine walks each policy's closer up their parent_id
// chain to find the Introducer/TL/GL — if two agents' parent_id ever
// point at each other in a loop (A -> B -> A), that walk never
// terminates, freezing the whole command on that one transaction with
// zero visible progress.
//
// This command checks EVERY agent's parent_id chain, independently of
// CommissionEngine, and reports:
//   1. Any genuine cycle (an agent reachable again from its own chain)
//   2. Any chain deeper than 20 levels (almost certainly also a data
//      problem — GeneralLink's hierarchy is only 3 roles deep)
// Read-only — makes no changes.
// -------------------------------------------------------
class HierarchyDetectCycles extends Command
{
    protected $signature = 'hierarchy:detect-cycles';
    protected $description = 'Check every agent\'s parent_id chain for circular references or abnormal depth';

    public function handle(): int
    {
        $agents = DB::table('agents')->where('is_deleted', false)->get(['agent_id', 'full_name', 'role', 'parent_id'])
            ->keyBy('agent_id');

        $this->info("Checking {$agents->count()} agent(s) for circular or abnormally deep parent_id chains...");

        $cycles = [];
        $deepChains = [];

        foreach ($agents as $agent) {
            $visited = [];
            $chain = [];
            $current = $agent;
            $depth = 0;

            while ($current) {
                if (isset($visited[$current->agent_id])) {
                    $cycles[] = [
                        'start' => $agent,
                        'chain' => $chain,
                        'loopsBackTo' => $current,
                    ];
                    break;
                }
                $visited[$current->agent_id] = true;
                $chain[] = $current;
                $depth++;

                if ($depth > 20) {
                    $deepChains[] = [
                        'start' => $agent,
                        'depth' => $depth,
                    ];
                    break;
                }

                $current = $current->parent_id ? $agents->get($current->parent_id) : null;
            }
        }

        $this->line('');
        if (empty($cycles)) {
            $this->info('No circular parent_id chains found.');
        } else {
            $this->error(count($cycles) . ' circular chain(s) found:');
            foreach ($cycles as $c) {
                $names = collect($c['chain'])->map(fn($a) => "{$a->full_name} ({$a->role}, {$a->agent_id})")->implode(' -> ');
                $this->line("  Starting from {$c['start']->full_name} ({$c['start']->agent_id}): {$names} -> loops back to {$c['loopsBackTo']->full_name} ({$c['loopsBackTo']->agent_id})");
            }
        }

        $this->line('');
        if (empty($deepChains)) {
            $this->info('No abnormally deep chains found (all under 20 levels).');
        } else {
            $this->warn(count($deepChains) . ' agent(s) with a parent_id chain deeper than 20 levels (worth a manual look):');
            foreach ($deepChains as $d) {
                $this->line("  {$d['start']->full_name} ({$d['start']->agent_id}) — depth {$d['depth']}+");
            }
        }

        return self::SUCCESS;
    }
}
