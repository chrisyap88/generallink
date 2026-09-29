<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;

// -------------------------------------------------------
// NEW 31 Jul 2026 — Configurable Rank System, Phase 4. Per Chris: rank
// assignment must be fully automatic once the system is live, exactly
// like role promotion already is — no Excel, no manual screen for
// day-to-day operation. Admin sets criteria once per rank (Rank
// Promotion Rules screen); this service evaluates agents against those
// criteria and keeps agents.rank_id current on its own.
//
// Two separate evaluation paths, kept deliberately separate for
// performance reasons (the same lesson learned from the "0% for a long
// time" bug on commission:recalculate-all — recomputing a whole
// population's numbers on every single transaction is expensive):
//
//   1. evaluateThresholdRanksForAgent() — cheap, per-agent, safe to run
//      right after that agent's own commission confirms (same trigger
//      point as HierarchyService::evaluateRankChange()). Covers
//      RECRUIT_COUNT / SALES_VOLUME / TENURE_MONTHS criteria.
//
//   2. evaluateTopNRanksForRoleGroup() — recomputes an entire role's
//      population and re-ranks them by a metric (Chris's "HQ" example:
//      "whoever has the single highest team volume"). Deliberately NOT
//      run per-transaction — only via a manual/periodic command — since
//      it must compare every agent in that role against every other,
//      not just check one agent's own numbers.
//
// Order matters when both run together (see RankEvaluateAll command):
// TOP_N_BY_METRIC ranks are re-claimed FIRST (they represent a special,
// comparative status), THEN threshold ranks are evaluated for everyone
// else — evaluateThresholdRanksForAgent() deliberately never touches an
// agent currently holding a rank governed by a TOP_N_BY_METRIC rule,
// since only the top-N pass is allowed to grant or remove that one.
// -------------------------------------------------------
class RankPromotionService
{
    public function __construct(private HierarchyService $hierarchyService) {}

    /**
     * All active ranks for this agent's own role, preferring their own
     * Organization Rewards Group's ranks if that group_label has defined
     * any, else the System Default (group_label_id NULL) set — same
     * fallback convention as RankAssignmentController::rankOptionsForAgent().
     * Ordered most senior first (lowest display_order).
     *
     * RESCOPED 31 Jul 2026 — was agent->group_id (their own individual
     * GL team), now agent->group_label_id (their Special Privilege
     * Group), per Chris: one shared rank ladder per prihatin2u/rela2u/
     * PVATM, not per individual GL.
     */
    private function ranksForAgent(Agent $agent): \Illuminate\Support\Collection
    {
        $ownGroupRanks = $agent->group_label_id
            ? DB::table('role_ranks')->where('role', $agent->role)->where('group_label_id', $agent->group_label_id)->where('is_active', true)->get()
            : collect();

        $ranks = $ownGroupRanks->isNotEmpty()
            ? $ownGroupRanks
            : DB::table('role_ranks')->where('role', $agent->role)->whereNull('group_label_id')->where('is_active', true)->get();

        return $ranks->sortBy('display_order')->values();
    }

    private function activeRules(string $rankId): \Illuminate\Support\Collection
    {
        return DB::table('rank_promotion_rules')->where('rank_id', $rankId)->where('is_active', true)->get();
    }

    private function combineLogicFor(string $rankId): string
    {
        return DB::table('rank_promotion_rule_logic')->where('rank_id', $rankId)->value('combine_logic') ?? 'AND';
    }

    private function criterionPasses(Agent $agent, object $rule): bool
    {
        return match ($rule->criteria_type) {
            'RECRUIT_COUNT' => $this->directActiveCount($agent) >= $rule->threshold_value,
            'SALES_VOLUME'  => $this->hierarchyService->teamVolumeBetween($agent, $rule->sales_metric, now()->subMonths($rule->sales_period_months ?? 12), now()) >= $rule->threshold_value,
            'TENURE_MONTHS' => $this->tenureMonths($agent) >= $rule->threshold_value,
            default         => false, // TOP_N_BY_METRIC is never evaluated here — see evaluateTopNRanksForRoleGroup()
        };
    }

    private function directActiveCount(Agent $agent): int
    {
        return Agent::where('parent_id', $agent->agent_id)->where('status', 'ACTIVE')->count();
    }

    private function tenureMonths(Agent $agent): int
    {
        $lastPromotion = DB::table('role_history')
            ->where('agent_id', $agent->agent_id)
            ->where('new_role', $agent->role)
            ->orderByDesc('effective_date')
            ->value('effective_date');

        $since = $lastPromotion ? \Carbon\Carbon::parse($lastPromotion) : $agent->created_at;
        return $since ? (int) $since->diffInMonths(now()) : 0;
    }

    private function rankMeetsCriteria(Agent $agent, object $rank): bool
    {
        $rules = $this->activeRules($rank->rank_id)->where('criteria_type', '!=', 'TOP_N_BY_METRIC');
        if ($rules->isEmpty()) {
            return false; // nothing configured — never auto-assign a rank with no threshold rules
        }

        $logic = $this->combineLogicFor($rank->rank_id);
        return $logic === 'OR'
            ? $rules->contains(fn($rule) => $this->criterionPasses($agent, $rule))
            : $rules->every(fn($rule) => $this->criterionPasses($agent, $rule));
    }

