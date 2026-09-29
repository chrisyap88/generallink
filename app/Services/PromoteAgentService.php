<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromoteAgentService
{
    // -------------------------------------------------------
    // Call this after any agent's downline count might have changed
    // (e.g. right after a new recruit activates their account). Checks
    // the SPONSOR (the person who might now have hit their promotion
    // threshold), not the newly-activated person themselves.
    // -------------------------------------------------------
    public function checkAndPromote(Agent $sponsor): void
    {
        if ($sponsor->role === 'INTRODUCER') {
            $this->checkIntroducerToTL($sponsor);
        } elseif ($sponsor->role === 'TEAM_LEADER') {
            $this->checkTLToGL($sponsor);
        }
        // GROUP_LEADER has no further promotion — GL is the top tier.
    }

    // Introducer -> Team Leader: 3 active DIRECT Introducer recruits
    // (spec 26.1 promotion threshold table).
    private function checkIntroducerToTL(Agent $introducer): void
    {
        $activeDirectRecruits = Agent::where('parent_id', $introducer->agent_id)
            ->where('role', 'INTRODUCER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->count();

        if ($activeDirectRecruits < 3) {
            return; // not yet at threshold
        }

        // Promoted TLs report to the Group Leader of their current
        // group (tier compression) — NOT to whoever recruited them,
        // since that person may not even be a TL/GL themselves.
        $group = $introducer->group_id ? DB::table('groups')->where('group_id', $introducer->group_id)->first() : null;
        $newParentId = $group ? Agent::where('group_id', $group->group_id)->where('role', 'GROUP_LEADER')->value('agent_id') : null;

        $oldRole = $introducer->role;
        $oldParentId = $introducer->parent_id;
        $oldGroupId = $introducer->group_id;

        // RULE (spec 25.4): role_history MUST be written BEFORE
        // changing parent_id or group_id.
        DB::table('role_history')->insert([
            'history_id'      => (string) Str::uuid(),
            'agent_id'        => $introducer->agent_id,
            'old_role'        => $oldRole,
            'new_role'        => 'TEAM_LEADER',
            'old_parent_id'   => $oldParentId,
            'new_parent_id'   => $newParentId,
            'old_group_id'    => $oldGroupId,
            'new_group_id'    => $oldGroupId, // group doesn't change at this tier
            'effective_date'  => now(),
            'reason'          => 'AUTO_PROMOTED',
            'performed_by'    => null, // system-triggered, not an Admin
            'created_at'      => now(),
        ]);

        $introducer->update([
            'role'      => 'TEAM_LEADER',
            'parent_id' => $newParentId,
        ]);

        AuditService::logChange('agents', $introducer->agent_id, 'ROLE_CHANGE', ['role' => $oldRole], ['role' => 'TEAM_LEADER']);

        $this->notifyPromotion($introducer, $oldRole, 'TEAM_LEADER');
    }

    // Team Leader -> Group Leader: 3 active DIRECT TL recruits (spec
    // 26.2 Method A - Auto-Promotion). Creates a brand new group,
    // moves the entire subtree, sets parent_id to NULL (breakaway).
    private function checkTLToGL(Agent $tl): void
    {
        $activeDirectTLRecruits = Agent::where('parent_id', $tl->agent_id)
            ->where('role', 'TEAM_LEADER')
            ->where('status', 'ACTIVE')
            ->where('is_deleted', false)
            ->count();

        if ($activeDirectTLRecruits < 3) {
            return; // not yet at threshold
        }

        $oldRole = $tl->role;
        $oldParentId = $tl->parent_id;
        $oldGroupId = $tl->group_id;

        // New, independent group — auto-named for now; Admin can rename
        // anytime via Group Maintenance (promotion itself never waits
        // on Admin, per spec: "fully automatic, NO Admin involvement").
        $newGroupId = (string) Str::uuid();
        DB::table('groups')->insert([
            'group_id'    => $newGroupId,
            'group_name'  => $tl->full_name . "'s Group",
            'group_code'  => 'G-' . strtoupper(Str::random(6)),
            'is_active'   => true,
            'created_by'  => null, // system-triggered
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // RULE (spec 25.4): role_history MUST be written BEFORE
        // changing parent_id or group_id.
        DB::table('role_history')->insert([
            'history_id'      => (string) Str::uuid(),
            'agent_id'        => $tl->agent_id,
            'old_role'        => $oldRole,
            'new_role'        => 'GROUP_LEADER',
            'old_parent_id'   => $oldParentId,
            'new_parent_id'   => null, // breakaway — no longer reports to anyone
            'old_group_id'    => $oldGroupId,
            'new_group_id'    => $newGroupId,
            'effective_date'  => now(),
            'reason'          => 'AUTO_PROMOTED',
            'performed_by'    => null,
            'created_at'      => now(),
        ]);

        // The entire subtree (their TLs, those TLs' Introducers, and
        // their own direct Introducers) moves to the new group together.
        $subtreeIds = $this->collectSubtreeIds($tl->agent_id);

        $tl->update([
            'role'             => 'GROUP_LEADER',
            'parent_id'        => null,
            'group_id'         => $newGroupId,
            'origin_group_id'  => $oldGroupId, // preserves where they came from
        ]);

        if (!empty($subtreeIds)) {
            Agent::whereIn('agent_id', $subtreeIds)->update(['group_id' => $newGroupId]);
        }

        AuditService::logChange('agents', $tl->agent_id, 'ROLE_CHANGE', ['role' => $oldRole], ['role' => 'GROUP_LEADER']);

        $this->notifyPromotion($tl, $oldRole, 'GROUP_LEADER');
    }

    // Recursively collects every descendant agent_id under a given
    // agent, so the whole subtree can move together on promotion.
    private function collectSubtreeIds(string $agentId): array
    {
        $direct = Agent::where('parent_id', $agentId)->where('is_deleted', false)->pluck('agent_id')->toArray();
        $all = $direct;
        foreach ($direct as $childId) {
            $all = array_merge($all, $this->collectSubtreeIds($childId));
        }
        return $all;
    }

    private function notifyPromotion(Agent $agent, string $oldRole, string $newRole): void
    {
        $sponsor = $agent->parent_id ? Agent::find($agent->parent_id) : null;
        $recipients = app(NotificationService::class)->recipientsForGroupBroadcast($agent, $sponsor);
        app(NotificationService::class)->notify(
            $recipients,
            'PROMOTION',
            'Promotion!',
            "Congratulations to {$agent->full_name} ({$agent->agent_code}) on their promotion from {$oldRole} to {$newRole}!",
            $agent->agent_id
        );
    }
}
