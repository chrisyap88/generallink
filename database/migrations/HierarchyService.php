<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HierarchyService
{
    // -------------------------------------------------------
    // FIXED 03 Jul 2026 — Generate agent_code matching the live convention
    // (e.g. sponsor "1-4" + next child "3" = "1-4-3"), NOT the old
    // "C0001-0" format. Uses the new next_child_seq column, incremented
    // atomically under a row lock, instead of "COUNT(children) + 1" —
    // which is unsafe once many people can register at the same moment
    // (100k+ groups scale).
    //
    // Sets BOTH agent_code and member_code to the same value, matching
    // what's already true for every existing live agent (verified via
    // tinker on Grace Tan's record — both fields identical).
    // -------------------------------------------------------
    public function generateAgentCode(Agent $sponsor): string
    {
        $retries = 0;

        do {
            $newCode = DB::transaction(function () use ($sponsor) {
                // Lock the sponsor's row so two simultaneous registrations
                // under the same sponsor can never get the same number.
                $locked = Agent::lockForUpdate()->find($sponsor->agent_id);

                $locked->increment('next_child_seq');
                $nextSeq = $locked->next_child_seq;

                // Sponsor with no code yet (shouldn't normally happen — GL
                // codes are assigned outside self-registration — but guarded
                // defensively rather than producing "-1").
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

    // Kept for backward compatibility with any existing callers —
    // now just calls the corrected method above.
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
    // Register a new agent under a sponsor
    // FIXED 03 Jul 2026 — now sets agent_code (was never being set before),
    // and origin_group_id (permanent, set once, never changed afterward).
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
                'origin_group_id'        => $sponsor->group_id, // permanent — never changed after this
                'hierarchy_path'         => $hierarchyPath,
                'role'                   => 'INTRODUCER',
                'status'                 => 'ACTIVE',
                'recruitable_tier_depth' => $tierDepth,
                'recruitment_blocked'    => false,
                'created_by'             => $createdBy,
            ]));

            $this->evaluateRankChange($sponsor);

            return $agent;
        });
    }

    // -------------------------------------------------------
    // MODULE 2 — Auto Promotion / Demotion Engine (unchanged for now —
    // Decisions 4/5/6 will be added in the next file, separately, since
    // that's a bigger, distinct change to promote()/demote())
    // -------------------------------------------------------
    public function evaluateRankChange(Agent $agent): void
    {
        DB::transaction(function () use ($agent) {

            $locked = Agent::lockForUpdate()->find($agent->agent_id);
            if (! $locked) return;

            if ($locked->isIntroducer()) {
                $directIntroducers = Agent::where('parent_id', $locked->agent_id)
                                          ->where('status', 'ACTIVE')
                                          ->where('role', 'INTRODUCER')
                                          ->count();
                if ($directIntroducers >= 3) {
                    $this->promote($locked, 'TEAM_LEADER');
                }
            } elseif ($locked->isTeamLeader()) {
                $directTLs = Agent::where('parent_id', $locked->agent_id)
                                  ->where('status', 'ACTIVE')
                                  ->where('role', 'TEAM_LEADER')
                                  ->count();
                if ($directTLs >= 3) {
                    $this->promote($locked, 'GROUP_LEADER');
                }
                $directActiveIntroducers = Agent::where('parent_id', $locked->agent_id)
                                                ->where('status', 'ACTIVE')
                                                ->where('role', 'INTRODUCER')
                                                ->count();
                if ($directActiveIntroducers < 3 && $locked->isTeamLeader()) {
                    $this->demote($locked, 'INTRODUCER');
                }
            } elseif ($locked->isGroupLeader()) {
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
            'recruitment_blocked' => false,
        ]);

        $this->rebuildHierarchyPaths($agent);

        AuditService::logChange('agents', $agent->agent_id, 'PROMOTE', ['role' => $oldRole], ['role' => $newRole]);
    }

    private function demote(Agent $agent, string $newRole): void
    {
        $oldRole = $agent->role;
        $agent->update(['role' => $newRole]);

        if ($agent->parent_id) {
            $parent = Agent::find($agent->parent_id);
            if ($parent) $this->evaluateRankChange($parent);
        }

        AuditService::logChange('agents', $agent->agent_id, 'DEMOTE', ['role' => $oldRole], ['role' => $newRole]);
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
