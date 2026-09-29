<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HierarchyService
{
    // -------------------------------------------------------
    // MODULE 2B — Generate hierarchical member code
    // -------------------------------------------------------
    public function generateMemberCode(Agent $sponsor): string
    {
        // Get the group config for separator character
        $group = DB::table('groups')->where('group_id', $sponsor->group_id)->first();
        $sep   = $group?->separator_char ?? '-';

        // If sponsor is the GL (no member code yet), this is the root member
        if (! $sponsor->member_code) {
            $groupCode = $group?->group_code ?? 'X0001';
            $suffix    = $group?->root_member_suffix ?? '0';
            return $groupCode . $sep . $suffix;
        }

        // Count existing direct downlines of sponsor to get next index
        $retries = 0;
        do {
            $count    = Agent::where('parent_id', $sponsor->agent_id)->count();
            $newIndex = $count + 1 + $retries;
            $newCode  = $sponsor->member_code . $sep . $newIndex;

            // Check uniqueness — handles race condition
            $exists = Agent::where('member_code', $newCode)->exists();
            $retries++;
        } while ($exists && $retries < 10);

        if ($exists) throw new \Exception('Could not generate unique member code. Please retry.');

        return $newCode;
    }

    // -------------------------------------------------------
    // MODULE 2A — Tier restriction check
    // -------------------------------------------------------
    public function canRecruit(Agent $sponsor): bool
    {
        // GL, TL, Admin can always recruit
        if (! $sponsor->isIntroducer()) return true;

        // Already flagged as blocked
        if ($sponsor->recruitment_blocked) return false;

        // Get max tier limit (group-specific or global)
        $limit = DB::table('tier_recruitment_config')
            ->where(fn($q) => $q->where('group_id', $sponsor->group_id)->orWhereNull('group_id'))
            ->where('is_active', true)
            ->orderByRaw('group_id IS NULL ASC') // prefer group-specific
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
    // Register a new agent under a sponsor
    // -------------------------------------------------------
    public function registerUnderSponsor(Agent $sponsor, array $agentData, string $createdBy): Agent
    {
        if (! $this->canRecruit($sponsor)) {
            throw new \Exception($this->getRecruitmentBlockReason($sponsor));
        }

        return DB::transaction(function () use ($sponsor, $agentData, $createdBy) {

            $memberCode  = $this->generateMemberCode($sponsor);
            $newAgentId  = Str::uuid()->toString();
            $tierDepth   = $sponsor->recruitable_tier_depth + 1;
            $hierarchyPath = $sponsor->hierarchy_path . $newAgentId . '/';

            $agent = Agent::create(array_merge($agentData, [
                'agent_id'               => $newAgentId,
                'member_code'            => $memberCode,
                'parent_id'              => $sponsor->agent_id,
                'group_id'               => $sponsor->group_id,
                'hierarchy_path'         => $hierarchyPath,
                'role'                   => 'INTRODUCER',
                'status'                 => 'ACTIVE',
                'recruitable_tier_depth' => $tierDepth,
                'recruitment_blocked'    => false,
                'created_by'             => $createdBy,
            ]));

            // Evaluate sponsor for promotion after new recruit
            $this->evaluateRankChange($sponsor);

            return $agent;
        });
    }

    // -------------------------------------------------------
    // MODULE 2 — Auto Promotion / Demotion Engine
    // Uses row-level locking (SELECT FOR UPDATE) to prevent race conditions
    // -------------------------------------------------------
    public function evaluateRankChange(Agent $agent): void
    {
        DB::transaction(function () use ($agent) {

            // Lock the agent row
            $locked = Agent::lockForUpdate()->find($agent->agent_id);
            if (! $locked) return;

            $directActiveCount = Agent::where('parent_id', $locked->agent_id)
                                      ->where('status', 'ACTIVE')
                                      ->count();

            if ($locked->isIntroducer()) {
                // Promote to TL if 3+ active direct Introducers
                $directIntroducers = Agent::where('parent_id', $locked->agent_id)
                                          ->where('status', 'ACTIVE')
                                          ->where('role', 'INTRODUCER')
                                          ->count();
                if ($directIntroducers >= 3) {
                    $this->promote($locked, 'TEAM_LEADER');
                }
            } elseif ($locked->isTeamLeader()) {
                // Promote to GL if 3+ active direct TLs
                $directTLs = Agent::where('parent_id', $locked->agent_id)
                                  ->where('status', 'ACTIVE')
                                  ->where('role', 'TEAM_LEADER')
                                  ->count();
                if ($directTLs >= 3) {
                    $this->promote($locked, 'GROUP_LEADER');
                }
                // Demote if falls below 3 active direct Introducers
                $directActiveIntroducers = Agent::where('parent_id', $locked->agent_id)
                                                ->where('status', 'ACTIVE')
                                                ->where('role', 'INTRODUCER')
                                                ->count();
                if ($directActiveIntroducers < 3 && $locked->isTeamLeader()) {
                    $this->demote($locked, 'INTRODUCER');
                }
            } elseif ($locked->isGroupLeader()) {
                // Demote if falls below 3 active direct TLs
                $directActiveTLs = Agent::where('parent_id', $locked->agent_id)
                                        ->where('status', 'ACTIVE')
                                        ->where('role', 'TEAM_LEADER')
                                        ->count();
                if ($directActiveTLs < 3) {
                    $this->demote($locked, 'TEAM_LEADER');
                }
            }
        });
    }

    private function promote(Agent $agent, string $newRole): void
    {
        $oldRole = $agent->role;
        $agent->update([
            'role'                => $newRole,
            'recruitment_blocked' => false, // Clear block on TL promotion
        ]);

        // Rebuild hierarchy path for all descendants
        $this->rebuildHierarchyPaths($agent);

        AuditService::logChange('agents', $agent->agent_id, 'PROMOTE', ['role' => $oldRole], ['role' => $newRole]);
    }

    private function demote(Agent $agent, string $newRole): void
    {
        $oldRole = $agent->role;
        $agent->update(['role' => $newRole]);

        // Cascade evaluation to parent (demotion may affect grandparent's rank)
        if ($agent->parent_id) {
            $parent = Agent::find($agent->parent_id);
            if ($parent) $this->evaluateRankChange($parent);
        }

        AuditService::logChange('agents', $agent->agent_id, 'DEMOTE', ['role' => $oldRole], ['role' => $newRole]);
    }

    // -------------------------------------------------------
    // Rebuild hierarchy_path for all descendants after rank change
    // -------------------------------------------------------
    private function rebuildHierarchyPaths(Agent $root): void
    {
        $descendants = Agent::where('hierarchy_path', 'like', "%/{$root->agent_id}/%")->get();
        foreach ($descendants as $d) {
            // Recalculate path by walking up
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
