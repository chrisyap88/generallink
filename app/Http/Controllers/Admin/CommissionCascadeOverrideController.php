<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 31 Jul 2026 — Rank system redesign, Phase 2. Per Chris's Legal
// Business Eco System example, confirmed explicitly: this is ADMIN-ONLY
// configuration, set up per specific GL or TL only when that individual
// asks for it — never a self-service screen for the agent themselves,
// and never compulsory for everyone under an Organization Rewards Group.
// Any GL/TL with no override here just keeps using the structure's
// shared flat % (group_leader_pct/team_leader_pct), completely
// unaffected — this table is additive, opt-in, per individual agent.
//
// Scope: only applies to NON Rank-Only structures for now — Chris's
// examples were all framed as "GL keeps 2% of his flat %, hands a TL an
// 8% envelope", i.e. the classic 3-way role split, not the separate
// Rank-Only mode (RankAllocationController). If Chris wants this to also
// work for Rank-Only structures later, that's a deliberate follow-up,
// not assumed here.
//
// IMPORTANT — this controller only lets Admin DEFINE overrides and saves
// them. It does NOT yet change how CommissionEngine actually pays a real
// transaction — that's a deliberately separate, not-yet-built step
// (Phase 2b), same two-step pattern already used for the rank system
// itself (schema + screen first, engine wiring after, so a real-money
// change is never bundled with a screen Chris hasn't seen yet).
class CommissionCascadeOverrideController extends Controller
{
    public function index(string $structureId)
    {
        $structure = $this->loadStructure($structureId);

        $overrides = DB::table('commission_cascade_overrides as o')
            ->join('agents as a', 'a.agent_id', '=', 'o.owner_agent_id')
            ->where('o.structure_id', $structureId)
            ->orderBy('a.role')
            ->orderBy('a.full_name')
            ->get(['o.*', 'a.full_name', 'a.agent_code', 'a.role']);

        // Quick allocated-total per override, for the list view.
        $allocatedTotals = DB::table('commission_cascade_allocations as ca')
            ->join('commission_cascade_overrides as o', 'o.override_id', '=', 'ca.override_id')
            ->where('o.structure_id', $structureId)
            ->groupBy('ca.override_id')
            ->selectRaw('ca.override_id, SUM(ca.pct) as total')
            ->pluck('total', 'override_id');

        return view('masterfile.cascade-overrides', compact('structure', 'overrides', 'allocatedTotals'));
    }

