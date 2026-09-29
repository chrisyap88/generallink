<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 23 Jul 2026 — read-only diagnostic, changes nothing. Built to
// pin down exactly why the Sales and Earning Income Forecast's
// Contributor Ranking numbers don't add up to the summary card total
// for a given Team Leader (e.g. card says 18, but Peter Yong (1) +
// Azrul Nizam (2) + Grace Tan (0) + Tan Boon Hwa Personal only add up
// to a handful, not 18).
//
// This walks the SAME two techniques the real controller uses —
// parent_id (always reliable) and hierarchy_path (only reliable if
// every agent's hierarchy_path was correctly computed) — side by
// side for one named agent's whole downline, so any remaining gap
// between them is immediately visible instead of hidden inside a
// dashboard screen.
//
// Run via: php artisan demo:trace-team "Tan Boon Hwa"
// -------------------------------------------------------
class TraceTeamCounts extends Command
{
    protected $signature = 'demo:trace-team {name : Full name of the agent to trace, e.g. "Tan Boon Hwa"}';
    protected $description = 'Read-only diagnostic: shows exactly which agents and transactions are (and are not) being counted under a given agent, to explain any total mismatch on the Forecast screen';

    public function handle(): int
    {
        $name = $this->argument('name');

        $root = DB::table('agents')->where('full_name', $name)->where('is_deleted', false)->first();
        if (!$root) {
            $this->error("No agent named \"{$name}\" found.");
            return self::FAILURE;
        }

        $this->info("Root: {$root->full_name} ({$root->role})");
        $this->line("  agent_id: {$root->agent_id}");
        $this->line("  parent_id: " . ($root->parent_id ?? '(none — top of hierarchy)'));
        $this->line("  hierarchy_path: {$root->hierarchy_path}");
        $this->line('');

        // ---- METHOD A: hierarchy_path LIKE match — what the real
        // dashboard controller uses (nodeAndDescendantIds()).
        $viaPath = DB::table('agents')
            ->where(function ($q) use ($root) {
                $q->where('agent_id', $root->agent_id)->orWhere('hierarchy_path', 'like', '%/' . $root->agent_id . '/%');
            })
            ->where('is_deleted', false)
            ->get(['agent_id', 'full_name', 'role', 'parent_id', 'hierarchy_path']);

        // ---- METHOD B: recursive parent_id walk — independent of
        // hierarchy_path entirely, built fresh here just to compare.
        $all = DB::table('agents')->where('is_deleted', false)->get(['agent_id', 'full_name', 'role', 'parent_id', 'hierarchy_path']);
        $byParent = $all->groupBy('parent_id');
        $viaParentIds = [];
        $queue = [$root->agent_id];
        while (!empty($queue)) {
            $current = array_pop($queue);
            $viaParentIds[] = $current;
            foreach ($byParent->get($current, collect()) as $child) {
                $queue[] = $child->agent_id;
            }
        }
        $viaParent = $all->whereIn('agent_id', $viaParentIds);

        $pathIds = $viaPath->pluck('agent_id')->all();
        $parentIds = $viaParent->pluck('agent_id')->all();
        $missingFromPath = array_diff($parentIds, $pathIds);
        $missingFromParent = array_diff($pathIds, $parentIds);

        $this->info('Downline via hierarchy_path (what the dashboard uses): ' . count($pathIds) . ' agent(s)');
        $this->info('Downline via parent_id chain (independent check): ' . count($parentIds) . ' agent(s)');
        $this->line('');

        if (!empty($missingFromPath)) {
            $this->error('MISMATCH — these agents are really under ' . $name . ' (correct parent_id chain) but hierarchy_path fails to find them:');
            foreach ($all->whereIn('agent_id', $missingFromPath) as $a) {
                $this->line("  - {$a->full_name} ({$a->role}) — hierarchy_path: {$a->hierarchy_path}, parent_id: {$a->parent_id}");
            }
            $this->line('');
        }
        if (!empty($missingFromParent)) {
            $this->error('MISMATCH — these agents are found via hierarchy_path but NOT via the parent_id chain (should not happen):');
            foreach ($all->whereIn('agent_id', $missingFromParent) as $a) {
                $this->line("  - {$a->full_name} ({$a->role}) — hierarchy_path: {$a->hierarchy_path}, parent_id: {$a->parent_id}");
            }
            $this->line('');
        }
        if (empty($missingFromPath) && empty($missingFromParent)) {
            $this->info('No mismatch — both methods agree on the exact same set of agents.');
            $this->line('');
        }

        // ---- Now the actual transaction counts, per agent in the
        // parent_id-verified set, all-time DEMO2026 batch only (so this
        // lines up with what was just seeded).
        $this->info('Sales Transactions per agent (DEMO2026- batch only, all months):');
        $rows = DB::table('sales_transactions')
            ->whereIn('agent_id', $parentIds)
            ->where('document_reference_number', 'like', 'DEMO2026-%')
            ->select('agent_id', DB::raw('COUNT(*) as cnt'), DB::raw('SUM(premium_amount) as total_premium'))
            ->groupBy('agent_id')
            ->get()
            ->keyBy('agent_id');

        $grandTotal = 0;
        foreach ($viaParent as $a) {
            $r = $rows->get($a->agent_id);
            $cnt = $r->cnt ?? 0;
            $grandTotal += $cnt;
            if ($cnt > 0) {
                $this->line("  {$a->full_name} ({$a->role}, parent_id={$a->parent_id}): {$cnt} transaction(s), RM " . number_format($r->total_premium, 2));
            }
        }
        $this->line('');
        $this->info("TOTAL across whole downline (parent_id-verified): {$grandTotal}");

        return self::SUCCESS;
    }
}
