<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommissionEngine
{
    public function __construct(
        private RewardPointsService $pointsService,
        private HierarchyService $hierarchyService,
    ) {}

    /**
     * Calculate commission for a submitted policy.
     * Called immediately when a Sales Transaction is first submitted.
     *
     * UPDATED 16 Jul 2026 — no longer pays out immediately. A brand-new
     * submission hasn't been confirmed against the vendor's own payment
     * yet, so records are created with status PENDING (visible to the
     * agent as "expected earning") and wallets are NOT credited and no
     * reward points are awarded until confirmPolicy() is called later
     * (once Admin verifies the sale against real vendor remittance —
     * the actual fraud-proof step, not this calculation itself).
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
            $distributions = $this->resolveDistributions($closer, $structure, $pool, $base);

            // 4b. NEW 31 Jul 2026 — "Override Recipient Profile" (personal
            // profile) overrides. Separate from the rank-based overrides
            // above: these are configured per NAMED agent (not per rank
            // slot), can be a fixed RM amount instead of only a %, and
            // are gated by effective/expiry date + Active/Inactive status.
            // Additive on top of $distributions — every matching, active,
            // in-date override row stacks (per Chris: priority is a
            // tie-break/display order only, never exclusion).
            $distributions = array_merge($distributions, $this->resolveIndividualOverrides($policy, $base, $distributions));

            // 5. Create commission transaction records as PENDING — no
            // wallet credit, no reward points yet. Those only happen in
            // confirmPolicy() below.
            foreach ($distributions as $dist) {
                $txnId = Str::uuid()->toString();

                DB::table('commission_transactions')->insert([
                    'txn_id'               => $txnId,
                    'policy_id'            => $policyId,
                    'agent_id'             => $dist['agent_id'],
                    'structure_id'         => $structure->structure_id,
                    'role_at_transaction'  => $dist['role'],
                    'rank_id'              => $dist['rank_id'] ?? null,
                    'override_id'          => $dist['override_id'] ?? null,
                    'policy_premium'       => $policy->premium_amount,
                    'total_pool_amount'    => $pool,
                    'entitlement_pct'      => $dist['pct'],
                    'commission_amount'    => $dist['amount'],
                    'is_breakage'          => $dist['is_breakage'],
                    'redistribution_reason'=> $dist['reason'] ?? null,
                    'status'               => 'PENDING',
                    'created_by'           => 'SYSTEM',
                    // FIXED 1 Aug 2026 — per Chris: after recalculating
                    // PVATM's commissions, June's Earning Income showed
                    // RM 0.00 on the dashboard even though the sales were
                    // real and the recalculation had run successfully.
                    // Root cause: this always stamped created_at=now(),
                    // so a policy sold in June but RECALCULATED today
                    // (e.g. via commission:recalculate-pvatm or
                    // commission:recalculate-all) got its commission_
                    // transactions row dated TODAY instead of June. Every
                    // dashboard/report that buckets Earning Income by
                    // month via commission_transactions.created_at then
                    // silently loses it from June and (wrongly) adds it
                    // to today's month instead. Using the policy's own
                    // created_at keeps commissions dated to when the
                    // SALE happened, not when it was last recalculated —
                    // correct both for a brand-new submission (where
                    // $policy->created_at IS now anyway) and for a
                    // historical recalculation.
                    'created_at'           => $policy->created_at,
                    'updated_at'           => now(),
                ]);
            }
        });
    }

    /**
     * NEW 16 Jul 2026 — the real release step. Called by Admin once a
     * Sales Transaction has been confirmed (today: a manual "Confirm"
     * action; future: automatic once matched during Vendor Payment
     * Reconciliation). Flips this policy's PENDING commission rows to
     * CONFIRMED, credits wallets, and awards reward points — this is
     * the ONLY place money actually moves, deliberately separated from
     * calculate() above.
     *
     * UPDATED 24 Jul 2026 — per Chris: Sales/Earning Income Volume
     * promotion rules should activate "when the sales transaction
     * approved and that sales transaction earning income claim
     * successful" — i.e. right here, the moment each agent's earning
     * income for this policy is actually confirmed, not on a timer.
     * Each non-breakage agent who was just paid gets re-checked against
     * Promotion & Demotion Rules once, after all their commission rows
     * for this policy are confirmed.
     *
     * UPDATED 30 Jul 2026 — added optional $recheckRanks (default true,
     * so the normal single-policy Confirm button in the UI is unchanged).
     * Bulk callers (commission:recalculate-all) pass false and instead
     * collect the returned agent IDs to rank-check ONCE per agent after
     * the whole run, since evaluateRankChange() walks that agent's
     * entire downline volume from scratch every time it's called —
     * calling it per-transaction meant the same Group/Team Leader with
     * hundreds of policies had their whole team's volume re-summed
     * hundreds of times in a row. Returns the non-breakage agent IDs
     * paid on this policy either way, so bulk callers can batch the
     * re-check themselves.
     */
    public function confirmPolicy(string $policyId, bool $recheckRanks = true): array
    {
        return DB::transaction(function () use ($policyId, $recheckRanks) {
            $policy = DB::table('sales_transactions')->where('policy_id', $policyId)->first();
            if (! $policy) throw new \Exception("Policy {$policyId} not found.");

            $pending = DB::table('commission_transactions')
                ->where('policy_id', $policyId)
                ->where('status', 'PENDING')
                ->get();

            $agentIdsToRecheck = [];

            foreach ($pending as $txn) {
                DB::table('commission_transactions')
                    ->where('txn_id', $txn->txn_id)
                    ->update(['status' => 'CONFIRMED', 'updated_at' => now()]);

                if (! $txn->is_breakage) {
                    DB::table('agents')->where('agent_id', $txn->agent_id)
                      ->increment('commission_balance', $txn->commission_amount);
                    $agentIdsToRecheck[$txn->agent_id] = true;
                }

                $this->pointsService->awardPoints($txn->agent_id, $txn->commission_amount, $txn->txn_id, $policy->vendor_id, $policy->product_id);
            }

            DB::table('sales_transactions')->where('policy_id', $policyId)->update([
                'status'     => 'ACTIVE',
                'updated_at' => now(),
            ]);

            // Re-check each paid agent's Sales/Earning Income Volume
            // (and any other active rule) now that this policy's
            // earning income is confirmed — not waiting for a schedule
            // or the next recruit to join.
            if ($recheckRanks) {
                foreach (array_keys($agentIdsToRecheck) as $agentId) {
                    $agent = Agent::find($agentId);
                    if ($agent) {
                        $this->hierarchyService->evaluateRankChange($agent);
                        // REMOVED 31 Jul 2026 — per Chris, the automatic
                        // per-agent rank_id evaluation (RankPromotionService)
                        // is retired; rank is now set at the bucket level
                        // (Role + Organization Rewards Group) via Rank
                        // Assignment, not per individual agent.
                    }
                }
            }

            return array_keys($agentIdsToRecheck);
        });
    }

    /**
     * Resolve commission distributions respecting hierarchy and missing-tier rules.
     * If a tier is absent or inactive, its share is redistributed upward.
     * If no upline exists, it goes to SYSTEM_COMPANY_ACCOUNT.
     *
     * FIXED 24 Jul 2026 — each role's pct (group_leader_pct/team_leader_pct/
     * introducer_pct) is validated on the Earning Income Structure form to
     * SUM to total_commission_pct (e.g. 2.5 + 2.5 + 5 = 10), meaning those
     * percentages are meant to be applied directly against the policy's
     * premium/sum insured — the SAME base $pool was already computed from.
     * This previously multiplied each role's pct against $pool a second
     * time (pool is already base*total_commission_pct/100), which silently
     * shrank every agent's payout by an extra factor of total_commission_pct/100
     * (e.g. a 10% total structure paid out only 10% of the correct amount).
     * Now takes $base (the raw premium/sum insured) and applies each role's
     * pct against that directly, so the three role amounts sum to $pool as
     * intended.
     */
    private function resolveDistributions(Agent $closer, object $structure, float $pool, float $base): array
    {
        // NEW 31 Jul 2026 — Configurable Rank System, Phase 2. Per Chris:
        // wire the rank breakdown into the actual calculation. If this
        // structure has any Rank Allocation configured (via the Earning
        // Income Structures screen's Rank Allocation panel), payout
        // switches to the rank-based model below instead of the flat
        // one-agent-per-role model. Structures nobody has touched with
        // rank allocations yet behave EXACTLY as before — this is
        // additive, never a behavior change for unconfigured structures.
        $rankPayoutMap = $this->buildRankPayoutMap($structure->structure_id);
        if (!empty($rankPayoutMap)) {
            return $this->resolveRankBasedDistributions($closer, $structure, $base, $rankPayoutMap);
        }

        $distributions = [];
        $distributed   = 0;

        // Map: role => [pct of base premium/sum insured, agent]
        $tiers = [
            'INTRODUCER'   => ['pct' => $structure->introducer_pct,   'agent' => null],
            'TEAM_LEADER'  => ['pct' => $structure->team_leader_pct,  'agent' => null],
            'GROUP_LEADER' => ['pct' => $structure->group_leader_pct, 'agent' => null],
        ];

        // Walk hierarchy from the closer upward and assign agents to tiers.
        //
        // GUARDED 30 Jul 2026 — this loop had no protection against a
        // circular parent_id chain (agent A -> parent B -> parent A).
        // If that ever occurs in the data (e.g. a bad manual hierarchy
        // edit), this would loop forever on that one transaction with
        // zero visible progress — exactly the "stuck at 0%, nothing
        // happens" symptom Chris hit running commission:recalculate-all
        // over the full 2510-transaction dataset (vs. the smaller
        // 387-transaction AIA-only run that never reached the affected
        // agent). $visited stops it after at most 100 levels up and
        // logs which agent's chain is broken instead of hanging.
        $current = $closer;
        $visited = [];
        $depth = 0;
        while ($current) {
            if (isset($visited[$current->agent_id]) || $depth > 100) {
                \Log::warning("Circular or excessively deep parent_id chain detected while resolving commission tiers, starting from closer agent {$closer->agent_id}. Stopped at agent {$current->agent_id}.");
                break;
            }
            $visited[$current->agent_id] = true;
            $depth++;

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
            $amount = round($base * ($data['pct'] / 100), 4);
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
     * NEW 31 Jul 2026 — "Override Recipient Profile" (personal profile).
     * Distinct from commission_rank_overrides (configured per RANK,
     * auto-applies to whoever holds it) — this is configured per NAMED
     * agent instead, e.g. a specific Regional Director you designate,
     * regardless of whether "Regional Director" exists as a system Role
     * or where that person sits in the parent_id upline chain.
     *
     * A row fires when this policy's own $distributions already paid
     * someone in that row's source_role (optionally narrowed to one
     * specific source_rank_id) — proving that role/rank was genuinely
     * part of this policy's payout, exactly matching Chris's own
     * example: "a Regional Director may override from Group Leader,
     * Team Leader, or Introducer based on the company's products plan."
     * The recipient does NOT need to be that closer's actual upline —
     * this is a personal entitlement, not a hierarchy walk.
     *
     * Scoped per company/product (vendor_id/product_id nullable = all),
     * gated by effective_date/expiry_date against the policy's own
     * coverage_start, and only fires when status = ACTIVE. Every
     * matching row stacks — per Chris, priority never excludes another
     * matching rule, it's a display/tie-break order only.
     */
    private function resolveIndividualOverrides(object $policy, float $base, array $existingDistributions): array
    {
        $overrides = DB::table('agent_commission_overrides')
            ->where('status', 'ACTIVE')
            ->where('effective_date', '<=', $policy->coverage_start)
            ->where(function ($q) use ($policy) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', $policy->coverage_start);
            })
            ->where(function ($q) use ($policy) {
                $q->whereNull('vendor_id')->orWhere('vendor_id', $policy->vendor_id);
            })
            ->where(function ($q) use ($policy) {
                $q->whereNull('product_id')->orWhere('product_id', $policy->product_id);
            })
            ->orderByDesc('priority')
            ->get();

        $extra = [];
        foreach ($overrides as $ov) {
            $sourcePaidThisPolicy = collect($existingDistributions)->contains(function ($d) use ($ov) {
                if (($d['is_breakage'] ?? false) || $d['role'] !== $ov->source_role) {
                    return false;
                }
                if ($ov->source_rank_id && ($d['rank_id'] ?? null) !== $ov->source_rank_id) {
                    return false;
                }
                return true;
            });

            if (!$sourcePaidThisPolicy) {
                continue; // this policy never touched that source role/rank — nothing to override
            }

            $amount = $ov->override_type === 'PERCENTAGE'
                ? round($base * ((float) $ov->override_value / 100), 4)
                : (float) $ov->override_value;

            if ($amount <= 0) {
                continue;
            }

            $extra[] = [
                'agent_id'    => $ov->recipient_agent_id,
                'role'        => $ov->source_role, // audit trail: which pool this personal override drew from
                'pct'         => $ov->override_type === 'PERCENTAGE' ? (float) $ov->override_value : 0,
                'amount'      => $amount,
                'is_breakage' => false,
                'reason'      => 'Individual Override Recipient Profile',
                'rank_id'     => null,
                'override_id' => $ov->override_id,
            ];
        }

        return $extra;
    }

    /**
     * NEW 31 Jul 2026 — builds, for one Earning Income Structure, a flat
     * map of rank_id => ['role' => its home role, 'pct' => the % of the
     * base premium/sum insured that specific rank should receive].
     *
     * Combines two sources:
     *   - commission_rank_allocations: a rank's own slice of its OWN
     *     role's %.
     *   - commission_rank_overrides: an EXTRA slice a rank draws from a
     *     DIFFERENT role's pool (Phase 3, "cross-role draws").
     * Both are added together per rank_id, since the validation on the
     * Admin screen (MasterFileController::validateRankAllocations())
     * already guarantees every role's total pool — its own ranks' cut
     * plus anything drawn INTO it via overrides — sums to exactly that
     * role's % on the structure. So summing here can never exceed the
     * structure's Total Commission %.
     *
     * Returns an empty array if this structure has no rank allocations
     * configured at all — the caller falls back to the original flat
     * role-based model in that case, unchanged.
     */
    private function buildRankPayoutMap(string $structureId): array
    {
        $allocations = DB::table('commission_rank_allocations as cra')
            ->join('role_ranks as rr', 'rr.rank_id', '=', 'cra.rank_id')
            ->where('cra.structure_id', $structureId)
            ->get(['cra.rank_id', 'rr.role', 'rr.group_label_id', 'cra.rank_pct']);

        if ($allocations->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($allocations as $a) {
            // group_label_id carried through here (NEW 1 Aug 2026) so the
            // breakage-vs-fallback decision below knows WHICH group's
            // requires_rank_assignment flag governs this specific rank.
            $map[$a->rank_id] = ['role' => $a->role, 'group_label_id' => $a->group_label_id, 'pct' => (float) $a->rank_pct];
        }

        $overrides = DB::table('commission_rank_overrides')
            ->where('structure_id', $structureId)
            ->get(['rank_id', 'override_pct']);

        foreach ($overrides as $o) {
            if (!isset($map[$o->rank_id])) {
                // A rank drawing an override without its own home
                // allocation row — shouldn't normally happen, but don't
                // silently drop the money; look up its home role directly.
                $homeRank = DB::table('role_ranks')->where('rank_id', $o->rank_id)->first(['role', 'group_label_id']);
                $map[$o->rank_id] = ['role' => $homeRank->role ?? null, 'group_label_id' => $homeRank->group_label_id ?? null, 'pct' => 0.0];
            }
            $map[$o->rank_id]['pct'] += (float) $o->override_pct;
        }

        return $map;
    }

    /**
     * NEW 31 Jul 2026 — Configurable Rank System, Phase 2 payout logic.
     * Per Chris ("Option 2", confirmed 24 Jul 2026): walks the ENTIRE
     * upline chain — not stopping at the single nearest agent per role
     * like the flat model does — and pays EVERY DISTINCT rank found
     * along that chain its own allocated %. This matters when a chain
     * has more than one rank of leadership above the closer (e.g. a
     * Bronze Group Leader reporting up to a Gold Group Leader) — both
     * get paid their own cut, not just whichever is nearest.
     *
     * A rank configured on this structure that nobody active in the
     * chain actually holds (agent has no rank assigned yet, or holds a
     * rank this structure doesn't cover) becomes breakage by default —
     * same convention as the flat model's "no active {role}" breakage.
     *
     * CHANGED 1 Aug 2026 — per Chris: "you should fall back to hierarchy
     * category if no rank assignment... refer to the group label flag."
     * Breakage is no longer unconditional. Each configured rank belongs
     * to one Organization Rewards Group (or System Default); if THAT
     * group's group_labels.requires_rank_assignment is OFF (the
     * default), an unmatched rank's slice is instead paid to the
     * nearest active agent of that rank's own ROLE in the same upline
     * chain — same recipient-finding rule the flat (no-rank) model
     * already uses — rather than sitting unpaid as breakage. Only a
     * group that's been deliberately switched to strict mode keeps the
     * original "unranked = breakage until ranked" behavior.
     */
    private function resolveRankBasedDistributions(Agent $closer, object $structure, float $base, array $rankPayoutMap): array
    {
        $distributions = [];
        $distributed   = 0.0;
        $paidRankIds   = [];
        // NEW 1 Aug 2026 — role => first active agent of that role found
        // walking up from the closer, exact same "first one wins" rule
        // as the flat model's $tiers. Used as the fallback recipient
        // below when a configured rank can't be matched to anyone.
        $nearestActiveByRole = [];

        $current = $closer;
        $visited = [];
        $depth = 0;
        while ($current) {
            if (isset($visited[$current->agent_id]) || $depth > 100) {
                \Log::warning("Circular or excessively deep parent_id chain detected while resolving RANK-based commission tiers, starting from closer agent {$closer->agent_id}. Stopped at agent {$current->agent_id}.");
                break;
            }
            $visited[$current->agent_id] = true;
            $depth++;

            if ($current->isActive() && !isset($nearestActiveByRole[$current->role])) {
                $nearestActiveByRole[$current->role] = $current;
            }

            if (
                $current->rank_id
                && !isset($paidRankIds[$current->rank_id])
                && isset($rankPayoutMap[$current->rank_id])
                && $current->isActive()
            ) {
                $rankInfo = $rankPayoutMap[$current->rank_id];
                $amount = round($base * ($rankInfo['pct'] / 100), 4);
                if ($amount > 0) {
                    $distributions[] = [
                        'agent_id'    => $current->agent_id,
                        'role'        => $rankInfo['role'],
                        'pct'         => $rankInfo['pct'],
                        'amount'      => $amount,
                        'is_breakage' => false,
                        'reason'      => null,
                        'rank_id'     => $current->rank_id,
                    ];
                    $distributed += $amount;
                }
                $paidRankIds[$current->rank_id] = true;
            }

            $current = $current->parent_id ? Agent::find($current->parent_id) : null;
        }

        $systemAccountId = DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->value('agent_id');

        // Cache per group_label_id so a structure with many unmatched
        // ranks across the same group doesn't re-query for each one.
        // A rank with no group_label_id (System Default) has no
        // group_labels row to carry the flag on, so it defaults to
        // graceful fallback (false) rather than strict breakage.
        $requiresRankCache = [];
        $requiresRank = function (?string $groupLabelId) use (&$requiresRankCache) {
            if (!$groupLabelId) {
                return false;
            }
            if (!array_key_exists($groupLabelId, $requiresRankCache)) {
                $requiresRankCache[$groupLabelId] = (bool) DB::table('group_labels')->where('group_label_id', $groupLabelId)->value('requires_rank_assignment');
            }
            return $requiresRankCache[$groupLabelId];
        };

        // Every rank configured on this structure that nobody active and
        // ranked in the chain actually matched.
        foreach ($rankPayoutMap as $rankId => $info) {
            if (isset($paidRankIds[$rankId])) {
                continue;
            }
            $amount = round($base * ($info['pct'] / 100), 4);
            if ($amount <= 0) {
                continue;
            }

            $fallbackAgent = $nearestActiveByRole[$info['role']] ?? null;
            if ($fallbackAgent && !$requiresRank($info['group_label_id'] ?? null)) {
                $distributions[] = [
                    'agent_id'    => $fallbackAgent->agent_id,
                    'role'        => $info['role'],
                    'pct'         => $info['pct'],
                    'amount'      => $amount,
                    'is_breakage' => false,
                    'reason'      => "Rank not assigned — paid to nearest active {$info['role']} instead (group does not require strict rank assignment)",
                    'rank_id'     => $rankId,
                ];
                $distributed += $amount;
                continue;
            }

            $rankName = DB::table('role_ranks')->where('rank_id', $rankId)->value('rank_name') ?? 'Unknown Rank';
            $distributions[] = [
                'agent_id'    => $systemAccountId,
                'role'        => $info['role'],
                'pct'         => $info['pct'],
                'amount'      => $amount,
                'is_breakage' => true,
                'reason'      => "No active agent holding rank \"{$rankName}\" in upline chain — credited to SYSTEM account until ranked",
                'rank_id'     => $rankId,
            ];
            $distributed += $amount;
        }

        // Reconcile any rounding difference to system account, same as
        // the flat model.
        $pool = round($base * ($structure->total_commission_pct / 100), 4);
        $remainder = round($pool - $distributed, 4);
        if (abs($remainder) > 0.0001) {
            $distributions[] = [
                'agent_id'    => $systemAccountId,
                'role'        => 'GROUP_LEADER',
                'pct'         => 0,
                'amount'      => $remainder,
                'is_breakage' => true,
                'reason'      => 'Rounding breakage reconciliation',
                'rank_id'     => null,
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
