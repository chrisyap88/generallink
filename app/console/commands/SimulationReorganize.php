<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 29 Jul 2026 — per Chris ("you do everything la"), a single
// one-click command that does as much of the reorganize-for-simulation
// work automatically as can be done SAFELY:
//
//   1. Auto-assigns any agent with no sponsor to their upline, but
//      ONLY where there is exactly ONE unambiguous, already-existing
//      candidate to assign them to (e.g. their group has exactly one
//      Team Leader, or exactly one Group Leader). Never invents a
//      relationship or guesses between multiple candidates — those are
//      listed separately as needing a real human decision.
//   2. Reports (never invents) any sales transaction still missing a
//      real sales amount — a real premium figure for a real customer
//      cannot be fabricated.
//   3. Resets earning income to zero (deletes commission_transactions,
//      zeroes every agent wallet) — already explicitly authorized by
//      Chris, so this runs without an extra confirmation prompt here
//      (unlike the standalone commission:reset command).
//   4. Prints the vendor+product grouping, same as commission:report /
//      simulation:audit, so it's all in one place.
// -------------------------------------------------------
class SimulationReorganize extends Command
{
    protected $signature = 'simulation:reorganize';
    protected $description = 'One-click: auto-fix unambiguous hierarchy gaps, report un-fixable gaps, reset earning income, and show vendor+product groupings';

    public function handle(): int
    {
        $this->step1AutoAssignHierarchy();
        $this->step2ReportMissingAmounts();
        $this->step3ResetEarningIncome();
        $this->step4VendorProductGroups();

        $this->line('');
        $this->info('All done.');
        return self::SUCCESS;
    }

    private function step1AutoAssignHierarchy(): void
    {
        $this->info('========================================================');
        $this->info(' STEP 1 — AUTO-FIX HIERARCHY (only where unambiguous)');
        $this->info('========================================================');

        $orphans = DB::table('agents')
            ->where('is_deleted', false)
            ->whereNull('parent_id')
            ->where('role', '!=', 'ADMIN')
            ->get();

        if ($orphans->isEmpty()) {
            $this->line('  No orphaned agents found — hierarchy already complete.');
            $this->line('');
            return;
        }

        $allGroupLeaders = DB::table('agents')->where('is_deleted', false)->where('role', 'GROUP_LEADER')->get();
        $fixed = 0;
        $skipped = [];

        foreach ($orphans as $agent) {
            $assignedTo = null;

            if ($agent->group_id) {
                // Prefer the most specific tier: if this is an
                // INTRODUCER and their group has exactly one TEAM_LEADER,
                // place them under that TL rather than skipping straight
                // to the GL.
                if ($agent->role === 'INTRODUCER') {
                    $tls = DB::table('agents')->where('is_deleted', false)->where('role', 'TEAM_LEADER')->where('group_id', $agent->group_id)->get();
                    if ($tls->count() === 1) {
                        $assignedTo = $tls->first();
                    }
                }
                if (!$assignedTo) {
                    $gls = DB::table('agents')->where('is_deleted', false)->where('role', 'GROUP_LEADER')->where('group_id', $agent->group_id)->get();
                    if ($gls->count() === 1) {
                        $assignedTo = $gls->first();
                    }
                }
            } else {
                // No group at all — only safe to guess if there is
                // literally just ONE Group Leader in the whole system.
                if ($allGroupLeaders->count() === 1) {
                    $assignedTo = $allGroupLeaders->first();
                }
            }

            if ($assignedTo) {
                $hierarchyPath = ($assignedTo->hierarchy_path ?? '/') . $agent->agent_id . '/';
                DB::table('agents')->where('agent_id', $agent->agent_id)->update([
                    'parent_id'      => $assignedTo->agent_id,
                    'group_id'       => $assignedTo->group_id,
                    'hierarchy_path' => $hierarchyPath,
                    'updated_at'     => now(),
                ]);
                $fixed++;
                $this->line("  Assigned {$agent->full_name} ({$agent->role}) -> under {$assignedTo->full_name} ({$assignedTo->role})");
            } else {
                $skipped[] = $agent;
            }
        }

        $this->line('');
        $this->info("  Auto-assigned: {$fixed}");
        if (!empty($skipped)) {
            $this->warn('  Could NOT auto-assign (ambiguous or no candidate at all) — needs your manual decision via Admin > Pending Assignment:');
            foreach ($skipped as $agent) {
                $this->line("    - {$agent->full_name} ({$agent->role}, status: {$agent->status})");
            }
        }
        $this->line('');
    }

