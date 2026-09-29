<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// NEW 25 Aug 2026 — per Chris: a clean way to see how many agent logins
// are assigned to each CBE level (HQ/State/Temple) without wrestling
// with Windows cmd's quoting of `php artisan tinker --execute="..."`
// one-liners (the `=>` in an arrow function keeps tripping over cmd's
// `>` redirection parsing).
class CbeAgentCounts extends Command
{
    protected $signature = 'cbe:agent-counts';
    protected $description = 'Show how many agent logins are assigned to each CBE level (HQ/State/Temple), plus a Branch (city) breakdown.';

    public function handle(): int
    {
        $byLevel = DB::table('agents as a')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
            ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->where('a.is_deleted', false)
            ->select('l.level_name', DB::raw('count(*) as agent_count'))
            ->groupBy('l.level_name')
            ->orderBy('l.level_order')
            ->get();

        $this->info('Agent logins by level:');
        if ($byLevel->isEmpty()) {
            $this->line('  (none yet — no agent logins are assigned to any CBE node)');
        } else {
            $this->table(['Level', 'Agent Logins'], $byLevel->map(fn ($r) => [$r->level_name, $r->agent_count])->toArray());
        }

        $byCity = DB::table('agents as a')
            ->join('cbe_hierarchy_nodes as n', 'n.node_id', '=', 'a.cbe_node_id')
            ->where('a.is_deleted', false)
            ->whereNotNull('n.city')
            ->select('n.city', DB::raw('count(*) as agent_count'))
            ->groupBy('n.city')
            ->orderBy('n.city')
            ->get();

        $this->newLine();
        $this->info('Agent logins by Branch (city, Temple-level only):');
        if ($byCity->isEmpty()) {
            $this->line('  (none yet)');
        } else {
            $this->table(['City / Branch', 'Agent Logins'], $byCity->map(fn ($r) => [$r->city, $r->agent_count])->toArray());
        }

        $totalNodes = DB::table('cbe_hierarchy_nodes')->count();
        $totalAgentsWithNode = DB::table('agents')->whereNotNull('cbe_node_id')->where('is_deleted', false)->count();
        $this->newLine();
        $this->line("Total CBE nodes (places, not people): {$totalNodes}");
        $this->line("Total agent logins assigned to any CBE node: {$totalAgentsWithNode}");

        return self::SUCCESS;
    }
}
