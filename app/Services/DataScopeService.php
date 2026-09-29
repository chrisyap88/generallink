<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DataScopeService
{
    protected Agent $agent;

    public function __construct()
    {
        $this->agent = auth('agent')->user();
    }

    /**
     * Is Admin — no filter applied
     */
    public function isAdmin(): bool
    {
        return $this->agent->role === 'ADMIN';
    }

    /**
     * Is Group Leader
     */
    public function isGL(): bool
    {
        return $this->agent->role === 'GROUP_LEADER';
    }

    /**
     * Is Team Leader
     */
    public function isTL(): bool
    {
        return $this->agent->role === 'TEAM_LEADER';
    }

    /**
     * Is Introducer
     */
    public function isIntroducer(): bool
    {
        return $this->agent->role === 'INTRODUCER';
    }

    /**
     * Get logged in agent_id
     */
    public function agentId(): string
    {
        return $this->agent->agent_id;
    }

    /**
     * Get group_id of logged in agent
     */
    public function groupId(): ?string
    {
        return $this->agent->group_id ?? null;
    }

    /**
     * Get all agent_ids in scope — cached per group
     * Admin: all agents
     * GL: all agents in his group
     * TL: himself + EVERY Introducer below him, no matter how deep
     *     (an Introducer can recruit another Introducer, who can recruit
     *     another, etc. — up to tier_recruitment_config's limit)
     * Introducer: himself + every Introducer HE recruited, no matter how
     *     deep — same reasoning, fixed 18 Jul 2026 per Chris: a nested
     *     introducer's submissions were invisible to their own recruiter
     *     because this used to only match direct parent_id (one level).
     *
     * Uses agents.hierarchy_path (e.g. "/gl/tl/introA/introB/") the same
     * way HierarchyService already does elsewhere in this codebase
     * (e.g. its own "%/{$root->agent_id}/%" descendant lookups) — a
     * plain LIKE match against this agent's own ID as a path segment
     * finds every descendant regardless of depth, without a recursive
     * query.
     */
    public function getAgentIds(): array
    {
        $cacheKey = $this->getCacheKey('agent_ids');

        return Cache::remember($cacheKey, 300, function () {
            if ($this->isAdmin()) {
                return DB::table('agents')
                    ->where('is_deleted', false)
                    ->pluck('agent_id')
                    ->toArray();
            }

            if ($this->isGL()) {
                return DB::table('agents')
                    ->where('group_id', $this->groupId())
                    ->where('is_deleted', false)
                    ->pluck('agent_id')
                    ->toArray();
            }

            // TL and Introducer both need "myself + my entire downline,
            // however deep" — same query, just anchored at a different
            // agent_id.
            $ids = DB::table('agents')
                ->where('hierarchy_path', 'like', '%/' . $this->agentId() . '/%')
                ->where('is_deleted', false)
                ->pluck('agent_id')
                ->toArray();
            $ids[] = $this->agentId();
            return array_values(array_unique($ids));
        });
    }

    /**
     * Apply scope to an agents query builder
     * Usage: $scope->applyToQuery(Agent::query())
     */
    public function applyToQuery($query, string $table = 'agents', string $column = 'agent_id')
    {
        if ($this->isAdmin()) {
            return $query; // No filter
        }

        if ($this->isGL()) {
            return $query->where("{$table}.group_id", $this->groupId());
        }

        if ($this->isTL()) {
            return $query->where(function ($q) use ($table, $column) {
                $q->where("{$table}.{$column}", $this->agentId())
                  ->orWhere("{$table}.parent_id", $this->agentId());
            });
        }

        // Introducer
        return $query->where("{$table}.{$column}", $this->agentId());
    }

    /**
     * Apply scope to sales_transactions query
     */
    public function applyToTransactions($query, string $table = 'sales_transactions')
    {
        if ($this->isAdmin()) {
            return $query;
        }

        $agentIds = $this->getAgentIds();
        return $query->whereIn("{$table}.agent_id", $agentIds);
    }

    /**
     * Apply scope to commission_transactions query
     */
    public function applyToCommissions($query, string $table = 'commission_transactions')
    {
        if ($this->isAdmin()) {
            return $query;
        }

        $agentIds = $this->getAgentIds();
        return $query->whereIn("{$table}.agent_id", $agentIds);
    }

    /**
     * Apply scope to customers query.
     * CHANGED 19 Jul 2026 — per Chris: customer/prospect visibility is
     * deliberately NOT the same rule as transactions/commissions above.
     * Every non-Admin role (GL, TL, Introducer alike) is locked to
     * customers/prospects they personally own — never their downline's —
     * so an upline can never see, open, edit, or deactivate a downline
     * agent's own contacts. This was previously getAgentIds() (whole
     * downline), same as applyToTransactions/applyToCommissions; those
     * two are untouched (earning income / renewal forecast still need
     * team-wide visibility) — only customer-record access changed.
     * Admin still sees everyone.
     */
    public function applyToCustomers($query, string $table = 'customers')
    {
        if ($this->isAdmin()) {
            return $query;
        }

        return $query->where("{$table}.owned_by_agent_id", $this->agentId());
    }

    /**
     * Verify an agent_id is within scope — throws 403 if not
     */
    public function verifyAgentAccess(string $agentId): void
    {
        if ($this->isAdmin()) return;

        $agentIds = $this->getAgentIds();

        if (!in_array($agentId, $agentIds)) {
            abort(403, 'Access denied — agent outside your scope.');
        }
    }

    /**
     * NEW 21 Jul 2026 — Help Desk internal messaging. Per Chris: any
     * agent may message their own UPLINE (walking parent_id all the
     * way up) and their own DOWNLINE (however deep — whole group for a
     * GL, every recursive Introducer for a TL/Introducer), but NEVER
     * sideways to a peer at the same level. Admin sits above every
     * chain and may message anyone. This works for an arbitrary agent
     * row (not just the logged-in one) so Cc scoping below can compute
     * "the recipient's own chain" too, not only the sender's.
     */
    public function helpDeskEligibleAgentIdsFor(object $agent): array
    {
        if ($agent->role === 'ADMIN') {
            return DB::table('agents')
                ->where('is_deleted', false)
                ->where('agent_id', '!=', $agent->agent_id)
                ->pluck('agent_id')
                ->toArray();
        }

        $ids = collect();

        // Downline — whole group for a GL, every recursive descendant
        // for a TL/Introducer. Same technique as getAgentIds() above,
        // just anchored at an arbitrary $agent rather than the caller.
        if ($agent->role === 'GROUP_LEADER') {
            $ids = $ids->merge(
                DB::table('agents')->where('group_id', $agent->group_id)->where('is_deleted', false)->pluck('agent_id')
            );
        } else {
            $ids = $ids->merge(
                DB::table('agents')->where('hierarchy_path', 'like', '%/' . $agent->agent_id . '/%')->where('is_deleted', false)->pluck('agent_id')
            );
        }

        // Upline — walk parent_id all the way up (Introducer -> TL ->
        // GL), plus Admin is always reachable regardless of whether the
        // chain literally terminates at an ADMIN row.
        $current = $agent;
        while ($current->parent_id) {
            $parent = DB::table('agents')->where('agent_id', $current->parent_id)->where('is_deleted', false)->first();
            if (!$parent) break;
            $ids->push($parent->agent_id);
            $current = $parent;
        }
        $ids = $ids->merge(DB::table('agents')->where('role', 'ADMIN')->where('is_deleted', false)->pluck('agent_id'));

        return $ids->unique()->reject(fn ($id) => $id === $agent->agent_id)->values()->all();
    }

    /**
     * Eligible Help Desk recipients for the CURRENTLY logged-in agent.
     */
    public function helpDeskEligibleAgents()
    {
        $ids = $this->helpDeskEligibleAgentIdsFor($this->agent);
        return DB::table('agents')->whereIn('agent_id', $ids)->where('is_deleted', false)->orderBy('full_name')->get();
    }

    /**
     * Security guard for composing/replying: is $agentId genuinely
     * within the CALLER's own upline/downline? Aborts 403 if not —
     * mirrors verifyAgentAccess()'s shape but uses the Help Desk rule
     * (upline+downline) instead of the "whole team" rule used elsewhere.
     */
    public function verifyHelpDeskAccess(string $agentId): void
    {
        if ($this->isAdmin()) return;

        if (!in_array($agentId, $this->helpDeskEligibleAgentIdsFor($this->agent))) {
            abort(403, 'You can only message your own upline or downline.');
        }
    }

    /**
     * REDESIGNED 12 Aug 2026 (second pass) — per Chris: "i am the admin i
     * write tan boon hwa i should able to cc all in the group... cc to
     * GL, cc to all TL within chris yap group and all introducer within
     * chris yap and all the TL. same go to Tan Boon Hwa write to anyone
     * within his group (chris yap) and also able to cc all the admin."
     * So Cc is always the UNION of two pools: (1) everyone in the
     * relevant GL group — the GL, every TL, every Introducer under that
     * GL — found via group_id of whichever party (sender or recipient)
     * actually has one, and (2) every Admin, always, because Admin sits
     * above every group and any group member should be able to loop
     * Admin in, and Admin should always be able to loop in other Admins
     * too. This single rule naturally also covers Admin-to-Admin
     * (group_id is null on both sides, so only pool (2) applies, which
     * is exactly "every other Admin") — replacing the earlier separate
     * Admin-to-Admin special case, which wrongly excluded the group when
     * an Admin messaged a non-Admin.
     */
    public function helpDeskCcOptionsFor(string $senderAgentId, string $recipientAgentId)
    {
        $sender = DB::table('agents')->where('agent_id', $senderAgentId)->first();
        $recipient = DB::table('agents')->where('agent_id', $recipientAgentId)->first();
        if (!$sender || !$recipient) return collect();

        $groupId = $sender->group_id ?? $recipient->group_id ?? null;

        return DB::table('agents')
            ->where('is_deleted', false)
            ->where(function ($q) use ($groupId) {
                $q->where('role', 'ADMIN');
                if ($groupId) {
                    $q->orWhere('group_id', $groupId);
                }
            })
            ->whereNotIn('agent_id', [$senderAgentId, $recipientAgentId])
            ->orderByRaw("CASE WHEN role = 'ADMIN' THEN 0 ELSE 1 END")
            ->orderBy('full_name')
            ->get();
    }

    /**
     * NEW 8 Aug 2026 — Task #83. Per Chris's confirmed decision: an agent
     * may only send a WhatsApp message to a phone number that belongs to
     * someone already within their own visibility scope — their own
     * customers (owned_by_agent_id, same owner-only rule as
     * applyToCustomers()), or their own upline/downline agents (same rule
     * as Help Desk messaging — helpDeskEligibleAgentIdsFor()). A number
     * that matches nobody in GeneralLink at all is also blocked — otherwise
     * the whole rule would be pointless (anyone could just type a random
     * number to bypass it). Admin is exempt, same as everywhere else in
     * this service.
     *
     * $normalizedPhone must already have non-digits stripped (same format
     * WhatsAppController::send() sends to Meta) so it matches the digits
     * stored in customers.phone / agents.phone.
     *
     * Returns: ['allowed' => bool, 'type' => 'CUSTOMER'|'AGENT'|'UNKNOWN',
     * 'id' => string|null, 'name' => string|null, 'reason' => string|null]
     * — 'reason' is only set when 'allowed' is false, and is written to be
     * shown directly to the agent (cause), alongside the fix/alternative
     * added by the caller.
     */
    public function verifyWhatsAppRecipient(string $normalizedPhone): array
    {
        $customer = DB::table('customers')
            ->where('phone', $normalizedPhone)
            ->where('is_deleted', false)
            ->first();

        if ($customer) {
            if ($this->isAdmin() || $customer->owned_by_agent_id === $this->agentId()) {
                return ['allowed' => true, 'type' => 'CUSTOMER', 'id' => $customer->customer_id, 'name' => $customer->full_name, 'reason' => null];
            }
            return ['allowed' => false, 'type' => 'CUSTOMER', 'id' => $customer->customer_id, 'name' => $customer->full_name, 'reason' => 'This number belongs to a customer owned by another agent, not one of yours.'];
        }

        $agent = DB::table('agents')
            ->where('phone', $normalizedPhone)
            ->where('is_deleted', false)
            ->first();

        if ($agent) {
            if ($this->isAdmin() || $agent->agent_id === $this->agentId() || in_array($agent->agent_id, $this->helpDeskEligibleAgentIdsFor($this->agent), true)) {
                return ['allowed' => true, 'type' => 'AGENT', 'id' => $agent->agent_id, 'name' => $agent->full_name, 'reason' => null];
            }
            return ['allowed' => false, 'type' => 'AGENT', 'id' => $agent->agent_id, 'name' => $agent->full_name, 'reason' => 'This number belongs to an agent outside your own upline or downline.'];
        }

        if ($this->isAdmin()) {
            return ['allowed' => true, 'type' => 'UNKNOWN', 'id' => null, 'name' => null, 'reason' => null];
        }

        return ['allowed' => false, 'type' => 'UNKNOWN', 'id' => null, 'name' => null, 'reason' => 'This number doesn\'t match any customer or agent in GeneralLink.'];
    }

    /**
     * Get unique cache key scoped to this agent/group
     */
    public function getCacheKey(string $prefix): string
    {
        if ($this->isAdmin()) {
            return "admin_{$prefix}";
        }

        if ($this->isGL()) {
            return "gl_{$this->groupId()}_{$prefix}";
        }

        if ($this->isTL()) {
            return "tl_{$this->agentId()}_{$prefix}";
        }

        return "intro_{$this->agentId()}_{$prefix}";
    }

    /**
     * Invalidate all cache for this agent's scope
     */
    public function invalidateCache(): void
    {
        $prefixes = ['agent_ids', 'dashboard_metrics', 'network_summary', 'transactions'];
        foreach ($prefixes as $prefix) {
            Cache::forget($this->getCacheKey($prefix));
        }
    }
}