    private function step2ReportMissingAmounts(): void
    {
        $this->info('========================================================');
        $this->info(' STEP 2 — SALES AMOUNT CHECK (cannot invent real figures)');
        $this->info('========================================================');

        $missing = DB::table('sales_transactions')
            ->where('is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('premium_amount')->orWhere('premium_amount', '<=', 0);
            })
            ->get(['policy_number', 'document_reference_number']);

        if ($missing->isEmpty()) {
            $this->line('  None found — every sales transaction already has a sales amount.');
        } else {
            $this->warn("  {$missing->count()} transaction(s) still need a real sales amount entered manually (cannot be auto-filled):");
            foreach ($missing as $row) {
                $this->line("    - Policy {$row->policy_number} (Ref: " . ($row->document_reference_number ?? 'none') . ')');
            }
        }
        $this->line('');
    }

    private function step3ResetEarningIncome(): void
    {
        $this->info('========================================================');
        $this->info(' STEP 3 — RESET EARNING INCOME TO ZERO');
        $this->info('========================================================');

        $rowCount = DB::table('commission_transactions')->count();
        $walletAgents = DB::table('agents')->where('is_deleted', false)->where('commission_balance', '>', 0)->count();

        if ($rowCount === 0 && $walletAgents === 0) {
            $this->line('  Nothing to reset — already clean.');
            $this->line('');
            return;
        }

        DB::transaction(function () {
            DB::table('commission_transactions')->delete();
            DB::table('agents')->where('is_deleted', false)->update(['commission_balance' => 0]);
        });

        $this->line("  Deleted {$rowCount} commission_transactions row(s).");
        $this->line("  Reset {$walletAgents} agent wallet(s) to RM 0.");
        $this->line('  sales_transactions (the real customer policies) were not touched.');
        $this->line('');
    }

    private function step4VendorProductGroups(): void
    {
        $this->info('========================================================');
        $this->info(' STEP 4 — VENDOR + PRODUCT GROUPS (set up % against these)');
        $this->info('========================================================');

        $groups = DB::table('sales_transactions as st')
            ->join('vendors as v', 'st.vendor_id', '=', 'v.vendor_id')
            ->join('products as p', 'st.product_id', '=', 'p.product_id')
            ->where('st.is_deleted', false)
            ->selectRaw('v.vendor_id, v.vendor_name, p.product_id, p.product_name, COUNT(*) as txn_count, SUM(st.premium_amount) as total_premium')
            ->groupBy('v.vendor_id', 'v.vendor_name', 'p.product_id', 'p.product_name')
            ->orderBy('v.vendor_name')
            ->orderBy('p.product_name')
            ->get();

        if ($groups->isEmpty()) {
            $this->line('  No sales transactions found.');
            return;
        }

        foreach ($groups as $g) {
            $structure = DB::table('commission_structures')
                ->where('vendor_id', $g->vendor_id)
                ->where('product_id', $g->product_id)
                ->where('is_active', true)
                ->orderByDesc('valid_from')
                ->first();

            $structureNote = $structure
                ? "HAS a structure (Total {$structure->total_commission_pct}% / Intro {$structure->introducer_pct}% / TL {$structure->team_leader_pct}% / GL {$structure->group_leader_pct}%)"
                : 'NO active structure set up yet';

            $this->line("  {$g->vendor_name} / {$g->product_name} — {$g->txn_count} transaction(s), total premium RM " . number_format($g->total_premium, 2) . " — {$structureNote}");
        }
    }
}
