<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 29 Jul 2026 — Chris asked "how do I know your calculation is
// correct, based on the product earning % setup and agent status of
// the respective upline?" This command is the answer: it recomputes
// the expected commission distribution for a policy INDEPENDENTLY
// (reading commission_structures + the real agents table directly,
// not by calling CommissionEngine's own code), then compares that
// independent answer against what CommissionEngine actually stored
// in commission_transactions. If the two ever disagree, this command
// says so explicitly instead of silently trusting the engine.
//
// Deliberately does NOT reuse any private method from CommissionEngine
// — that would just be checking the engine against itself. This is a
// second, separate calculation of the same numbers.
// -------------------------------------------------------
class VerifyCommission extends Command
{
    protected $signature = 'commission:verify {reference?}';
    protected $description = 'Independently recompute a policy\'s expected commission distribution and compare it against what is actually stored, to verify the calculation is correct';

    public function handle(): int
    {
        $reference = $this->argument('reference');

        if (!$reference) {
            $this->showRecentTransactions();
            $reference = $this->ask('Enter a Document Reference Number or Policy ID from the list above to verify (or leave blank to cancel)');
            if (!$reference) {
                return self::SUCCESS;
            }
        }

        $policy = DB::table('sales_transactions')
            ->where('policy_id', $reference)
            ->orWhere('document_reference_number', $reference)
            ->orWhere('policy_number', $reference)
            ->first();

        if (!$policy) {
            $this->error("No sales transaction found matching \"{$reference}\".");
            return self::FAILURE;
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' POLICY');
        $this->info('========================================================');

        $vendor  = DB::table('vendors')->where('vendor_id', $policy->vendor_id)->first();
        $product = DB::table('products')->where('product_id', $policy->product_id)->first();
        $closer  = DB::table('agents')->where('agent_id', $policy->agent_id)->first();

        $this->line("  Policy Number       : {$policy->policy_number}");
        $this->line("  Reference Number    : " . ($policy->document_reference_number ?? '(none)'));
        $this->line("  Vendor              : " . ($vendor->vendor_name ?? '(unknown)'));
        $this->line("  Product             : " . ($product->product_name ?? '(unknown)'));
        $this->line("  Premium Amount      : RM " . number_format($policy->premium_amount, 2));
        $this->line("  Sum Insured         : " . ($policy->sum_insured !== null ? 'RM ' . number_format($policy->sum_insured, 2) : '(none)'));
        $this->line("  Submitted By        : " . ($closer->full_name ?? '(unknown)') . ' (' . ($closer->role ?? '?') . ', status: ' . ($closer->status ?? '?') . ')');
        $this->line("  Transaction Status  : {$policy->status}");
        $this->line('');

        // ---- 1. Independently resolve the commission structure ----
        $structure = DB::table('commission_structures')
            ->where('vendor_id', $policy->vendor_id)
            ->where('product_id', $policy->product_id)
            ->where('is_active', true)
            ->where('valid_from', '<=', $policy->coverage_start)
            ->where(function ($q) use ($policy) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $policy->coverage_start);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (!$structure) {
            $this->warn('No active commission structure found for this vendor + product as of the coverage start date.');
            $this->warn('Expected: no commission_transactions rows exist for this policy at all.');
            $actualCount = DB::table('commission_transactions')->where('policy_id', $policy->policy_id)->count();
            $this->line("Actual commission_transactions rows found: {$actualCount}");
            $this->line($actualCount === 0 ? '=> MATCHES (correctly skipped).' : '=> MISMATCH — rows exist but no structure was found.');
            return self::SUCCESS;
        }

        $this->info('========================================================');
        $this->info(' EARNING INCOME STRUCTURE (% setup, from commission_structures)');
        $this->info('========================================================');
        $this->line("  Basis                  : {$structure->commission_basis}");
        $this->line("  Total Commission %     : {$structure->total_commission_pct}%");
        $this->line("  Introducer %           : {$structure->introducer_pct}%");
        $this->line("  Team Leader %          : {$structure->team_leader_pct}%");
        $this->line("  Group Leader %         : {$structure->group_leader_pct}%");
        $sumParts = bcadd(bcadd($structure->introducer_pct, $structure->team_leader_pct, 4), $structure->group_leader_pct, 4);
        $this->line("  (Introducer + TL + GL % = {$sumParts}%, should equal Total Commission % of {$structure->total_commission_pct}%)");
        if (abs((float)$sumParts - (float)$structure->total_commission_pct) > 0.0001) {
            $this->error('  => WARNING: the three role percentages do NOT sum to the Total Commission % on this structure. Fix this on the Earning Income Structure screen.');
        }
        $this->line('');

        $base = $structure->commission_basis === 'SUM_INSURED_PCT' ? (float)$policy->sum_insured : (float)$policy->premium_amount;
        $pool = round($base * ((float)$structure->total_commission_pct / 100), 4);

        $this->line("  Base amount used (premium or sum insured per basis above) : RM " . number_format($base, 2));
        $this->line("  => Gross Pool = Base x Total Commission % = RM " . number_format($pool, 2));
        $this->line('');

        // ---- 2. Independently walk the upline chain ----
        $this->info('========================================================');
        $this->info(' UPLINE CHAIN (walked from the submitting agent upward)');
        $this->info('========================================================');

        $tiers = [
            'INTRODUCER'   => ['pct' => (float)$structure->introducer_pct,   'agent' => null],
            'TEAM_LEADER'  => ['pct' => (float)$structure->team_leader_pct,  'agent' => null],
            'GROUP_LEADER' => ['pct' => (float)$structure->group_leader_pct, 'agent' => null],
        ];

        $current = $closer;
        $depth = 0;
        while ($current && $depth < 50) {
            $depth++;
            $activeFlag = ($current->status === 'ACTIVE') ? 'ACTIVE' : "NOT ACTIVE (status: {$current->status})";
            $assignedNote = '';
            if (isset($tiers[$current->role]) && $tiers[$current->role]['agent'] === null && $current->status === 'ACTIVE') {
                $tiers[$current->role]['agent'] = $current;
                $assignedNote = "  <-- fills the {$current->role} tier";
            } elseif (isset($tiers[$current->role]) && $tiers[$current->role]['agent'] === null) {
                $assignedNote = "  <-- is {$current->role} but INACTIVE, so does NOT fill this tier";
            }
            $this->line("  [{$depth}] {$current->full_name} — {$current->role}, {$activeFlag}{$assignedNote}");
            $current = $current->parent_id ? DB::table('agents')->where('agent_id', $current->parent_id)->first() : null;
        }

        // Closer-is-Introducer special case, matching CommissionEngine's own rule
        if ($closer->role === 'INTRODUCER' && $tiers['INTRODUCER']['agent'] === null) {
            $tiers['INTRODUCER']['agent'] = $closer;
            $this->line("  (submitting agent themselves fills the INTRODUCER tier)");
        }
        $this->line('');

        // ---- 3. Independently compute expected distribution ----
        $this->info('========================================================');
        $this->info(' EXPECTED DISTRIBUTION (recomputed independently)');
        $this->info('========================================================');

        $expected = [];
        $expectedTotal = 0;
        foreach ($tiers as $role => $data) {
            $amount = round($base * ($data['pct'] / 100), 4);
            if ($amount <= 0) continue;
            if ($data['agent']) {
                $expected[$data['agent']->agent_id][] = [
                    'role' => $role, 'pct' => $data['pct'], 'amount' => $amount,
                    'agent_name' => $data['agent']->full_name, 'breakage' => false,
                ];
                $this->line("  {$role} ({$data['pct']}%) -> {$data['agent']->full_name}: RM " . number_format($amount, 2));
            } else {
                $this->line("  {$role} ({$data['pct']}%) -> no active {$role} in upline -> SYSTEM/breakage: RM " . number_format($amount, 2));
                $expected['__BREAKAGE__'][] = ['role' => $role, 'pct' => $data['pct'], 'amount' => $amount, 'breakage' => true];
            }
            $expectedTotal += $amount;
        }
        $this->line("  Expected total (should equal Gross Pool RM " . number_format($pool, 2) . ") : RM " . number_format($expectedTotal, 2));
        $this->line('');

        // ---- 4. Compare against what's actually stored ----
        $this->info('========================================================');
        $this->info(' ACTUAL commission_transactions ROWS FOR THIS POLICY');
        $this->info('========================================================');

        $actualRows = DB::table('commission_transactions')
            ->where('policy_id', $policy->policy_id)
            ->orderBy('created_at')
            ->get();

        if ($actualRows->isEmpty()) {
            $this->warn('  No commission_transactions rows found for this policy at all.');
        }

        $mismatches = 0;
        $actualTotal = 0;
        foreach ($actualRows as $row) {
            $agentName = DB::table('agents')->where('agent_id', $row->agent_id)->value('full_name') ?? '(SYSTEM/unknown)';
            $actualTotal += $row->commission_amount;
            $this->line("  {$row->role_at_transaction} -> {$agentName}: RM " . number_format($row->commission_amount, 2)
                . " ({$row->entitlement_pct}%, status: {$row->status}" . ($row->is_breakage ? ', BREAKAGE' : '') . ")");

            // Cross-check this row against the independently-expected amount for that same role
            $expectedForRole = null;
            foreach (($expected[$row->agent_id] ?? $expected['__BREAKAGE__'] ?? []) as $exp) {
                if ($exp['role'] === $row->role_at_transaction) {
                    $expectedForRole = $exp;
                    break;
                }
            }
            if ($expectedForRole === null) {
                $this->error("    => MISMATCH: could not find a matching expected entry for this role/agent.");
                $mismatches++;
            } elseif (abs((float)$expectedForRole['amount'] - (float)$row->commission_amount) > 0.01) {
                $this->error("    => MISMATCH: expected RM " . number_format($expectedForRole['amount'], 2) . " but stored amount is RM " . number_format($row->commission_amount, 2));
                $mismatches++;

                // UPDATED 29 Jul 2026 — Chris's first real test run hit
                // exactly this: every row was smaller than expected by
                // the same constant ratio. That specific fingerprint
                // means the row was calculated by applying the role %
                // TWICE (once via the pool, once via the role %) instead
                // of once — the bug fixed in CommissionEngine on 24 Jul
                // 2026. Detect and explain it automatically instead of
                // leaving Chris to spot the pattern by hand.
                if ((float)$row->commission_amount > 0) {
                    $ratio = (float)$expectedForRole['amount'] / (float)$row->commission_amount;
                    $doublePctRatio = $structure->total_commission_pct > 0 ? 100 / (float)$structure->total_commission_pct : null;
                    if ($doublePctRatio && abs($ratio - $doublePctRatio) < 0.01) {
                        $rowCreatedAt = \Illuminate\Support\Carbon::parse($row->created_at);
                        $this->comment("       Likely cause: this row was created " . $rowCreatedAt->format('d M Y, g:ia')
                            . " and its amount looks like it was calculated by applying the role % against the POOL"
                            . " (which was already scaled by Total Commission %) instead of against the raw premium/sum insured directly."
                            . " That double-percentage bug in CommissionEngine was fixed on 24 Jul 2026 — this row was very likely"
                            . " created before that fix and was never recalculated (commission is only ever calculated once, at"
                            . " submission time, so old rows don't auto-correct). If this row's date is BEFORE 24 Jul 2026, this is"
                            . " expected old data, not a live bug. If it's AFTER 24 Jul 2026, that's a live problem worth flagging back.");
                    }
                }
            } else {
                $this->line("    => matches expected amount.");
            }
        }

        $this->line('');
        $this->line("  Actual total stored : RM " . number_format($actualTotal, 2));
        $this->line('');

        $this->info('========================================================');
        if ($mismatches === 0 && $actualRows->isNotEmpty()) {
            $this->info(" RESULT: all {$actualRows->count()} row(s) match the independently recomputed expected amounts.");
        } elseif ($actualRows->isEmpty()) {
            $this->warn(' RESULT: nothing to compare — no commission rows exist yet for this policy.');
        } else {
            $this->error(" RESULT: {$mismatches} mismatch(es) found — see above.");
        }
        $this->info('========================================================');

        return self::SUCCESS;
    }

    private function showRecentTransactions(): void
    {
        $rows = DB::table('sales_transactions')
            ->where('is_deleted', false)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['policy_id', 'document_reference_number', 'policy_number', 'status', 'created_at']);

        $this->info('Most recent 15 Sales Transactions:');
        $this->table(
            ['Reference No.', 'Policy No.', 'Status', 'Submitted'],
            $rows->map(fn($r) => [
                $r->document_reference_number ?? '(none)',
                $r->policy_number,
                $r->status,
                \Illuminate\Support\Carbon::parse($r->created_at)->format('d M Y, g:ia'),
            ])
        );
    }
}
