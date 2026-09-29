<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HierarchyService
{
    // -------------------------------------------------------
    // Generate agent_code matching the live convention (e.g. "1-4-3"),
    // using the safe next_child_seq counter. (unchanged from previous file)
    // -------------------------------------------------------
    public function generateAgentCode(Agent $sponsor): string
    {
        $retries = 0;

        do {
            $newCode = DB::transaction(function () use ($sponsor) {
                $locked = Agent::lockForUpdate()->find($sponsor->agent_id);
                $locked->increment('next_child_seq');
                $nextSeq = $locked->next_child_seq;

                if (! $locked->agent_code) {
                    return (string) $nextSeq;
                }

                return $locked->agent_code . '-' . $nextSeq;
            });

            $exists = Agent::where('agent_code', $newCode)->exists();
            $retries++;
        } while ($exists && $retries < 10);

        if ($exists) {
            throw new \Exception('Could not generate unique agent code. Please retry.');
        }

        return $newCode;
    }

    public function generateMemberCode(Agent $sponsor): string
    {
        return $this->generateAgentCode($sponsor);
    }

    // -------------------------------------------------------
    // MODULE 2A — Tier restriction check (unchanged)
    // -------------------------------------------------------
    public function canRecruit(Agent $sponsor): bool
    {
        if (! $sponsor->isIntroducer()) return true;
        if ($sponsor->recruitment_blocked) return false;

        $limit = DB::table('tier_recruitment_config')
            ->where(fn($q) => $q->where('group_id', $sponsor->group_id)->orWhereNull('group_id'))
            ->where('is_active', true)
            ->orderByRaw('group_id IS NULL ASC')
            ->value('max_tier_limit') ?? 2;

        return $sponsor->recruitable_tier_depth < $limit;
    }

    public function getRecruitmentBlockReason(Agent $sponsor): string
    {
        $limit = DB::table('tier_recruitment_config')
            ->where(fn($q) => $q->where('group_id', $sponsor->group_id)->orWhereNull('group_id'))
            ->where('is_active', true)
            ->orderByRaw('group_id IS NULL ASC')
            ->value('max_tier_limit') ?? 2;

        return "Recruitment tier limit reached (Tier {$limit}). " .
               "Recruit {$limit} more Introducers to be promoted to Team Leader and unlock unlimited recruitment.";
    }

    // -------------------------------------------------------
    // Register a new agent under a sponsor (unchanged from previous file)
    // -------------------------------------------------------
    public function registerUnderSponsor(Agent $sponsor, array $agentData, string $createdBy): Agent
    {
        if (! $this->canRecruit($sponsor)) {
            throw new \Exception($this->getRecruitmentBlockReason($sponsor));
        }

        return DB::transaction(function () use ($sponsor, $agentData, $createdBy) {

            $newCode       = $this->generateAgentCode($sponsor);
            $newAgentId    = Str::uuid()->toString();
            $tierDepth     = $sponsor->recruitable_tier_depth + 1;
            $hierarchyPath = $sponsor->hierarchy_path . $newAgentId . '/';

            $agent = Agent::create(array_merge($agentData, [
                'agent_id'               => $newAgentId,
                'agent_code'             => $newCode,
                'member_code'            => $newCode,
                'parent_id'              => $sponsor->agent_id,
                'group_id'               => $sponsor->group_id,
                'origin_group_id'        => $sponsor->group_id,
                'hierarchy_path'         => $hierarchyPath,
                'role'                   => 'INTRODUCER',
                // FIXED (confirmed decision, session 06 Jul 2026): must
                // start INACTIVE, not ACTIVE. Becomes ACTIVE only after
                // the person's own email verification + password setup
                // (see AuthController::setPassword()). This also closes
                // a race condition: evaluateRankChange() below now
                // correctly EXCLUDES this brand-new, still-unverified
                // recruit from the sponsor's active-recruit count.
                'status'                 => 'INACTIVE',
                'recruitable_tier_depth' => $tierDepth,
                'recruitment_blocked'    => false,
                'created_by'             => $createdBy,
            ]));

            $this->evaluateRankChange($sponsor);

            return $agent;
        });
    }

    // -------------------------------------------------------
    // MODULE 2 — Auto Promotion / Demotion Engine
    // UPDATED 24 Jul 2026 — Configurable Promotion & Demotion Rules. The
    // hardcoded "3 direct active recruits" check now reads from the
    // `promotion_demotion_rules` table (Admin-editable on the
    // Promotion & Demotion Rules screen) and supports 3 criteria types
    // combined with AND logic: RECRUIT_COUNT (the original rule,
    // unchanged by default), SALES_VOLUME, and TENURE_MONTHS. Demotion
    // re-checks the SAME criteria that qualified the agent for their
    // CURRENT role in the first place (a Team Leader demotes if they no
    // longer meet the Introducer->Team Leader rules; a Group Leader
    // demotes if they no longer meet the Team Leader->Group Leader
    // rules) — TENURE_MONTHS is skipped on demotion since it's a
    // one-time eligibility gate, not something you can "lose".
    // -------------------------------------------------------
    public function evaluateRankChange(Agent $agent): void
    {
        DB::transaction(function () use ($agent) {

            $locked = Agent::lockForUpdate()->find($agent->agent_id);
            if (! $locked) return;

            // Organization Rewards Group check — generic and reusable for
            // ANY organization Admin sets this flag for (Tekun,
            // National Veteran Corporation, Felda, Police Cooperative,
            // etc.), not hardcoded to any specific one. Confirmed
            // decision (06 Jul 2026): these groups have fixed roles
            // (1 GL, 1 TL, many Introducers) with NO automatic
            // promotion/demotion at all.
            if ($locked->group_label_id) {
                $enabled = DB::table('group_labels')->where('group_label_id', $locked->group_label_id)->value('promotion_demotion_enabled');
                if ($enabled === false || $enabled === 0) {
                    return;
                }
            }

            if ($locked->isIntroducer()) {
                if ($this->meetsCriteria($locked, 'TEAM_LEADER')) {
                    $this->promote($locked, 'TEAM_LEADER');
                }
            } elseif ($locked->isTeamLeader()) {
                if ($this->meetsCriteria($locked, 'GROUP_LEADER')) {
                    $this->promote($locked, 'GROUP_LEADER');
                } elseif (! $this->meetsCriteria($locked, 'TEAM_LEADER', forDemotionCheck: true)) {
                    $this->demote($locked, 'INTRODUCER');
                }
            } elseif ($locked->isGroupLeader()) {
                if (! $this->meetsCriteria($locked, 'GROUP_LEADER', forDemotionCheck: true)) {
                    $this->demote($locked, 'TEAM_LEADER');
                }
            }
        });
    }

    // UPDATED 24 Jul 2026 — rules are now scoped per Special Privilege
    // Group (group_labels.group_label_id — the SAME identity that gates
    // promotion_demotion_enabled above), not one shared set. A group
    // with its OWN active rules for this transition uses ONLY those; a
    // group with none defined yet (including plain Public agents, whose
    // group_label_id is null) falls back to the System Default set
    // (group_label_id IS NULL). Any number of rules can apply — no
    // longer capped at 3 — and ALL of them must pass (AND logic).
    // Which group_label_id's rules actually apply for this transition —
    // the agent's own group if it has ANY active rules defined, else
    // System Default (null). Kept separate from rulesFor() so the
    // AND/OR combine_logic lookup below can use the exact same scope
    // the rules themselves came from.
    private function resolveRuleScope(Agent $agent, string $toRole): ?string
    {
        $groupLabelId = $agent->group_label_id;

        $ownHasRules = DB::table('promotion_demotion_rules')
            ->where('to_role', $toRole)
            ->where('is_active', true)
            ->where('group_label_id', $groupLabelId)
            ->exists();

        return ($ownHasRules || $groupLabelId === null) ? $groupLabelId : null;
    }

    private function rulesFor(?string $groupLabelId, string $toRole): \Illuminate\Support\Collection
    {
        return DB::table('promotion_demotion_rules')
            ->where('to_role', $toRole)
            ->where('is_active', true)
            ->where('group_label_id', $groupLabelId)
            ->get();
    }

    // NEW 25 Jul 2026 — per Chris: "if i chose one matrix is one matrix
    // if i click 2 matric you should ask me and / or". Defaults to AND
    // (matches original behaviour, and AND/OR make no difference with
    // only 1 rule anyway). Uses the SAME scope the rules came from — a
    // group following its own rules uses its own AND/OR choice; a group
    // inheriting System Default rules also inherits System Default's
    // AND/OR choice.
    private function combineLogicFor(?string $groupLabelId, string $toRole): string
    {
        return DB::table('promotion_rule_logic')
            ->where('group_label_id', $groupLabelId)
            ->where('to_role', $toRole)
            ->value('combine_logic') ?? 'AND';
    }

    private function meetsCriteria(Agent $agent, string $toRole, bool $forDemotionCheck = false): bool
    {
        $scope = $this->resolveRuleScope($agent, $toRole);
        $rules = $this->rulesFor($scope, $toRole);

        // One-time eligibility gate, not re-checked on demotion.
        $applicable = $rules->reject(fn($rule) => $forDemotionCheck && $rule->criteria_type === 'TENURE_MONTHS');

        if ($applicable->isEmpty()) {
            // Nothing left to measure — never auto-promote with no
            // rules, but also never auto-demote when there's nothing to
            // check against (e.g. only a Tenure gate existed, or every
            // rule was cleared). Demoting with no yardstick would be
            // arbitrary.
            return $forDemotionCheck;
        }

        $logic = $this->combineLogicFor($scope, $toRole);

        if ($logic === 'OR') {
            return $applicable->contains(fn($rule) => $this->criterionPasses($agent, $rule));
        }

        return $applicable->every(fn($rule) => $this->criterionPasses($agent, $rule));
    }

    private function criterionPasses(Agent $agent, object $rule): bool
    {
        return match ($rule->criteria_type) {
            'RECRUIT_COUNT' => $this->directActiveCount($agent, $rule->from_role) >= $rule->threshold_value,
            'SALES_VOLUME'  => $this->salesVolume($agent, $rule->sales_metric, $rule->sales_period_months) >= $rule->threshold_value,
            'TENURE_MONTHS' => $this->tenureMonths($agent) >= $rule->threshold_value,
            default         => true,
        };
    }

    private function directActiveCount(Agent $agent, string $role): int
    {
        return Agent::where('parent_id', $agent->agent_id)
            ->where('status', 'ACTIVE')
            ->where('role', $role)
            ->count();
    }

    // Every agent_id in this agent's own subtree, INCLUDING themselves —
    // used so Sales/Earning Income Volume reflects the whole team, not
    // just the one agent (Chris: "introducer plus downline achieved x
    // sales promote TL"). Reuses the same hierarchy_path pattern already
    // used by moveSubtreeToGroup() elsewhere in this file. PUBLIC so the
    // separate Breakaway Bonus evaluation command can reuse it without
    // duplicating this query.
    public function subtreeAgentIds(Agent $agent): array
    {
        $descendantIds = Agent::where('hierarchy_path', 'like', "%/{$agent->agent_id}/%")
            ->pluck('agent_id')
            ->all();

        return array_merge([$agent->agent_id], $descendantIds);
    }

    // Sums the agent's OWN production PLUS their entire downline subtree
    // for an ARBITRARY date range. EARNING_INCOME = actual commission
    // credited (commission_transactions.commission_amount, CONFIRMED
    // only); PREMIUM = total policy premium submitted (sales_
    // transactions.premium_amount). PUBLIC — reused by the Breakaway
    // Bonus evaluation command (which needs a fixed period_start/
    // period_end, not just "trailing N months from right now").
    public function teamVolumeBetween(Agent $agent, ?string $metric, \Carbon\Carbon $since, \Carbon\Carbon $until): float
    {
        $agentIds = $this->subtreeAgentIds($agent);

        if ($metric === 'PREMIUM') {
            return (float) DB::table('sales_transactions')
                ->whereIn('agent_id', $agentIds)
                ->where('is_deleted', false)
                ->whereBetween('created_at', [$since, $until])
                ->sum('premium_amount');
        }

        return (float) DB::table('commission_transactions')
            ->whereIn('agent_id', $agentIds)
            ->where('status', 'CONFIRMED')
            ->whereBetween('created_at', [$since, $until])
            ->sum('commission_amount');
    }

    // Sums the agent's OWN production PLUS their entire downline subtree
    // over a trailing rolling period ending now — used by Promotion &
    // Demotion Rules. Thin wrapper around teamVolumeBetween() above.
    private function salesVolume(Agent $agent, ?string $metric, ?int $periodMonths): float
    {
        return $this->teamVolumeBetween($agent, $metric, now()->subMonths($periodMonths ?? 12), now());
    }

    // Months since the agent was last promoted INTO their current role
    // (role_history), falling back to their agents.created_at if
    // they've never had a role_history row (e.g. an original Introducer
    // who's never moved).
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

    // -------------------------------------------------------
    // PROMOTE — rewritten 03 Jul 2026 for Decisions 5 & 6
    // -------------------------------------------------------
    private function promote(Agent $agent, string $newRole): void
    {
        $oldRole      = $agent->role;
        $oldParentId  = $agent->parent_id;
        $oldGroupId   = $agent->group_id;
        $newParentId  = null;
        $newGroupId   = $oldGroupId;

        // NEW 25 Jul 2026 — Breakaway Bonus. Capture who this agent is
        // breaking away FROM before parent_id gets nulled below — the
        // nearest active Group Leader up her OLD parent chain (e.g.
        // Chris Yap, if Amy Tan is being promoted to GL out from under
        // him). Only relevant for a promotion TO Group Leader.
        $breakawayFromGL = $newRole === 'GROUP_LEADER' ? $this->findNearestActiveGL($agent) : null;

        // Decision 5 — promotion TO Group Leader always creates a BRAND NEW
        // group_id, never reuses a previous one, even on re-promotion.
        if ($newRole === 'GROUP_LEADER') {
            $newGroupId  = $this->createNewGroupForAgent($agent);
            $newParentId = null; // GL is root — no parent
        }

        $agent->update([
            'role'                => $newRole,
            'parent_id'           => $newParentId ?? $agent->parent_id,
            'group_id'            => $newGroupId,
            'recruitment_blocked' => false,
        ]);

        if ($newRole === 'GROUP_LEADER') {
            // Move her entire CURRENT downline subtree into her new group.
            // Former downline who already broke away to their own GL are
            // naturally excluded — their hierarchy_path no longer contains
            // her agent_id after their own promotion already rebuilt it.
            $this->moveSubtreeToGroup($agent, $newGroupId);
            $this->rebuildHierarchyPaths($agent);

            // Decision 6 — reunite anyone displaced from her during a
            // PREVIOUS demotion, who hasn't been reunited yet.
            $this->reuniteDisplacedDownline($agent);
        }

        AuditService::logChange('agents', $agent->agent_id, 'PROMOTE', ['role' => $oldRole], ['role' => $newRole]);

        $this->writeRoleHistory($agent, $oldRole, $newRole, $oldParentId, $newParentId, $oldGroupId, $newGroupId, 'AUTO_PROMOTED');

        // Breakaway Bonus link — only if there WAS an active GL up the
        // old chain (a brand new top-level GL with nobody above has
        // nobody to owe a breakaway bonus to). Never touches money —
        // just records the relationship for the separate breakaway
        // evaluation command to check later.
        if ($breakawayFromGL) {
            DB::table('breakaway_links')->insert([
                'link_id'              => (string) Str::uuid(),
                'promoted_gl_agent_id' => $agent->agent_id,
                'original_gl_agent_id' => $breakawayFromGL->agent_id,
                'promoted_at'          => now(),
                'is_active'            => true,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }
    }

    // -------------------------------------------------------
    // DEMOTE — rewritten 03 Jul 2026 for Decisions 4 & 6
    // -------------------------------------------------------
    private function demote(Agent $agent, string $newRole): void
    {
        $oldRole    = $agent->role;
        $oldGroupId = $agent->group_id;
        $wasGL      = $agent->isGroupLeader();

        $newParentAgent = $wasGL ? $this->findNearestActiveGL($agent) : null;
        $newParentId    = $newParentAgent?->agent_id;
        $newGroupId     = $newParentAgent?->group_id ?? $agent->group_id;

        if ($wasGL) {
            // Decision 4 — mark her group inactive, NEVER delete it.
            DB::table('groups')->where('group_id', $oldGroupId)->update([
                'is_active'  => false,
                'updated_at' => now(),
            ]);

            // Her subordinate TEAM_LEADERs (and their downlines) must move —
            // a TL's parent can never be another TL. Her own direct
            // INTRODUCERS are NOT touched — a TL is allowed direct
            // Introducers, so they correctly stay with her.
            $subordinateTLs = Agent::where('parent_id', $agent->agent_id)
                                    ->where('role', 'TEAM_LEADER')
                                    ->get();

            foreach ($subordinateTLs as $tl) {
                // Decision 6 — mark as displaced (not permanent), so this
                // specific TL and their downline can be auto-reunited if
                // $agent is promoted to GL again later.
                $tl->update([
                    'parent_id'               => $newParentId,
                    'displaced_from_agent_id' => $agent->agent_id,
                ]);
                $this->moveSubtreeToGroup($tl, $newGroupId, markDisplacedFrom: $agent->agent_id);
            }

            $this->rebuildHierarchyPaths($newParentAgent ?? $agent);
        }

        $agent->update([
            'role'      => $newRole,
            'parent_id' => $newParentId ?? $agent->parent_id,
            'group_id'  => $newGroupId,
        ]);

        if ($agent->parent_id) {
            $parent = Agent::find($agent->parent_id);
            if ($parent) $this->evaluateRankChange($parent);
        }

        AuditService::logChange('agents', $agent->agent_id, 'DEMOTE', ['role' => $oldRole], ['role' => $newRole]);

        $this->writeRoleHistory($agent, $oldRole, $newRole, null, $newParentId, $oldGroupId, $newGroupId, 'AUTO_DEMOTED');
    }

    // -------------------------------------------------------
    // Decision 5 — create a brand new group for a newly-promoted GL
    // -------------------------------------------------------
    private function createNewGroupForAgent(Agent $agent): string
    {
        $groupId = Str::uuid()->toString();

        DB::table('groups')->insert([
            'group_id'           => $groupId,
            'group_name'         => $agent->full_name,
            'group_code'         => $agent->agent_code, // matches live convention — no separate letter prefix
            'group_email'        => $agent->email,
            'separator_char'     => '-',
            'root_member_suffix' => '0',
            'is_active'          => true,
            'created_by'         => $agent->agent_id,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return $groupId;
    }

    // -------------------------------------------------------
    // Move an agent's entire current downline subtree to a new group_id.
    // Optionally tags each moved agent with displaced_from_agent_id
    // (used during demotion — Decision 6).
    // -------------------------------------------------------
    private function moveSubtreeToGroup(Agent $root, string $newGroupId, ?string $markDisplacedFrom = null): void
    {
        $query = Agent::where('hierarchy_path', 'like', "%/{$root->agent_id}/%");

        if ($markDisplacedFrom) {
            $query->update([
                'group_id'                => $newGroupId,
                'displaced_from_agent_id' => $markDisplacedFrom,
                'updated_at'              => now(),
            ]);
        } else {
            $query->update([
                'group_id'   => $newGroupId,
                'updated_at' => now(),
            ]);
        }
    }

    // -------------------------------------------------------
    // Decision 6 — reunite anyone previously displaced from this agent
    // during an earlier demotion, now that she's a GL again. Skips anyone
    // who became a GL themselves in the meantime (existing "what moves"
    // exception — checked by role != GROUP_LEADER).
    // -------------------------------------------------------
    private function reuniteDisplacedDownline(Agent $agent): void
    {
        $displaced = Agent::where('displaced_from_agent_id', $agent->agent_id)
                          ->where('role', '!=', 'GROUP_LEADER')
                          ->get();

        foreach ($displaced as $member) {
            // Only reunite direct former subordinates — their own downlines
            // will follow automatically via moveSubtreeToGroup below.
            if ($member->parent_id !== $agent->agent_id) {
                $member->update(['parent_id' => $agent->agent_id]);
            }
            $member->update([
                'group_id'                => $agent->group_id,
                'displaced_from_agent_id' => null,
            ]);
            $this->moveSubtreeToGroup($member, $agent->group_id);
        }

        if ($displaced->isNotEmpty()) {
            // Mark the original demotion event(s) as reunified so they're
            // never processed again on a future re-promotion.
            DB::table('role_history')
                ->where('agent_id', $agent->agent_id)
                ->where('reason', 'AUTO_DEMOTED')
                ->whereNull('reunified_at')
                ->update(['reunified_at' => now()]);

            $this->rebuildHierarchyPaths($agent);
        }
    }

    // -------------------------------------------------------
    // Walk up the parent_id chain to find the first ACTIVE Group Leader.
    // Falls back to beneficiary_agent_id if the chain runs out (Section 59).
    // -------------------------------------------------------
    private function findNearestActiveGL(Agent $agent): ?Agent
    {
        $current = $agent->parent_id ? Agent::find($agent->parent_id) : null;

        while ($current) {
            if ($current->role === 'GROUP_LEADER' && $current->status === 'ACTIVE') {
                return $current;
            }

            if ($current->role === 'GROUP_LEADER' && $current->status !== 'ACTIVE' && $current->beneficiary_agent_id) {
                $beneficiary = Agent::find($current->beneficiary_agent_id);
                if ($beneficiary && $beneficiary->status === 'ACTIVE') {
                    return $beneficiary;
                }
            }

            $current = $current->parent_id ? Agent::find($current->parent_id) : null;
        }

        return null; // No active GL found anywhere up the chain
    }

    // -------------------------------------------------------
    // Permanent audit trail — Section 25.4
    // -------------------------------------------------------
    private function writeRoleHistory(
        Agent $agent,
        string $oldRole,
        string $newRole,
        ?string $oldParentId,
        ?string $newParentId,
        ?string $oldGroupId,
        ?string $newGroupId,
        string $reason
    ): void {
        DB::table('role_history')->insert([
            'history_id'     => Str::uuid()->toString(),
            'agent_id'       => $agent->agent_id,
            'old_role'       => $oldRole,
            'new_role'       => $newRole,
            'old_parent_id'  => $oldParentId,
            'new_parent_id'  => $newParentId,
            'old_group_id'   => $oldGroupId,
            'new_group_id'   => $newGroupId,
            'effective_date' => now(),
            'reason'         => $reason,
            'performed_by'   => null, // system-automatic
            'created_at'     => now(),
        ]);
    }

    private function rebuildHierarchyPaths(Agent $root): void
    {
        $descendants = Agent::where('hierarchy_path', 'like', "%/{$root->agent_id}/%")->get();
        foreach ($descendants as $d) {
            $path = $this->buildPath($d);
            DB::table('agents')->where('agent_id', $d->agent_id)->update(['hierarchy_path' => $path]);
        }
    }

    private function buildPath(Agent $agent): string
    {
        $path    = '/';
        $current = $agent;
        $chain   = [];

        while ($current) {
            $chain[] = $current->agent_id;
            $current = $current->parent_id ? Agent::find($current->parent_id) : null;
        }

        foreach (array_reverse($chain) as $id) {
            $path .= $id . '/';
        }

        return $path;
    }
}