    /**
     * Recomputes the best-fit rank for one agent from scratch (handles
     * both promotion and demotion in one pass — whichever rank they
     * currently qualify for, most senior first, is what they get).
     * Never touches an agent currently holding a TOP_N_BY_METRIC-governed
     * rank — only evaluateTopNRanksForRoleGroup() may grant or remove
     * that kind of rank.
     */
    public function evaluateThresholdRanksForAgent(Agent $agent): void
    {
        if ($agent->role === 'ADMIN') {
            return;
        }

        if ($agent->rank_id) {
            $currentRankHasTopN = DB::table('rank_promotion_rules')
                ->where('rank_id', $agent->rank_id)
                ->where('criteria_type', 'TOP_N_BY_METRIC')
                ->where('is_active', true)
                ->exists();
            if ($currentRankHasTopN) {
                return; // hands off — governed by the top-N pass only
            }
        }

        $ranks = $this->ranksForAgent($agent)->reject(function ($rank) {
            return DB::table('rank_promotion_rules')
                ->where('rank_id', $rank->rank_id)
                ->where('criteria_type', 'TOP_N_BY_METRIC')
                ->where('is_active', true)
                ->exists();
        });

        $bestFit = null;
        foreach ($ranks as $rank) {
            if ($this->rankMeetsCriteria($agent, $rank)) {
                $bestFit = $rank;
                break; // ranks are ordered most senior first
            }
        }

        $newRankId = $bestFit->rank_id ?? null;
        if ($agent->rank_id !== $newRankId) {
            DB::table('agents')->where('agent_id', $agent->agent_id)->update([
                'rank_id'    => $newRankId,
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Recomputes every TOP_N_BY_METRIC rank belonging to one specific
     * role+group_label scope. Deliberately expensive (compares every
     * agent in that role+group_label against every other) — never call
     * this per transaction; only from a manual/periodic command.
     *
     * RESCOPED 31 Jul 2026 — was role+group_id, now role+group_label_id,
     * same reasoning as ranksForAgent() above.
     */
    public function evaluateTopNRanksForRoleGroup(string $role, ?string $groupLabelId): array
    {
        $rankIds = DB::table('role_ranks')
            ->where('role', $role)
            ->where('group_label_id', $groupLabelId)
            ->where('is_active', true)
            ->pluck('rank_id');

        $changes = [];

        foreach ($rankIds as $rankId) {
            $rule = DB::table('rank_promotion_rules')
                ->where('rank_id', $rankId)
                ->where('criteria_type', 'TOP_N_BY_METRIC')
                ->where('is_active', true)
                ->first();

            if (!$rule) {
                continue;
            }

            $n = max(1, (int) $rule->threshold_value);

            // Population sharing this exact role+group_label scope —
            // same fallback rule as ranksForAgent(): an agent falls into
            // the System Default (group_label_id null) population only
            // if their OWN group_label has no ranks of its own for this role.
            $agents = Agent::where('role', $role)->where('status', 'ACTIVE');
            if ($groupLabelId) {
                $agents->where('group_label_id', $groupLabelId);
            } else {
                $groupLabelsWithOwnRanks = DB::table('role_ranks')->where('role', $role)->whereNotNull('group_label_id')->pluck('group_label_id')->unique();
                $agents->where(function ($q) use ($groupLabelsWithOwnRanks) {
                    $q->whereNull('group_label_id')->orWhereNotIn('group_label_id', $groupLabelsWithOwnRanks);
                });
            }
            $agents = $agents->get();

            $ranked = $agents->map(function ($agent) use ($rule) {
                $agent->_metric = $this->hierarchyService->teamVolumeBetween(
                    $agent, $rule->sales_metric, now()->subMonths($rule->sales_period_months ?? 12), now()
                );
                return $agent;
            })->filter(fn($a) => $a->_metric > 0)
              ->sortByDesc('_metric')
              ->values();

            $winnerIds = $ranked->take($n)->pluck('agent_id')->all();

            // Clear anyone currently holding this rank who's no longer a winner.
            $previousHolders = DB::table('agents')->where('rank_id', $rankId)->pluck('agent_id');
            foreach ($previousHolders as $agentId) {
                if (!in_array($agentId, $winnerIds)) {
                    DB::table('agents')->where('agent_id', $agentId)->update(['rank_id' => null, 'updated_at' => now()]);
                    $changes[] = "Removed rank from agent {$agentId} (no longer top {$n})";
                }
            }

            foreach ($winnerIds as $agentId) {
                $current = DB::table('agents')->where('agent_id', $agentId)->value('rank_id');
                if ($current !== $rankId) {
                    DB::table('agents')->where('agent_id', $agentId)->update(['rank_id' => $rankId, 'updated_at' => now()]);
                    $changes[] = "Assigned rank to agent {$agentId} (now top {$n})";
                }
            }
        }

        return $changes;
    }
}