    public function agentTypeahead(Request $request, string $structureId)
    {
        $structure = $this->loadStructure($structureId);
        $q = trim((string) $request->get('q', ''));
        $parentId = $request->get('parent_id'); // when set, restrict to direct downline of this agent only

        $query = DB::table('agents')
            ->where('is_deleted', false)
            ->whereIn('role', ['GROUP_LEADER', 'TEAM_LEADER']);

        if ($parentId) {
            $query->where('parent_id', $parentId);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('full_name', 'like', "%{$q}%")->orWhere('agent_code', 'like', "%{$q}%");
            });
        }

        $agents = $query->orderBy('full_name')->limit(15)->get(['agent_id', 'full_name', 'agent_code', 'role']);

        return response()->json($agents->map(fn($a) => [
            'agent_id'  => $a->agent_id,
            'label'     => "{$a->full_name} ({$a->agent_code}) — " . RoleLabelService::shortLabel($a->role),
        ]));
    }

    // Loads (or shows the create form for) the override belonging to a
    // specific owner agent under this structure.
    public function edit(string $structureId, string $agentId)
    {
        $structure = $this->loadStructure($structureId);
        $owner = DB::table('agents')->where('agent_id', $agentId)->firstOrFail();

        abort_if(!in_array($owner->role, ['GROUP_LEADER', 'TEAM_LEADER']), 422, 'Cascade overrides only apply to Group Leaders and Team Leaders.');

        $envelope = $this->resolveEnvelope($structure, $owner);

        $override = DB::table('commission_cascade_overrides')
            ->where('structure_id', $structureId)->where('owner_agent_id', $agentId)->first();

        $agentAllocations = collect();
        $rankAllocations = collect();
        if ($override) {
            $allocs = DB::table('commission_cascade_allocations')->where('override_id', $override->override_id)->get();
            $agentAllocations = $allocs->whereNotNull('target_agent_id')->values();
            $rankAllocations = $allocs->whereNotNull('target_rank_id')->pluck('pct', 'target_rank_id');
        }

        // Named-agent allocations only make sense one role down from the
        // owner (GL names a TL, TL names... nobody by name, TLs use Rank
        // instead since they can have many Introducers).
        $childRoleForNaming = $owner->role === 'GROUP_LEADER' ? 'TEAM_LEADER' : null;
        $rankRoleForOwner   = $owner->role === 'GROUP_LEADER' ? 'TEAM_LEADER' : 'INTRODUCER';

        // Ranks are scoped per Organization Rewards Group (role_ranks.group_label_id),
        // and commission_structures has no group_label_id of its own (one
        // structure is shared by every group selling that vendor/product)
        // — so the correct scope here is the OWNER's own group_label_id,
        // not the structure's.
        $rankGroupLabelId = $owner->group_label_id;
        $ranksForOwner = DB::table('role_ranks')
            ->where('role', $rankRoleForOwner)
            ->where('group_label_id', $rankGroupLabelId)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn($r) => $r->rank_no, SORT_NATURAL)->values();

        // Named agents already picked (so the typeahead boxes can show
        // their names on reload, not just hidden agent_ids).
        $namedAgentNames = collect();
        if ($agentAllocations->isNotEmpty()) {
            $namedAgentNames = DB::table('agents')->whereIn('agent_id', $agentAllocations->pluck('target_agent_id'))->pluck('full_name', 'agent_id');
        }

        return view('masterfile.cascade-override-edit', compact(
            'structure', 'owner', 'envelope', 'override', 'agentAllocations', 'rankAllocations',
            'childRoleForNaming', 'ranksForOwner', 'namedAgentNames'
        ));
    }

    public function update(Request $request, string $structureId, string $agentId)
    {
        $structure = $this->loadStructure($structureId);
        $owner = DB::table('agents')->where('agent_id', $agentId)->firstOrFail();
        $envelope = $this->resolveEnvelope($structure, $owner);

        $selfPct = (float) $request->input('self_pct', 0);
        $agentTargets = $request->input('agent_target', []); // [] of agent_id
        $agentPcts = $request->input('agent_pct', []);       // [] of pct, same index
        $rankPcts = $request->input('rank_pct', []);         // [rank_id] => pct

        $agentSum = 0.0;
        $validAgentRows = [];
        foreach ($agentTargets as $i => $targetAgentId) {
            $pct = (float) ($agentPcts[$i] ?? 0);
            if ($targetAgentId && $pct > 0) {
                $agentSum += $pct;
                $validAgentRows[] = ['target_agent_id' => $targetAgentId, 'pct' => $pct];
            }
        }
        $rankSum = 0.0;
        $validRankRows = [];
        foreach ($rankPcts as $rankId => $pct) {
            $pct = (float) $pct;
            if ($pct > 0) {
                $rankSum += $pct;
                $validRankRows[] = ['target_rank_id' => $rankId, 'pct' => $pct];
            }
        }

        $totalUsed = $selfPct + $agentSum + $rankSum;
        // Confirmed by Chris: children (self + downline allocations)
        // must NOT EXCEED the envelope this owner received — they do
        // NOT have to add up to exactly that number.
        if ($totalUsed - $envelope > 0.001) {
            return back()->withErrors(['splits' => "This allocation (self {$selfPct}% + downline " . ($agentSum + $rankSum) . "%  = {$totalUsed}%) exceeds the {$envelope}% envelope {$owner->full_name} received. Reduce it so the total is {$envelope}% or less."])->withInput();
        }

        DB::transaction(function () use ($structureId, $agentId, $selfPct, $validAgentRows, $validRankRows) {
            $overrideId = DB::table('commission_cascade_overrides')->where('structure_id', $structureId)->where('owner_agent_id', $agentId)->value('override_id');

            if (!$overrideId) {
                $overrideId = (string) Str::uuid();
                DB::table('commission_cascade_overrides')->insert([
                    'override_id'    => $overrideId,
                    'structure_id'   => $structureId,
                    'owner_agent_id' => $agentId,
                    'self_pct'       => $selfPct,
                    'created_by'     => Auth::guard('agent')->id(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]);
            } else {
                DB::table('commission_cascade_overrides')->where('override_id', $overrideId)->update([
                    'self_pct'   => $selfPct,
                    'updated_at' => now(),
                ]);
            }

            DB::table('commission_cascade_allocations')->where('override_id', $overrideId)->delete();

            $rows = [];
            foreach ($validAgentRows as $r) {
                $rows[] = [
                    'allocation_id'    => (string) Str::uuid(),
                    'override_id'      => $overrideId,
                    'target_agent_id'  => $r['target_agent_id'],
                    'target_rank_id'   => null,
                    'pct'              => $r['pct'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            foreach ($validRankRows as $r) {
                $rows[] = [
                    'allocation_id'    => (string) Str::uuid(),
                    'override_id'      => $overrideId,
                    'target_agent_id'  => null,
                    'target_rank_id'   => $r['target_rank_id'],
                    'pct'              => $r['pct'],
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            if (!empty($rows)) {
                DB::table('commission_cascade_allocations')->insert($rows);
            }
        });

        AuditService::logChange('commission_cascade_overrides', $agentId, 'CASCADE_OVERRIDE_SAVED', null, $request->all());

        return redirect()->route('admin.masterfile.commissions.cascade-overrides', $structureId)
            ->with('success', "Cascade override saved for {$owner->full_name}.");
    }

    public function destroy(string $structureId, string $agentId)
    {
        $overrideId = DB::table('commission_cascade_overrides')->where('structure_id', $structureId)->where('owner_agent_id', $agentId)->value('override_id');
        if ($overrideId) {
            DB::table('commission_cascade_allocations')->where('override_id', $overrideId)->delete();
            DB::table('commission_cascade_overrides')->where('override_id', $overrideId)->delete();
        }
        return redirect()->route('admin.masterfile.commissions.cascade-overrides', $structureId)->with('success', 'Cascade override removed — this agent falls back to the structure\'s shared %.');
    }

    private function loadStructure(string $structureId): object
    {
        $structure = DB::table('commission_structures as cs')
            ->join('vendors as v', 'v.vendor_id', '=', 'cs.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'cs.product_id')
            ->where('cs.structure_id', $structureId)
            ->first(['cs.*', 'v.vendor_name', 'p.product_name']);

        abort_if(!$structure, 404, 'Earning Income Structure not found.');
        abort_if($structure->is_rank_only, 422, 'Cascade overrides only apply to the normal Role Allocation mode, not Rank-Only structures.');

        return $structure;
    }

    // How much this owner received, before they decide their own split.
    // GL: the structure's own flat group_leader_pct — always the same
    // for every GL under this structure, no matter who their upline is.
    // TL: whatever their own GL specifically handed them via a named
    // allocation row — or, if their GL has no override at all (or didn't
    // name this TL specifically), the structure's flat team_leader_pct,
    // same as every other un-overridden TL gets today.
    private function resolveEnvelope(object $structure, object $owner): float
    {
        if ($owner->role === 'GROUP_LEADER') {
            return (float) $structure->group_leader_pct;
        }

        if ($owner->parent_id) {
            $glOverrideId = DB::table('commission_cascade_overrides')
                ->where('structure_id', $structure->structure_id)
                ->where('owner_agent_id', $owner->parent_id)
                ->value('override_id');

            if ($glOverrideId) {
                $named = DB::table('commission_cascade_allocations')
                    ->where('override_id', $glOverrideId)
                    ->where('target_agent_id', $owner->agent_id)
                    ->value('pct');
                if ($named !== null) {
                    return (float) $named;
                }
            }
        }

        return (float) $structure->team_leader_pct;
    }
}
