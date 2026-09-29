<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 31 Jul 2026 — Vendor Override Members, Step 3 of the flow: the
// periodic calculation run. Deliberately SEPARATE from CommissionEngine
// — Override Members aren't agents, nothing here touches earning_wallets
// or agents.rank_id. This only reads each Organization Rewards Group's real
// sales/earning-income volume with the relevant vendor (scoped by
// agents.group_label_id, confirmed by Chris as the correct scoping
// field), applies each member's per-product eligibility rules, and
// writes the result to override_commission_claims for Admin to review,
// export, and settle (deduct from the group's claim, or hand over as a
// claim-back report to the vendor) — whichever that member's own
// settlement_method says.
//
// CUSTOM_KPI rules are never auto-calculated (there's no formula to
// run) — they're listed separately so Admin knows to enter that
// member's amount by hand for the period.
// -------------------------------------------------------
class CalculateOverrideCommissions extends Command
{
    protected $signature = 'override:calculate';
    protected $description = 'Calculate this period\'s override commission for every active Vendor Override Member (writes to override_commission_claims for review)';

    public function handle(): int
    {
        $members = DB::table('override_members')->where('is_active', true)->get();

        $created = 0;
        $skippedNoRules = 0;
        $needsManualEntry = [];

        $this->info("Checking {$members->count()} active override member(s)...");

        foreach ($members as $member) {
            $rules = DB::table('override_member_eligibility_rules')
                ->where('override_member_id', $member->override_member_id)
                ->where('is_active', true)
                ->get();

            if ($rules->isEmpty()) {
                $skippedNoRules++;
                continue;
            }

            foreach ($rules as $rule) {
                if ($rule->criteria_type === 'CUSTOM_KPI') {
                    $needsManualEntry[] = "{$member->override_member_code} ({$member->full_name}) — {$rule->custom_kpi_description}";
                    continue;
                }

                $periodMonths = $rule->period_months ?? 12;
                $periodEnd = now();
                $periodStart = now()->copy()->subMonths($periodMonths);

                $salesBasis = $this->salesBasis($member, $rule, $periodStart, $periodEnd);

                $amount = match ($rule->criteria_type) {
                    'PERCENTAGE'    => round($salesBasis * ((float) $rule->threshold_value / 100), 4),
                    'FIXED_AMOUNT'  => (float) $rule->threshold_value,
                    'SALES_TARGET'  => $salesBasis >= (float) $rule->threshold_value ? $salesBasis : 0.0,
                    default         => 0.0,
                };

                if ($amount <= 0) {
                    continue; // nothing earned this period under this rule
                }

                DB::table('override_commission_claims')->insert([
                    'claim_id'           => (string) Str::uuid(),
                    'override_member_id' => $member->override_member_id,
                    'rule_id'            => $rule->rule_id,
                    'product_id'         => $rule->product_id,
                    'period_start'       => $periodStart->toDateString(),
                    'period_end'         => $periodEnd->toDateString(),
                    'sales_basis_amount' => $salesBasis,
                    'calculated_amount'  => $amount,
                    'settlement_method'  => $member->settlement_method,
                    'status'             => 'CALCULATED',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
                $created++;
            }
        }

        $this->line('');
        $this->info('========================================================');
        $this->info(' RESULT');
        $this->info('========================================================');
        $this->line("  Claims calculated             : {$created}");
        $this->line("  Members with no active rules   : {$skippedNoRules}");

        if (!empty($needsManualEntry)) {
            $this->warn('  Custom KPI rules — needs manual amount entry this period:');
            foreach ($needsManualEntry as $m) {
                $this->line("    - {$m}");
            }
        }

        $this->line('');
        $this->line('Review and settle these in Master File Maintenance -> Vendor Override Members.');

        return self::SUCCESS;
    }

    private function salesBasis(object $member, object $rule, $periodStart, $periodEnd): float
    {
        if ($rule->sales_metric === 'PREMIUM') {
            return (float) DB::table('sales_transactions as st')
                ->join('agents as a', 'a.agent_id', '=', 'st.agent_id')
                ->where('a.group_label_id', $member->group_label_id)
                ->where('st.vendor_id', $member->vendor_id)
                ->when($rule->product_id, fn($q) => $q->where('st.product_id', $rule->product_id))
                ->whereBetween('st.coverage_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
                ->sum('st.premium_amount');
        }

        // EARNING_INCOME — sum confirmed commission actually paid out on
        // this vendor's (optionally this product's) policies for agents
        // under this group label, not just the raw premium.
        return (float) DB::table('commission_transactions as ct')
            ->join('sales_transactions as st', 'st.policy_id', '=', 'ct.policy_id')
            ->join('agents as a', 'a.agent_id', '=', 'st.agent_id')
            ->where('a.group_label_id', $member->group_label_id)
            ->where('st.vendor_id', $member->vendor_id)
            ->when($rule->product_id, fn($q) => $q->where('st.product_id', $rule->product_id))
            ->where('ct.status', 'CONFIRMED')
            ->whereBetween('st.coverage_start', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->sum('ct.commission_amount');
    }
}
