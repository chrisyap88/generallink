<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris wants to reorganize the sales transactions to
// simulate earning income calculation cleanly: (1) fix the Introducer
// -> Team Leader -> Group Leader hierarchy, (2) make sure every
// transaction has a sales amount, (3) reset all earning income to
// zero, (4) get the vendor+product combinations grouped so he can set
// up proper %. Because he confirmed this is ALL real customer data
// (not test data), this audit deliberately does NOT invent or guess
// any real numbers (a real premium amount) or real relationships (who
// really sponsors whom) — those need a human who actually knows the
// real answer. What it DOES do is show exactly what's missing/broken
// today, so nothing has to be guessed blindly.
// -------------------------------------------------------
class SimulationAudit extends Command
{
    protected $signature = 'simulation:audit';
    protected $description = 'Read-only audit before reorganizing sales transactions/earning income: missing sales amounts, broken hierarchy, current earning income totals, and vendor+product groupings';

    public function handle(): int
    {
        $this->section1MissingAmounts();
        $this->section2BrokenHierarchy();
        $this->section3CurrentEarningIncome();
        $this->section4VendorProductGroups();

        $this->line('');
        $this->info('Audit complete. Nothing was changed — this is read-only.');
        return self::SUCCESS;
    }

    private function section1MissingAmounts(): void
    {
        $this->info('========================================================');
        $this->info(' 1. SALES TRANSACTIONS MISSING A SALES AMOUNT');
        $this->info('========================================================');

        $missing = DB::table('sales_transactions')
            ->where('is_deleted', false)
            ->where(function ($q) {
                $q->whereNull('premium_amount')->orWhere('premium_amount', '<=', 0);
            })
            ->get(['policy_id', 'policy_number', 'document_reference_number', 'customer_id', 'created_at']);

        if ($missing->isEmpty()) {
            $this->line('  None found — every sales transaction already has a sales amount greater than zero.');
            $this->line('');
            return;
        }

        $this->warn("  Found {$missing->count()} transaction(s) with no sales amount:");
        foreach ($missing as $row) {
            $customer = DB::table('customers')->where('customer_id', $row->customer_id)->value('full_name');
            // Check if a real number might already be sitting in the
            // extracted document attributes under a different field —
            // worth checking before assuming it needs to be typed fresh.
            $attrs = DB::table('sales_transaction_attributes')
                ->where('policy_id', $row->policy_id)
                ->where('attribute_name', 'like', '%premium%')
                ->orWhere(function ($q) use ($row) {
                    $q->where('policy_id', $row->policy_id)->where('attribute_name', 'like', '%amount%');
                })
                ->get(['attribute_name', 'attribute_value']);

            $this->line("  - Policy {$row->policy_number} (Ref: " . ($row->document_reference_number ?? 'none') . "), Customer: " . ($customer ?? 'unknown'));
            if ($attrs->isNotEmpty()) {
                foreach ($attrs as $a) {
                    $this->line("      Possible figure on file under '{$a->attribute_name}': {$a->attribute_value}");
                }
            } else {
                $this->line('      No extracted figure on file under any premium/amount attribute — needs the real figure entered manually.');
            }
        }
        $this->line('');
        $this->warn('  These need the REAL sales amount from the actual policy/document — I will not invent a figure for a real customer.');
        $this->line('  Please fill these in via Sales Transaction > Edit for each one listed above.');
        $this->line('');
    }

    private function section2BrokenHierarchy(): void
    {
        $this->info('========================================================');
        $this->info(' 2. AGENTS WITH NO SPONSOR (broken Introducer -> TL -> GL chain)');
        $this->info('========================================================');

        $orphans = DB::table('agents')
            ->where('is_deleted', false)
            ->whereNull('parent_id')
            ->where('role', '!=', 'ADMIN')
            ->orderBy('role')
            ->orderBy('full_name')
            ->get(['agent_id', 'full_name', 'role', 'status', 'group_id', 'created_at']);

        if ($orphans->isEmpty()) {
            $this->line('  None found — every non-Admin agent already has a sponsor (parent_id set).');
            $this->line('');
            return;
        }

        $this->warn("  Found {$orphans->count()} agent(s) with no sponsor at all:");
        $byRole = $orphans->groupBy('role');
        foreach ($byRole as $role => $group) {
            $this->line("  {$role} ({$group->count()}):");
            foreach ($group as $agent) {
                $txnCount = DB::table('sales_transactions')->where('agent_id', $agent->agent_id)->where('is_deleted', false)->count();
                $this->line("    - {$agent->full_name} (status: {$agent->status}) — has {$txnCount} sales transaction(s) attributed to them");
            }
        }
        $this->line('');
        $this->warn('  I cannot invent who really sponsors these agents — that\'s a real business relationship only you know.');
        $this->line('  GeneralLink already has a screen for exactly this: Admin > Master File Maintenance > Pending Assignment');
        $this->line('  (assigns an unassigned Introducer under a Group Leader — one-time, permanent).');
        $this->line('  NOTE: that screen currently assigns straight to a Group Leader, with no Team Leader in between —');
        $this->line('  if any of these agents should actually sit under a specific Team Leader instead, let me know and I can');
        $this->line('  either walk you through the existing Network screens to place them there, or extend that screen to');
        $this->line('  let you pick a Team Leader too.');
        $this->line('');
    }

    private function section3CurrentEarningIncome(): void
    {
        $this->info('========================================================');
        $this->info(' 3. CURRENT EARNING INCOME ON FILE (what a reset would wipe)');
        $this->info('========================================================');

        $counts = DB::table('commission_transactions')
            ->selectRaw('status, COUNT(*) as cnt, SUM(commission_amount) as total')
            ->groupBy('status')
            ->get();

        if ($counts->isEmpty()) {
            $this->line('  No earning income rows exist yet — nothing to reset.');
            $this->line('');
            return;
        }

        foreach ($counts as $row) {
            $this->line("  {$row->status}: {$row->cnt} row(s), totalling RM " . number_format($row->total, 2));
        }

        $walletTotal = DB::table('agents')->where('is_deleted', false)->sum('commission_balance');
        $walletAgents = DB::table('agents')->where('is_deleted', false)->where('commission_balance', '>', 0)->count();
        $this->line('');
        $this->line("  Agent wallet balances (commission_balance) currently sum to RM " . number_format($walletTotal, 2) . " across {$walletAgents} agent(s).");
        $this->warn('  A full reset would delete every row above AND set every one of those wallet balances back to RM 0.');
        $this->line('');
    }

    private function section4VendorProductGroups(): void
    {
        $this->info('========================================================');
        $this->info(' 4. VENDOR + PRODUCT COMBINATIONS IN USE (for setting up %)');
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
        $this->line('');
    }
}
