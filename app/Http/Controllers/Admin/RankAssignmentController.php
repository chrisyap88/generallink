<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// REBUILT 1 Aug 2026 (2) — per Chris, reversing the 31 Jul 2026 "bucket
// only" decision documented below: "your rank assignment is crazy
// design how i assign to one specific introducer to rank no?" He needs
// to put ONE named agent at a specific rank, independent of every other
// agent who happens to share that same role + group — the bucket-wide
// screen genuinely cannot do that, by design, so it had to change.
//
// This screen now lists individual agents — filterable by Role and
// Organization Rewards Group, and searchable by name/code — each with
// their OWN rank dropdown, scoped to only the ranks that exist for
// THEIR role+group (never a rank from a different bucket). Saving
// updates only the agent rows actually shown/submitted; every other
// agent's rank is left untouched.
//
// ORIGINAL 31 Jul 2026 REBUILD NOTE (superseded, kept for history): "per
// Chris, explicit and final: rank belongs to the (Role, Special
// Privilege Group) combination as a WHOLE — never to an individual
// agent." That instruction has been explicitly reversed by Chris above.
class RankAssignmentController extends Controller
{
    private const ROLES = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];
    // REDUCED 8 Jan 2026 — per Chris: "no scroll in one page aiyo i say
    // so many time." 12 rows + the filter card + table header + footer
    // (Save button, pagination, Prev nav) don't all fit inside one
    // viewport without scrolling. Dropped to 7, matching the same
    // "guarantee no-scroll" fix already applied to the Rank Hierarchy
    // and other list screens elsewhere in this app.
    private const PER_PAGE = 7;

    public function index(Request $request)
    {
        // FIXED 18 Aug 2026 — per Chris: Rank Assignment only applies
        // to Organization Rewards Group (ORG) agents — ranks are an
        // ORG-only concept — so the group picker must only ever offer
        // ORG groups, not DSG/CBE groups mixed in.
        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $roleLabels  = collect(self::ROLES)->mapWithKeys(fn ($r) => [$r => RoleLabelService::label($r)]);

        $role         = $request->filled('role') ? $request->get('role') : null;
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : null;
        $search       = trim((string) $request->get('search', ''));

        // FIXED 1 Aug 2026 (3) — per Chris: "why suddenly show all when i
        // have not select?" Same standing rule already applied to Agent
        // Balances — never load every agent by default. The Filter form
        // below submits a hidden "filtered=1" marker; until that's been
        // submitted at least once, show nothing and ask Chris to filter
        // first, same as every other "no default load-all" search screen
        // in this app.
        if (!$request->boolean('filtered')) {
            return view('masterfile.rank-assignment', [
                'agents' => null, 'roleLabels' => $roleLabels, 'groupLabels' => $groupLabels,
                'role' => $role, 'groupLabelId' => $groupLabelId, 'search' => $search, 'ranksByBucket' => [],
            ]);
        }

        $query = DB::table('agents as a')
            ->leftJoin('group_labels as gl', 'gl.group_label_id', '=', 'a.group_label_id')
            ->leftJoin('role_ranks as rr', 'rr.rank_id', '=', 'a.rank_id')
            ->where('a.is_deleted', false)
            ->whereIn('a.role', self::ROLES);

        if ($role) {
            $query->where('a.role', $role);
        }

        // "System Default" is submitted as an empty-but-present value —
        // distinguish "filter not touched at all" (no whereIn/whereNull
        // applied) from "filter explicitly set to System Default" (must
        // whereNull), same convention used on the Rank Allocation screen.
        if ($request->has('group_label_id') && $request->get('group_label_id') !== '__any__') {
            $groupLabelId ? $query->where('a.group_label_id', $groupLabelId) : $query->whereNull('a.group_label_id');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('a.full_name', 'like', "%{$search}%")
                  ->orWhere('a.agent_code', 'like', "%{$search}%");
            });
        }

        $agents = $query->orderBy('a.role')->orderBy('gl.group_name')->orderBy('a.full_name')
            ->paginate(self::PER_PAGE, [
                'a.agent_id', 'a.agent_code', 'a.full_name', 'a.role', 'a.group_label_id',
                'gl.group_name', 'a.rank_id', 'rr.rank_name', 'rr.rank_no',
            ])
            ->withQueryString();

        // Ranks available per (role, group_label_id) combo actually
        // present on this page, keyed so the view can build each row's
        // OWN dropdown without ever offering a rank from a different
        // bucket.
        $allRanks = DB::table('role_ranks')->where('is_active', true)->get()
            ->sortBy(fn ($r) => $r->rank_no, SORT_NATURAL)->values();

        $ranksByBucket = [];
        foreach ($agents as $a) {
            $key = $a->role . '|' . ($a->group_label_id ?? 'default');
            if (!isset($ranksByBucket[$key])) {
                $ranksByBucket[$key] = $allRanks->where('role', $a->role)->where('group_label_id', $a->group_label_id)->values();
            }
        }

        return view('masterfile.rank-assignment', compact(
            'agents', 'roleLabels', 'groupLabels', 'role', 'groupLabelId', 'search', 'ranksByBucket'
        ));
    }

    public function update(Request $request)
    {
        $assignments = $request->input('rank', []); // agent_id => rank_id (or '' for No Rank)
        $agentIds = array_keys($assignments);

        $agents = DB::table('agents')->whereIn('agent_id', $agentIds)->where('is_deleted', false)
            ->get(['agent_id', 'role', 'group_label_id'])->keyBy('agent_id');

        $changed = 0;
        foreach ($assignments as $agentId => $rankId) {
            $agent = $agents->get($agentId);
            if (!$agent) {
                continue; // not a real/current agent id — ignore
            }

            $rankId = $rankId !== '' ? $rankId : null;

            // Defense in depth: even though the dropdown only ever offers
            // ranks valid for that agent's own role+group, verify server-
            // side too before writing, in case the form was tampered with.
            if ($rankId !== null) {
                $rankQuery = DB::table('role_ranks')->where('rank_id', $rankId)->where('role', $agent->role);
                $agent->group_label_id === null ? $rankQuery->whereNull('group_label_id') : $rankQuery->where('group_label_id', $agent->group_label_id);
                if (!$rankQuery->exists()) {
                    continue; // rank doesn't belong to this agent's role+group — skip, don't silently miscategorize
                }
            }

            DB::table('agents')->where('agent_id', $agentId)->update(['rank_id' => $rankId, 'updated_at' => now()]);
            $changed++;
        }

        return redirect()->route('admin.masterfile.rank-assignment', $request->only(['role', 'group_label_id', 'search', 'page']))
            ->with('success', "Saved — {$changed} agent(s) updated.");
    }
}
