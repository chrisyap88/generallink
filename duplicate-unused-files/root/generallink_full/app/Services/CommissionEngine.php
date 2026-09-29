<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionEngine
{
    public function __construct(private RewardPointsService $pointsService) {}

    /**
     * Calculate and distribute commission for a submitted policy.
     * Called after a policy is activated in sales_transactions.
     *
     * All percentages come from commission_structures table — nothing is hard-coded.
     */
    public function calculate(string $policyId): void
    {
        DB::transaction(function () use ($policyId) {

            $policy = DB::table('sales_transactions')->where('policy_id', $policyId)->first();
            if (! $policy) throw new \Exception("Policy {$policyId} not found.");

            // 1. Resolve active commission structure for this vendor + product
            $structure = $this->resolveStructure($policy->vendor_id, $policy->product_id, $policy->coverage_start);
            if (! $structure) {
                \Log::warning("No commission structure found for policy {$policyId}. Skipping commission.");
                return;
            }

            // 2. Calculate the gross commission pool
            $basis  = $structure->commission_basis;
            $base   = $basis === 'SUM_INSURED_PCT' ? $policy->sum_insured : $policy->premium_amount;
            $pool   = round($base * ($structure->total_commission_pct / 100), 4);

            // 3. Resolve the agent hierarchy for this policy
            $closer = Agent::find($policy->agent_id);
            if (! $closer) return;

            // 4. Determine role entitlements based on structure
            $distributions = $this->resolveDistributions($closer, $structure, $pool);

            // 5. Create commission transaction records + credit wallets
            foreach ($distributions as $dist) {
                $txnId = Str::uuid()->toString();

                DB::table('commission_transactions')->insert([
                    'txn_id'               => $txnId,
                    'policy_id'            => $policyId,
                    'agent_id'             => $dist['agent_id'],
                    'structure_id'         => $structure->structure_id,
                    'role_at_transaction'  => $dist['role'],
                    'policy_premium'       => $policy->premium_amount,
                    'total_pool_amount'    => $pool,
                    'entitlement_pct'      => $dist['pct'],
                    'commission_amount'    => $dist['amount'],
                    'is_breakage'          => $dist['is_breakage'],
                    'redistribution_reason'=> $dist['reason'] ?? null,
                    'status'               => 'CONFIRMED',
                    'created_by'           => 'SYSTEM',
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                // Credit agent wallet (not for breakage — that goes to system account)
                if (! $dist['is_breakage']) {
                    DB::table('agents')->where('agent_id', $dist['agent_id'])
                      ->increment('commission_balance', $dist['amount']);
                }

                // Calculate reward points for this commission
                $this->pointsService->awardPoints($dist['agent_id'], $dist['amount'], $txnId, $policy->vendor_id, $policy->product_id);
            }
        });
    }

    /**
     * Resolve commission distributions respecting hierarchy and missing-tier rules.
     * If a tier is absent or inactive, its share is redistributed upward.
     * If no upline exists, it goes to SYSTEM_COMPANY_ACCOUNT.
     */
    private function resolveDistributions(Agent $closer, object $structure, float $pool): array
    {
        $distributions = [];
        $distributed   = 0;

        // Map: role => [pct of pool, agent]
        $tiers = [
            'INTRODUCER'   => ['pct' => $structure->introducer_pct,   'agent' => null],
            'TEAM_LEADER'  => ['pct' => $structure->team_leader_pct,  'agent' => null],
            'GROUP_LEADER' => ['pct' => $structure->group_leader_pct, 'agent' => null],
        ];

        // Walk hierarchy from the closer upward and assign agents to tiers
        $current = $closer;
        while ($current) {
            $role = $current->role;
            if (isset($tiers[$role]) && $tiers[$role]['agent'] === null && $current->isActive()) {
                $tiers[$role]['agent'] = $current;
            }
            $current = $current->parent_id ? Agent::find($current->parent_id) : null;
        }

        // If closer is Introducer, assign them to INTRODUCER tier
        if ($closer->isIntroducer() && $tiers['INTRODUCER']['agent'] === null) {
            $tiers['INTRODUCER']['agent'] = $closer;
        }

        $systemAccountId = DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->value('agent_id');

        foreach ($tiers as $role => $data) {
            $amount = round($pool * ($data['pct'] / 100), 4);
            if ($amount <= 0) continue;

            if ($data['agent'] && $data['agent']->isActive()) {
                $distributions[] = [
                    'agent_id'   => $data['agent']->agent_id,
                    'role'       => $role,
                    'pct'        => $data['pct'],
                    'amount'     => $amount,
                    'is_breakage'=> false,
                    'reason'     => null,
                ];
                $distributed += $amount;
            } else {
                // Tier absent — redistribute or breakage
                $distributions[] = [
                    'agent_id'   => $systemAccountId,
                    'role'       => $role,
                    'pct'        => $data['pct'],
                    'amount'     => $amount,
                    'is_breakage'=> true,
                    'reason'     => "No active {$role} in upline chain — credited to SYSTEM account",
                ];
                $distributed += $amount;
            }
        }

        // Reconcile any rounding difference to system account
        $remainder = round($pool - $distributed, 4);
        if (abs($remainder) > 0.0001) {
            $distributions[] = [
                'agent_id'   => $systemAccountId,
                'role'       => 'GROUP_LEADER',
                'pct'        => 0,
                'amount'     => $remainder,
                'is_breakage'=> true,
                'reason'     => 'Rounding breakage reconciliation',
            ];
        }

        return $distributions;
    }

    /**
     * Resolve the most specific active commission structure.
     * Matches vendor+product first, then falls back (shouldn't be needed as structure is required).
     */
    private function resolveStructure(string $vendorId, string $productId, string $effectiveDate): ?object
    {
        return DB::table('commission_structures')
            ->where('vendor_id', $vendorId)
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $effectiveDate)
            ->where(function ($q) use ($effectiveDate) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $effectiveDate);
            })
            ->orderByDesc('valid_from')
            ->first();
    }

    /**
     * Reverse commissions when a policy is cancelled.
     */
    public function reverse(string $policyId, string $reversedByTxnId): void
    {
        DB::transaction(function () use ($policyId, $reversedByTxnId) {

            $txns = DB::table('commission_transactions')
                      ->where('policy_id', $policyId)
                      ->where('status', 'CONFIRMED')
                      ->get();

            foreach ($txns as $txn) {
                // Mark original as reversed
                DB::table('commission_transactions')
                  ->where('txn_id', $txn->txn_id)
                  ->update(['status' => 'REVERSED', 'reversed_by_txn_id' => $reversedByTxnId, 'updated_at' => now()]);

                // Debit wallet
                if (! $txn->is_breakage) {
                    DB::table('agents')->where('agent_id', $txn->agent_id)
                      ->decrement('commission_balance', $txn->commission_amount);
                }

                // Reverse reward points
                $this->pointsService->reversePoints($txn->agent_id, $txn->txn_id);
            }
        });
    }
}
