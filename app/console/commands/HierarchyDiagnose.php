<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris ran the Earning Income report and asked why
// most rows show no Group Leader / Team Leader / Introducer at all
// (not even the closer's own name if they ARE one of those roles).
// This command answers that with real numbers instead of guessing:
// it shows how many agents actually have a sponsor (parent_id) set,
// broken down by role, and — critically — which ROLE actually closed
// each sales transaction, since a transaction closed by an ADMIN
// account (e.g. test/demo data entered directly by Admin rather than
// through a real Introducer/TL/GL account) will correctly show blank
// for all three tiers, because Admin isn't any of those three roles.
// -------------------------------------------------------
class HierarchyDiagnose extends Command
{
    protected $signature = 'hierarchy:diagnose';
    protected $description = 'Explain why sales transactions may be missing Group Leader / Team Leader / Introducer in the earning income report';

    public function handle(): int
    {
        $this->info('========================================================');
        $this->info(' AGENTS — by role, and whether they have a sponsor (parent_id)');
        $this->info('========================================================');

        $roles = DB::table('agents')->where('is_deleted', false)->distinct()->pluck('role');
        $this->table(
            ['Role', 'Total Agents', 'Has Sponsor (parent_id set)', 'No Sponsor (parent_id NULL)'],
            $roles->map(function ($role) {
                $total = DB::table('agents')->where('is_deleted', false)->where('role', $role)->count();
                $withParent = DB::table('agents')->where('is_deleted', false)->where('role', $role)->whereNotNull('parent_id')->count();
                return [$role, $total, $withParent, $total - $withParent];
            })
        );
        $this->line('');
        $this->line('  A blank Group Leader / Team Leader / Introducer column in the earning income');
        $this->line('  report means: walking upward from whoever closed that sale, no agent with that');
        $this->line('  role was found before running out of sponsors (parent_id = NULL). That is a');
        $this->line('  REAL reflection of how that agent was registered, not a bug in the report.');
        $this->line('');

        $this->info('========================================================');
        $this->info(' SALES TRANSACTIONS — by the ROLE of whoever actually closed the sale');
        $this->info('========================================================');

        $txnRoleCounts = DB::table('sales_transactions as st')
            ->leftJoin('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->where('st.is_deleted', false)
            ->selectRaw('COALESCE(a.role, "(agent record missing)") as closer_role, COUNT(*) as cnt')
            ->groupBy('closer_role')
            ->orderByDesc('cnt')
            ->get();

        $this->table(['Role of Agent who Submitted the Sale', 'Number of Transactions'], $txnRoleCounts->map(fn($r) => [$r->closer_role, $r->cnt]));
        $this->line('');
        $this->line('  IMPORTANT: only INTRODUCER, TEAM_LEADER and GROUP_LEADER roles can ever fill');
        $this->line('  those three report columns. If the row above shows a lot of "ADMIN" (or any');
        $this->line('  other role), every one of those transactions will correctly show blank for');
        $this->line('  Group Leader/Team Leader/Introducer, because the person who submitted it was');
        $this->line('  not actually acting as an Introducer/TL/GL in the org chart at all.');
        $this->line('');

        $this->info('========================================================');
        $this->info(' SAMPLE — first 15 transactions with a closer role of INTRODUCER/TEAM_LEADER/GROUP_LEADER');
        $this->info(' but STILL missing an upline tier (to catch any real gaps, separate from the ADMIN case above)');
        $this->info('========================================================');

        $sampleTxns = DB::table('sales_transactions as st')
            ->join('agents as a', 'st.agent_id', '=', 'a.agent_id')
            ->where('st.is_deleted', false)
            ->whereIn('a.role', ['INTRODUCER', 'TEAM_LEADER', 'GROUP_LEADER'])
            ->orderBy('st.created_at')
            ->get(['st.policy_number', 'a.agent_id', 'a.full_name', 'a.role', 'a.parent_id']);

        $shown = 0;
        foreach ($sampleTxns as $txn) {
            if ($shown >= 15) break;

            // Walk upline the same way the report does
            $found = ['GROUP_LEADER' => null, 'TEAM_LEADER' => null, 'INTRODUCER' => null];
            $current = DB::table('agents')->where('agent_id', $txn->agent_id)->first();
            $depth = 0;
            while ($current && $depth < 50) {
                $depth++;
                if (isset($found[$current->role]) && $found[$current->role] === null) {
                    $found[$current->role] = $current->full_name;
                }
                $current = $current->parent_id ? DB::table('agents')->where('agent_id', $current->parent_id)->first() : null;
            }

            $missing = [];
            foreach (['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'] as $tier) {
                if ($found[$tier] === null) $missing[] = $tier;
            }
            if (empty($missing)) continue; // this one's fully populated — not interesting for this sample

            $shown++;
            $this->line("  Policy {$txn->policy_number} — closed by {$txn->full_name} ({$txn->role}) — missing: " . implode(', ', $missing));
        }
        if ($shown === 0) {
            $this->line('  (none found — every Introducer/TL/GL-closed transaction already has a full upline chain)');
        }

        return self::SUCCESS;
    }
}
