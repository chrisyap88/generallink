<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 27 Aug 2026 — per Chris: "master file maintenance ... this is
// where you set up ... temple, branch, state, HQ (all related CBE
// set up)." No web-based create existed for cbe_hierarchy_nodes before
// this — only a one-off Artisan import command used for the original
// 591-temple load. This controller adds the missing CREATE screen.
//
// Deliberately CREATE-only: the existing Profile tab on the CBE KPI
// screen (AdminCbeKpiController::updateProfile()) already lets an
// Admin/officer edit contact_person_1/2, address, city, postcode, and
// manage cbe_hierarchy_node_phones for a node that already exists — so
// there is no need to duplicate an edit form here. One flexible screen
// covers Temple, Branch, State, and HQ, since a CBE community's level
// names are entirely admin-defined (cbe_hierarchy_levels), never
// hardcoded — matching how the KPI dashboard already treats levels
// dynamically per community.
class AdminCbeHierarchyNodeController extends Controller
{
    // NEW 11 Sep 2026 (Task #413 follow-up) — per Chris's decision: a CBE
    // node officer may now reach create()/store() (see
    // RequireAdminOrCbeOfficer middleware, routes/web.php), but that
    // middleware only checks THAT they're an active officer, not WHICH
    // node. This is the server-side enforcement that they can only ever
    // touch their own node's subtree — never trust the group/level/parent
    // values a request sends, since those can be edited in the URL/form
    // regardless of what the officer's own sidebar link pre-fills.
    // Returns null for platform Admin (no scoping applied) or for an
    // officer whose own node row can't be found (treated as no access).
    private function currentOfficerNode(): ?object
    {
        $agent = Auth::guard('agent')->user();

        if (! $agent || $agent->role === 'ADMIN') {
            return null;
        }

        $officerRow = DB::table('cbe_node_officers')
            ->where('agent_id', $agent->agent_id)
            ->where('is_active', true)
            ->first();

        if (! $officerRow) {
            return null;
        }

        return DB::table('cbe_hierarchy_nodes')
            ->where('node_id', $officerRow->node_id)
            ->first();
    }

    // REBUILT 10 Sep 2026 (Task #409) — per Chris: "why you show
    // everything ... first thing you should ask is what cbe group to set
    // up ... second after set up cbe group you should ask me to configure
    // cbe group for the state or branch or temple." The old screen jumped
    // straight from "pick a group" to a combined form + full existing-
    // structure browse list, all at once. Now it's a genuine 3-step
    // Prev/Next flow: (1) pick CBE Group, (2) pick which Level you're
    // setting up (with a count of how many already exist at that level,
    // so it's clear what you're adding to), (3) a plain create form for
    // that level only — level is locked (no longer an open dropdown,
    // since it was already chosen in step 2), Parent is scoped to just
    // the level directly above (the 99% case) instead of every node in
    // the community. The full existing-structure list is dropped from
    // this screen entirely — Insert/Restructure (Task #399) is the
    // dedicated screen for browsing/rewiring the existing tree.
    public function create(Request $request)
    {
        // NEW 11 Sep 2026 (Task #413 follow-up) — an officer's own node
        // ALWAYS wins over whatever the request says, so a tampered
        // ?group= (or one left over from a stale link) can't put them
        // into a different CBE community than their own.
        $officerNode = $this->currentOfficerNode();
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';

        // An officer whose active cbe_node_officers row points at a node
        // that no longer exists (deleted/reparented data problem) gets no
        // access rather than a confusing empty screen.
        abort_if($isOfficer && ! $officerNode, 403, 'You do not have permission to access this area.');

        $groupId = $isOfficer ? $officerNode->group_label_id : $request->get('group');
        $levelId = $request->get('level');

        $groups = DB::table('group_labels')
            ->where('group_type', 'CBE')
            ->when($isOfficer, fn ($q) => $q->where('group_label_id', $groupId))
            ->orderBy('group_name')
            ->get(['group_label_id', 'group_name']);

        $group = null;
        $levels = collect();
        $level = null;
        $parentCandidates = collect();
        $isTopLevel = false;
        $topLevelExisting = collect();

        if ($groupId) {
            $group = DB::table('group_labels')
                ->where('group_label_id', $groupId)
                ->where('group_type', 'CBE')
                ->first();

            if ($group) {
                $levels = DB::table('cbe_hierarchy_levels')
                    ->where('group_label_id', $groupId)
                    ->orderBy('level_order')
                    ->get(['level_id', 'level_order', 'level_name']);

                $counts = DB::table('cbe_hierarchy_nodes')
                    ->where('group_label_id', $groupId)
                    ->selectRaw('level_id, count(*) as cnt')
                    ->groupBy('level_id')
                    ->pluck('cnt', 'level_id');

                $levels = $levels->map(function ($lv) use ($counts) {
                    $lv->node_count = (int) ($counts[$lv->level_id] ?? 0);
                    return $lv;
                });

                // NEW 11 Sep 2026 (Task #413 follow-up) — an officer
                // manages only their OWN node, so they may only add
                // entities BENEATH it (their node's level_order and
                // anything shallower — HQ/State above them — is off
                // limits). Admin still sees every level, unchanged.
                if ($isOfficer) {
                    $officerLevelOrder = $levels->firstWhere('level_id', $officerNode->level_id)?->level_order;
                    $levels = $levels->filter(
                        fn ($lv) => $officerLevelOrder !== null && $lv->level_order > $officerLevelOrder
                    )->values();
                }

                if ($levelId) {
                    $level = $levels->firstWhere('level_id', $levelId);
                }

                if ($level) {
                    $parentLevel = $levels->where('level_order', '<', $level->level_order)
                        ->sortByDesc('level_order')
                        ->first();

                    // For an officer, $parentLevel may be null even when
                    // $level itself is valid (e.g. picking the level
                    // directly below their own) — that's fine, it just
                    // means their own node is the only possible parent,
                    // handled by the hierarchy_path scoping below either
                    // way since every candidate query already filters to
                    // their subtree.
                    $parentCandidatesQuery = $parentLevel
                        ? DB::table('cbe_hierarchy_nodes')
                            ->where('group_label_id', $groupId)
                            ->where('level_id', $parentLevel->level_id)
                        : null;

                    if ($isOfficer && $parentCandidatesQuery) {
                        // Never let an officer parent a new entity under a
                        // node outside their own subtree — hierarchy_path
                        // is a materialized path, so "starts with my own
                        // node's path" is exactly "is my node or a
                        // descendant of it".
                        $parentCandidatesQuery->where('hierarchy_path', 'like', $officerNode->hierarchy_path.'%');
                    }

                    $parentCandidates = $parentCandidatesQuery
                        ? $parentCandidatesQuery->orderBy('node_name')
                            ->get(['node_id', 'node_name', 'coverage_postcode_start', 'coverage_postcode_end'])
                        : collect();

                    // An officer adding directly beneath their own node
                    // (no intermediate level) has exactly one valid
                    // parent — their own node — which the query above
                    // can't produce since it only looks at $parentLevel.
                    // Offer it explicitly so the form still has a parent
                    // to pick from.
                    if ($isOfficer && $parentCandidates->isEmpty() && $officerNode->level_id !== $level->level_id) {
                        $isDirectChildLevel = $levels->where('level_order', '<', $level->level_order)
                            ->where('level_order', '>', $officerLevelOrder ?? -1)
                            ->isEmpty();
                        if ($isDirectChildLevel) {
                            $parentCandidates = collect([(object) [
                                'node_id' => $officerNode->node_id,
                                'node_name' => $officerNode->node_name,
                                'coverage_postcode_start' => $officerNode->coverage_postcode_start,
                                'coverage_postcode_end' => $officerNode->coverage_postcode_end,
                            ]]);
                        }
                    }

                    // NEW 10 Sep 2026 (Task #410) — per Chris: "how many
                    // records i can create for HQ?" There's no hard limit
                    // at any level — CBE communities can genuinely need
                    // more than one root in rare cases — but a second
                    // HQ-level record is almost always a mistake, so warn
                    // (not block) when one already exists. (Not reachable
                    // for an officer — their $levels list already
                    // excludes their own level and everything above it.)
                    $isTopLevel = $level->level_order === $levels->min('level_order') && ! $isOfficer;
                    if ($isTopLevel) {
                        $topLevelExisting = DB::table('cbe_hierarchy_nodes')
                            ->where('group_label_id', $groupId)
                            ->where('level_id', $level->level_id)
                            ->orderBy('node_name')
                            ->get(['node_id', 'node_name']);
                    }
                }
            }
        }

        // CHANGED 26 Sep 2026 — per Chris: "THERE IS NO ADD JUST
        // SEARCH/EDIT AND DISPLAY ALL THE SEARCH BOX". Once the CBE group
        // is known, Entity Maintenance goes straight to Search / Edit
        // (all search boxes) — no Add / Search landing, no Add button.
        // Add is only reached from Group Name › Entities / Branches (mode=add).
        // CHANGED 27 Sep 2026 — per Chris: Entity Maintenance is the standard
        // Add | Search / View / Edit method again. The entity is the LAST
        // level, so Add opens the entity form at that level straight away.
        $bottomLevelId = $group
            ? DB::table('cbe_hierarchy_levels')->where('group_label_id', $group->group_label_id)->orderByDesc('level_order')->value('level_id')
            : null;
        if ($group && ! $levelId && $request->get('mode') === 'add' && $bottomLevelId) {
            return redirect()->route('admin.cbe-kpi.hierarchy-nodes.create', ['group' => $group->group_label_id, 'level' => $bottomLevelId,
                'return' => $request->get('return') ?: route('admin.cbe-kpi.hierarchy-nodes.create')]);
        }

        return view('admin.cbe-kpi.hierarchy-nodes.create', [
            'groups' => $groups,
            'group' => $group,
            'levels' => $levels,
            'level' => $level,
            'parentCandidates' => $parentCandidates,
            'isTopLevel' => $isTopLevel,
            'topLevelExisting' => $topLevelExisting,
            'isOfficer' => $isOfficer,
            // NEW 26 Sep 2026 — Add / Search-View-Edit method: 'add' once
            // Add New Entity is chosen; empty = the 2-button landing.
            'mode' => $level ? 'add' : ($request->get('mode') === 'add' ? 'add' : null),
            'node' => null,
            'parentPick' => $level ? $this->parentPickList() : collect(),
            'currentParent' => null,
            'nodePhones' => collect(),
            'coveragePicked' => null,
            'bottomLevelId' => $bottomLevelId,
        ]);
    }

    // NEW 26 Sep 2026 — per Chris: after keying Postcode Coverage From/To,
    // list every postcode inside that range (from malaysia_postcodes) so
    // the next screen can show Entity Name / Postcode / City / checkbox
    // (ticked by default). Read-only lookup, returns JSON only.
    public function coveragePostcodes(Request $request)
    {
        $start = preg_replace('/\D/', '', (string) $request->get('start'));
        $end = preg_replace('/\D/', '', (string) $request->get('end'));

        if ($start === '' || $end === '') {
            return response()->json([]);
        }

        $startNum = (int) $start;
        $endNum = (int) $end;
        if ($startNum > $endNum) {
            [$startNum, $endNum] = [$endNum, $startNum];
        }

        $rows = DB::table('malaysia_postcodes')
            ->whereRaw('CAST(postcode AS UNSIGNED) BETWEEN ? AND ?', [$startNum, $endNum])
            ->select('postcode', 'city')
            ->distinct()
            ->orderBy('postcode')
            ->orderBy('city')
            ->limit(3000)
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        // NEW 11 Sep 2026 (Task #413 follow-up) — same rule as create():
        // an officer's own node wins over anything the form submits.
        // This is the part that actually matters for security — create()
        // only controls what an honest officer sees; this is what stops
        // a tampered POST (edited group_label_id/level_id/parent_node_id)
        // from writing a node outside their own subtree.
        $officerNode = $this->currentOfficerNode();
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';

        abort_if($isOfficer && ! $officerNode, 403, 'You do not have permission to access this area.');

        $groupId = $isOfficer ? $officerNode->group_label_id : $request->get('group_label_id');

        $group = DB::table('group_labels')
            ->where('group_label_id', $groupId)
            ->where('group_type', 'CBE')
            ->first();

        abort_if(! $group, 404);

        $levelId = $request->get('level_id');
        $level = DB::table('cbe_hierarchy_levels')
            ->where('level_id', $levelId)
            ->where('group_label_id', $groupId)
            ->first();
        $levelOk = (bool) $level;

        // An officer may only add levels BENEATH their own node — mirrors
        // the filtering already applied to the level list in create().
        if ($isOfficer && $levelOk) {
            $officerLevelOrder = DB::table('cbe_hierarchy_levels')
                ->where('level_id', $officerNode->level_id)
                ->value('level_order');
            if ($officerLevelOrder === null || $level->level_order <= $officerLevelOrder) {
                $levelOk = false;
            }
        }

        $name = trim((string) $request->get('node_name'));
        $postcode = trim((string) $request->get('postcode')) ?: null;

        // CHANGED 27 Sep 2026 — per Chris: the upline is ALWAYS chosen by
        // the user in "Affiliated To (Parent)" (any CBE group). Blank =
        // stand-alone / HQ pending affiliation. No more automatic postcode
        // linking for entities set up here.
        $parentId = trim((string) $request->get('parent_node_id')) ?: null;
        $parent = $parentId ? DB::table('cbe_hierarchy_nodes')->where('node_id', $parentId)->first() : null;
        if ($parentId && ! $parent) {
            return back()->withErrors(['parent_node_id' => __('cbe_masterfile.err_parent_invalid')])->withInput();
        }
        $stayStandalone = true; // manual choice -> link_locked

        // An officer can never create a root (no parent) — every new
        // entity they add must sit somewhere inside their own node's
        // subtree (hierarchy_path prefix proves it).
        $parentOk = true;
        if ($isOfficer) {
            $parentOk = $parent && str_starts_with((string) $parent->hierarchy_path, (string) $officerNode->hierarchy_path);
            if (! $parentOk) {
                $parent = null;
                $parentId = null;
            }
        }

        if (! $levelOk || $name === '') {
            return back()
                ->withErrors([
                    'level_id' => ! $levelOk ? __('cbe_masterfile.err_level_required') : null,
                    'node_name' => $name === '' ? __('cbe_masterfile.err_name_required') : null,
                ])
                ->withInput();
        }

        $nodeId = (string) Str::uuid();
        $hierarchyPath = ($parent->hierarchy_path ?? '/').$nodeId.'/';

        DB::table('cbe_hierarchy_nodes')->insert([
            'node_id' => $nodeId,
            'node_code' => null,
            'group_label_id' => $groupId,
            'level_id' => $levelId,
            'parent_node_id' => $parent->node_id ?? null,
            'link_locked' => $stayStandalone,
            'node_name' => $name,
            'node_name_zh' => trim((string) $request->get('node_name_zh')) ?: null,
            'city' => trim((string) $request->get('city')) ?: null,
            'postcode' => $postcode,
            // NEW 11 Sep 2026 — per Chris: "branch can configure which
            // city it belong to range of post code" — optional coverage
            // range, distinct from this entity's own `postcode` above.
            'coverage_postcode_start' => trim((string) $request->get('coverage_postcode_start')) ?: null,
            'coverage_postcode_end' => trim((string) $request->get('coverage_postcode_end')) ?: null,
            'address' => trim((string) $request->get('address')) ?: null,
            'contact_person_1' => trim((string) $request->get('contact_person_1')) ?: null,
            'contact_person_2' => trim((string) $request->get('contact_person_2')) ?: null,
            'external_reference_no' => trim((string) $request->get('external_reference_no')) ?: null,
            'hierarchy_path' => $hierarchyPath,
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Optional initial phone numbers, same shape as the existing
        // Profile-tab phone management (cbe_hierarchy_node_phones), so
        // this new node can start with numbers already on record instead
        // of forcing a second trip to the Profile tab right away.
        $phoneNumbers = (array) $request->get('phone_number', []);
        $phoneNotes = (array) $request->get('phone_note', []);
        $order = 0;
        foreach ($phoneNumbers as $i => $num) {
            $num = trim((string) $num);
            if ($num === '') {
                continue;
            }
            DB::table('cbe_hierarchy_node_phones')->insert([
                'phone_id' => (string) Str::uuid(),
                'node_id' => $nodeId,
                'phone_number' => $num,
                'contact_note' => trim((string) ($phoneNotes[$i] ?? '')) ?: null,
                'display_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // NEW 26 Sep 2026 — save only the postcodes left TICKED on the
        // coverage selection screen. Each value is "postcode|city". Only
        // postcodes inside this entity's own From/To range are accepted.
        $covStart = (int) preg_replace('/\D/', '', (string) $request->get('coverage_postcode_start'));
        $covEnd = (int) preg_replace('/\D/', '', (string) $request->get('coverage_postcode_end'));
        if ($covStart > $covEnd) {
            [$covStart, $covEnd] = [$covEnd, $covStart];
        }
        $seenCov = [];
        foreach ((array) $request->get('coverage_postcodes', []) as $item) {
            [$pc, $pcCity] = array_pad(explode('|', (string) $item, 2), 2, '');
            $pc = preg_replace('/\D/', '', $pc);
            if ($pc === '' || strlen($pc) > 5) {
                continue;
            }
            $pcNum = (int) $pc;
            if ($pcNum < $covStart || $pcNum > $covEnd) {
                continue;
            }
            $pc = str_pad($pc, 5, '0', STR_PAD_LEFT);
            $pcCity = mb_substr(trim($pcCity), 0, 100);
            $key = $pc.'|'.$pcCity;
            if (isset($seenCov[$key])) {
                continue;
            }
            $seenCov[$key] = true;
            DB::table('cbe_node_coverage_postcodes')->insert([
                'coverage_id' => (string) Str::uuid(),
                'node_id' => $nodeId,
                'postcode' => $pc,
                'city' => $pcCity !== '' ? $pcCity : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // REMOVED 27 Sep 2026 — per Chris the upline is always chosen by the
        // user, so no automatic postcode relinking of other entities here.
        // (was:) NEW 12 Sep 2026 — this new node might be the missing parent an
        // earlier, still-unlinked entity (e.g. Klang Branch created
        // before Selangor State existed) has been waiting for. Re-check
        // every currently-unlinked node in this same CBE community right
        // now, so linking happens the moment a qualifying parent shows
        // up — no matter what order things were created in, and with no
        // manual "restructure" step needed.
        // \App\Services\CbeHierarchyLinkService::relinkOrphans($groupId);

        // NEW 27 Sep 2026 — per Chris: upline rule (Branch -> real State of
        // its postcode, else HQ; re-pointed when a State appears / goes).
        \App\Services\CbeTierService::afterSave($nodeId);

        // CHANGED 10 Sep 2026 (Task #409) — keep the level selected on
        // redirect so adding several entities at the same level (e.g.
        // several Temples in a row) doesn't force re-picking the level
        // every single time.
        $ret = (string) $request->get('return');
        if ($ret !== '' && str_starts_with($ret, url('/'))) {
            return redirect()->to($ret)->with('cbe_node_saved', $name);
        }

        return redirect()
            ->route('admin.cbe-kpi.hierarchy-nodes.create', ['group' => $groupId, 'level' => $levelId])
            ->with('cbe_node_saved', $name);
    }

    // NEW 26 Sep 2026 — per Chris: Entity Maintenance must follow the
    // standard Add / Search-View-Edit method. This is the Search / View /
    // Edit list for one CBE group (officer: own subtree only).
    // REBUILT 27 Sep 2026 — per Chris: Entity Maintenance › Search / View /
    // Edit covers ALL CBEs, so the search boxes start with the CBE Group:
    // CBE Group → HQ → State → Branch → City → Postcode From–To → Entity
    // Name → GO. HQ / State / Branch list only what exists for the chosen
    // CBE (taken from the real records — a box with nothing in it is not
    // shown) and each narrows the next. Nothing is listed until GO. Rows =
    // that CBE's entities (own + affiliated), last level only, in postcode
    // order.
    public function index(Request $request)
    {
        $officerNode = $this->currentOfficerNode();
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        abort_if($isOfficer && ! $officerNode, 403, 'You do not have permission to access this area.');

        $groups = DB::table('group_labels')->where('group_type', 'CBE')
            ->when($isOfficer, fn ($q) => $q->where('group_label_id', $officerNode->group_label_id))
            ->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupId = $isOfficer ? $officerNode->group_label_id : (string) $request->get('group');
        $group = $groupId ? $groups->firstWhere('group_label_id', $groupId) : null;

        $ids = [];
        $tierOptions = ['hq' => [], 'state' => [], 'branch' => []];
        if ($group) {
            // candidates: entity-tier nodes among this CBE's own + affiliated
            // entities, plus its top entities when they are entity-tier
            // (e.g. a Rotary club that is the CBE's only level).
            $kinds = \App\Services\CbeTierService::groupEntityKinds($group->group_label_id);
            $topIds = DB::table('cbe_hierarchy_nodes as n')->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->where('n.group_label_id', $group->group_label_id)
                ->whereRaw('l.level_order = (select min(l2.level_order) from cbe_hierarchy_levels l2 where l2.group_label_id = n.group_label_id)')
                ->pluck('n.node_id')->all();
            $candidateIds = array_values(array_unique(array_merge(array_keys($kinds), $topIds)));
            $upIds = [];
            foreach (array_chunk($candidateIds, 1000) as $chunk) {
                foreach (DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $chunk)->get(['node_id', 'level_id', 'hierarchy_path', 'affiliated_node_id']) as $c) {
                    if (\App\Services\CbeTierService::tierOfNode($c) !== 'entity') {
                        continue;
                    }
                    if ($isOfficer && ! str_starts_with((string) $c->hierarchy_path, (string) $officerNode->hierarchy_path)
                        && ! ($c->affiliated_node_id && str_starts_with((string) DB::table('cbe_hierarchy_nodes')->where('node_id', $c->affiliated_node_id)->value('hierarchy_path'), (string) $officerNode->hierarchy_path))) {
                        continue;
                    }
                    $ids[] = $c->node_id;
                    foreach (array_filter(explode('/', (string) $c->hierarchy_path)) as $a) {
                        $upIds[$a] = true;
                    }
                    if ($c->affiliated_node_id) {
                        $upIds[$c->affiliated_node_id] = true;
                    }
                }
            }
            // HQ / State / Branch options from the candidates' real uplines
            // and the branches they are affiliated to.
            $upIds = array_keys($upIds);
            $paths = [];
            foreach (array_chunk($upIds, 1000) as $chunk) {
                foreach (DB::table('cbe_hierarchy_nodes')->whereIn('node_id', $chunk)->get(['node_id', 'level_id', 'node_name', 'node_code', 'hierarchy_path']) as $u) {
                    $t = \App\Services\CbeTierService::tierOfNode($u);
                    if (isset($tierOptions[$t])) {
                        $tierOptions[$t][$u->node_id] = ['v' => $u->node_name, 'l' => (string) $u->node_code, 'id' => $u->node_id, 'path' => (string) $u->hierarchy_path];
                    }
                }
            }
            foreach ($tierOptions as $t => $list) {
                $tierOptions[$t] = collect($list)->sortBy('v')->values()->all();
            }
        }

        $scope = fn () => DB::table('cbe_hierarchy_nodes as n')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->whereIn('n.node_id', $ids ?: ['#none#']);

        $searched = $group && $request->has('go');
        $nodes = null;
        if ($searched) {
            $query = $this->applyEntityFilters($request, $scope());
            // HQ / State / Branch picks (by id): under it, or affiliated to it
            // (or to anything under it).
            foreach (['hq_id', 'state_id', 'branch_id'] as $f) {
                $pick = (string) $request->get($f);
                if ($pick === '') {
                    continue;
                }
                $under = DB::table('cbe_hierarchy_nodes')->where('hierarchy_path', 'like', '%/'.$pick.'/%')->pluck('node_id')->push($pick)->all();
                $query->where(function ($w) use ($pick, $under) {
                    $w->where('n.hierarchy_path', 'like', '%/'.$pick.'/%')->orWhereIn('n.affiliated_node_id', $under);
                });
            }
            $query->orderByRaw("COALESCE(NULLIF(n.postcode, ''), '99999')")
                ->orderBy('n.node_name')
                ->select('n.node_id', 'n.level_id', 'n.node_code', 'n.node_name', 'n.node_name_zh', 'n.city', 'n.postcode', 'l.level_name',
                    'n.contact_person_1', 'n.contact_person', 'n.contact_phone',
                    DB::raw('(select p.phone_number from cbe_hierarchy_node_phones p where p.node_id = n.node_id order by p.display_order limit 1) as first_phone'));
            $perPageIn = (int) $request->get('per_page');
            $perPage = $perPageIn > 0 ? max(3, min(50, $perPageIn)) : 10;
            $nodes = $query->paginate($perPage)->withQueryString();
        }

        return view('admin.cbe-kpi.hierarchy-nodes.index', [
            'group' => $group,
            'groups' => $groups,
            'nodes' => $nodes,
            'searched' => $searched,
            'tierOptions' => $tierOptions,
            'cityList' => collect(),
            'nameList' => $group ? $this->pickName($scope()) : collect(),
            'postcodeList' => $this->pickPostcode(),
            'codeList' => collect(),
            'isOfficer' => $isOfficer,
        ]);
    }

    // CHANGED 27 Sep 2026 — per Chris: Group Name › (CBE) › "Affiliate Group"
    // tab. No search boxes: the list appears at once. Entities are found
    // automatically (CbeTierService::autoAffiliate — the HQ's entities in
    // this CBE's district) and saved automatically; every tick / untick
    // AUTO-SAVES (toggleGroupEntity). Compact 2-column rows, no scroll,
    // Prev / "Showing a–b of N records" / Next at the bottom.
    public function groupEntities(Request $request)
    {
        $group = DB::table('group_labels')->where('group_label_id', (string) $request->get('group'))->where('group_type', 'CBE')->first();
        abort_if(! $group, 404);
        abort_if(Auth::guard('agent')->user()?->role !== 'ADMIN', 403);

        $newIds = array_flip(array_merge(
            \App\Services\CbeTierService::autoAffiliate($group->group_label_id),
            (array) session()->pull('ag_new_ids.'.$group->group_label_id, [])
        ));
        $kinds = \App\Services\CbeTierService::groupEntityKinds($group->group_label_id);
        $total = count($kinds);

        // District candidates not (or no longer) affiliated here: released
        // ones (untick-able back) and ones affiliated to another branch (grey).
        $extra = [];
        $anchorName = [];
        $hasEx = \Illuminate\Support\Facades\Schema::hasTable('cbe_affiliation_exclusions');
        foreach (\App\Services\CbeTierService::anchors($group->group_label_id) as [$anchor, $hq]) {
            $anchorName[$anchor->node_id] = $anchor->node_name;
            $q = \App\Services\CbeTierService::districtCandidates($anchor, $hq);
            if (! $q) {
                continue;
            }
            foreach ((clone $q)->get(['n.node_id', 'n.affiliated_node_id']) as $c) {
                if (isset($kinds[$c->node_id])) {
                    continue;
                }
                $extra[$c->node_id] = ['kind' => $c->affiliated_node_id ? 'elsewhere' : 'released', 'anchor' => $anchor->node_id];
            }
        }

        $ids = array_merge(array_keys($kinds), array_keys($extra));
        $rows = collect();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $rows = $rows->concat(DB::table('cbe_hierarchy_nodes as n')
                ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->leftJoin('cbe_hierarchy_nodes as p', 'p.node_id', '=', 'n.parent_node_id')
                ->leftJoin('cbe_hierarchy_nodes as a', 'a.node_id', '=', 'n.affiliated_node_id')
                ->whereIn('n.node_id', $chunk)
                ->get(['n.node_id', 'n.node_code', 'n.node_name', 'n.node_name_zh', 'n.postcode', 'n.city', 'n.parent_node_id', 'n.affiliated_node_id', 'l.level_name', 'l.level_order', 'p.node_name as parent_name', 'a.node_name as aff_name']));
        }
        $rows = $rows->map(function ($r) use ($kinds, $extra, $newIds) {
            $r->kind = $kinds[$r->node_id] ?? $extra[$r->node_id]['kind'];
            $r->anchor = $r->kind === 'aff' ? $r->affiliated_node_id : ($r->kind === 'up' ? $r->parent_node_id : ($extra[$r->node_id]['anchor'] ?? null));
            $r->is_new = isset($newIds[$r->node_id]);
            return $r;
        })->sortBy(fn ($r) => [$r->kind === 'elsewhere' ? 1 : 0, (string) ($r->postcode ?: '99999'), mb_strtolower($r->node_name)])->values(); // CHANGED 27 Sep 2026 — per Chris: postcode order

        return view('admin.cbe-kpi.group-entities.index', [
            'group' => $group,
            'total' => $total,
            'rows' => $rows,
            'newCount' => count($newIds),
            'backUrl' => route('admin.masterfile.group-names.edit', $group->group_label_id),
        ]);
    }

    // Auto-save one tick / untick. JSON {ok, total}.
    public function toggleGroupEntity(Request $request)
    {
        $group = DB::table('group_labels')->where('group_label_id', (string) $request->get('group'))->where('group_type', 'CBE')->first();
        abort_if(! $group, 404);
        abort_if(Auth::guard('agent')->user()?->role !== 'ADMIN', 403);

        $id = (string) $request->get('node');
        $anchorId = (string) $request->get('anchor');
        $on = (bool) $request->boolean('on');
        $kind = (string) $request->get('kind');
        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->first();
        $anchor = DB::table('cbe_hierarchy_nodes')->where('node_id', $anchorId)->first();
        abort_if(! $node || ! $anchor, 404);

        // The anchor must be one of this CBE's own entities or linked under it.
        $familyOk = $anchor->group_label_id === $group->group_label_id
            || collect(\App\Services\CbeTierService::anchors($group->group_label_id))->contains(fn ($a) => $a[0]->node_id === $anchorId)
            || DB::table('cbe_hierarchy_nodes')->where('group_label_id', $group->group_label_id)->whereRaw('? LIKE CONCAT(hierarchy_path, \'%\')', [(string) $anchor->hierarchy_path])->exists();
        abort_if(! $familyOk, 403);

        if ($kind === 'up') {
            \App\Services\CbeHierarchyLinkService::setParent($id, $on ? $anchorId : null);
        } else {
            if ($on) {
                if ($node->affiliated_node_id && $node->affiliated_node_id !== $anchorId) {
                    return response()->json(['ok' => false, 'message' => __('cbe_masterfile.hier_link_already', ['name' => DB::table('cbe_hierarchy_nodes')->where('node_id', $node->affiliated_node_id)->value('node_name')])], 409);
                }
                DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->update(['affiliated_node_id' => $anchorId, 'updated_at' => now()]);
                if (\Illuminate\Support\Facades\Schema::hasTable('cbe_affiliation_exclusions')) {
                    DB::table('cbe_affiliation_exclusions')->where('node_id', $id)->where('anchor_node_id', $anchorId)->delete();
                }
            } else {
                DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->where('affiliated_node_id', $anchorId)->update(['affiliated_node_id' => null, 'updated_at' => now()]);
                if (\Illuminate\Support\Facades\Schema::hasTable('cbe_affiliation_exclusions')) {
                    DB::table('cbe_affiliation_exclusions')->updateOrInsert(['node_id' => $id, 'anchor_node_id' => $anchorId], ['updated_at' => now(), 'created_at' => now()]);
                }
            }
        }
        \App\Services\CbeTierService::afterSave($anchorId);

        return response()->json(['ok' => true, 'total' => count(\App\Services\CbeTierService::groupEntityKinds($group->group_label_id))]);
    }

    // NEW 26 Sep 2026 — View / Edit one entity, same form as Add (incl.
    // the postcode coverage tick-box selection screen).
    public function edit(Request $request, string $node)
    {
        $row = $this->editableNode($node);

        $group = DB::table('group_labels')->where('group_label_id', $row->group_label_id)->first();
        $level = DB::table('cbe_hierarchy_levels')->where('level_id', $row->level_id)->first(['level_id', 'level_order', 'level_name']);
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';

        $picked = \Illuminate\Support\Facades\Schema::hasTable('cbe_node_coverage_postcodes')
            ? DB::table('cbe_node_coverage_postcodes')->where('node_id', $row->node_id)->orderBy('postcode')->get(['postcode', 'city'])
                ->map(fn ($r) => $r->postcode.'|'.($r->city ?? ''))->values()->all()
            : [];

        return view('admin.cbe-kpi.hierarchy-nodes.create', [
            'groups' => collect(),
            'group' => $group,
            'levels' => collect([$level]),
            'level' => $level,
            'parentCandidates' => collect(),
            'isTopLevel' => false,
            'topLevelExisting' => collect(),
            'isOfficer' => $isOfficer,
            'mode' => 'edit',
            'node' => $row,
            'parentPick' => $this->parentPickList($row),
            'currentParent' => $row->parent_node_id ? DB::table('cbe_hierarchy_nodes as n')
                ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
                ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->where('n.node_id', $row->parent_node_id)
                ->first(['n.node_id', 'n.node_name', 'l.level_name', 'g.group_name']) : null,
            'nodePhones' => DB::table('cbe_hierarchy_node_phones')->where('node_id', $row->node_id)->orderBy('display_order')->get(),
            'coveragePicked' => count($picked) ? $picked : null,
        ]);
    }

    public function update(Request $request, string $node)
    {
        $row = $this->editableNode($node);
        $groupId = $row->group_label_id;

        $name = trim((string) $request->get('node_name'));
        if ($name === '') {
            return back()->withErrors(['node_name' => __('cbe_masterfile.err_name_required')])->withInput();
        }

        $postcode = trim((string) $request->get('postcode')) ?: null;

        // CHANGED 27 Sep 2026 — per Chris: upline = "Affiliated To (Parent)"
        // chosen by the user (any CBE group); blank = stand-alone / HQ.
        $newParentId = trim((string) $request->get('parent_node_id')) ?: null;
        if ($newParentId) {
            $p = DB::table('cbe_hierarchy_nodes')->where('node_id', $newParentId)->first();
            if (! $p || $p->node_id === $row->node_id || str_starts_with((string) $p->hierarchy_path, (string) $row->hierarchy_path)) {
                return back()->withErrors(['parent_node_id' => __('cbe_masterfile.err_parent_invalid')])->withInput();
            }
            $agentU = Auth::guard('agent')->user();
            if ($agentU && $agentU->role !== 'ADMIN') {
                $off = $this->currentOfficerNode();
                if (! $off || ! str_starts_with((string) $p->hierarchy_path, (string) $off->hierarchy_path)) {
                    return back()->withErrors(['parent_node_id' => __('cbe_masterfile.err_parent_invalid')])->withInput();
                }
            }
        }

        DB::transaction(function () use ($request, $row, $groupId, $name, $postcode, $newParentId) {
            DB::table('cbe_hierarchy_nodes')->where('node_id', $row->node_id)->update([
                'node_name' => $name,
                'node_name_zh' => trim((string) $request->get('node_name_zh')) ?: null,
                'city' => trim((string) $request->get('city')) ?: null,
                'postcode' => $postcode,
                'address' => trim((string) $request->get('address')) ?: null,
                'contact_person_1' => trim((string) $request->get('contact_person_1')) ?: null,
                'contact_person_2' => trim((string) $request->get('contact_person_2')) ?: null,
                'external_reference_no' => trim((string) $request->get('external_reference_no')) ?: null,
                'updated_at' => now(),
            ]);

            if ($newParentId !== $row->parent_node_id || ! $row->link_locked) {
                \App\Services\CbeHierarchyLinkService::setParent($row->node_id, $newParentId);
            }

            DB::table('cbe_hierarchy_node_phones')->where('node_id', $row->node_id)->delete();
            $this->savePhones($request, $row->node_id);

        });

        // NEW 27 Sep 2026 — upline rule (see CbeTierService).
        \App\Services\CbeTierService::afterSave($row->node_id);

        $ret = (string) $request->get('return');
        if ($ret !== '' && str_starts_with($ret, url('/'))) {
            return redirect()->to($ret)->with('cbe_node_saved', $name);
        }

        return redirect()
            ->route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $groupId])
            ->with('cbe_node_saved', $name);
    }

    // NEW 26 Sep 2026 — per Chris: a SEPARATE program from Entity
    // Maintenance ("ENTITY MAINTENANCE IS ADD OR SEARCH AND EDIT
    // INDIVIDUAL ENTITY, THIS PROGRAM WILL HAVE THE LINK TO WHICH CBE?
    // (PARENT ID)"). Screen: pick the Parent (City/Branch/State/HQ,
    // type-ahead pick list) -> search boxes City / Postcode From-To /
    // Entity Code From-To / Entity Name -> GO -> every result row TICKED
    // by default -> untick row by row -> Save links the ticked rows under
    // the Parent. Result rows = records at the level directly below the
    // Parent, plus still-unlinked records at any lower level, plus rows
    // already under this Parent.
    public function hierarchyLink(Request $request)
    {
        $officerNode = $this->currentOfficerNode();
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        abort_if($isOfficer && ! $officerNode, 403, 'You do not have permission to access this area.');

        $groups = DB::table('group_labels')->where('group_type', 'CBE')
            ->when($isOfficer, fn ($q) => $q->where('group_label_id', $officerNode->group_label_id))
            ->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupId = $isOfficer ? $officerNode->group_label_id : ($request->get('group') ?: ($groups->count() === 1 ? $groups->first()->group_label_id : null));
        $group = $groupId ? $groups->firstWhere('group_label_id', $groupId) : null;

        $parents = collect();
        $parent = null;
        $parentLevel = null;
        $rows = collect();
        $districtList = collect();
        $cityList = collect();
        $nameList = collect();
        $postcodeList = collect();
        $codeList = collect();
        $searched = false;
        $filterLevels = collect();
        $levelLists = [];
        $standalone = false;

        if ($group) {
            $levels = DB::table('cbe_hierarchy_levels')->where('group_label_id', $groupId)->orderBy('level_order')->get(['level_id', 'level_order', 'level_name']);
            $bottomOrder = $levels->max('level_order');
            $parentLevelIds = $levels->where('level_order', '<', $bottomOrder)->pluck('level_id');

            // CHANGED 27 Sep 2026 — any entity of this CBE can be the
            // parent (e.g. Persekutuan … has only a Branch level, and its
            // Klang Branch selects temples of its HQ CBE).
            $parents = DB::table('cbe_hierarchy_nodes as n')
                ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                ->where('n.group_label_id', $groupId)
                ->when($isOfficer, fn ($q) => $q->where('n.hierarchy_path', 'like', $officerNode->hierarchy_path.'%'))
                ->orderBy('l.level_order')->orderBy('n.node_name')
                ->get(['n.node_id', 'n.node_name', 'n.node_code', 'l.level_name']);

            $parentId = (string) $request->get('parent_id');
            if ($parentId !== '' && $parents->firstWhere('node_id', $parentId)) {
                $parent = $this->editableNode($parentId);
                [, $parentLevel, $lowerLevels] = $this->linkLevels($parent);

                // NEW 26 Sep 2026 — per Chris: extra pick-list boxes by
                // level — State parent: Branch; HQ parent: State + Branch.
                // i.e. every level between the parent and the City/entity
                // levels (City has its own City box).
                $filterLevels = $this->hierarchyFilterLevels($lowerLevels);
                foreach ($filterLevels as $fl) {
                    $levelLists[$fl->level_id] = $this->pickName(DB::table('cbe_hierarchy_nodes as n')
                        ->where('n.group_label_id', $groupId)
                        ->where('n.level_id', $fl->level_id)
                        ->when($isOfficer, fn ($q) => $q->where('n.hierarchy_path', 'like', $officerNode->hierarchy_path.'%')));
                }

                $scope = $lowerLevels->isNotEmpty() ? $this->linkCandidateScope($parent, $lowerLevels) : null;
                // NEW 27 Sep 2026 — entities of the HQ CBE this parent is
                // affiliated under (e.g. the 591 temples of 马来西亚道教总会).
                $famScope = $this->familyScope($parent);
                $standalone = ! $scope && ! $famScope;

                $nameList = collect();
                $codeList = collect();
                foreach (array_filter([$scope, $famScope]) as $sc) {
                    $nameList = $nameList->concat($this->pickName(clone $sc));
                    $codeList = $codeList->concat($this->pickCode(clone $sc));
                }
                $nameList = $nameList->unique('v')->values();
                $codeList = $codeList->unique('v')->values();
                $cityList = $this->pickCity();
                $postcodeList = $this->pickPostcode();
                $districtList = $this->pickDistrict();

                $searched = $request->has('go') && ! $standalone;
                if ($searched) {
                    $cols = ['n.node_id', 'n.node_code', 'n.node_name', 'n.node_name_zh', 'n.postcode', 'n.city', 'n.parent_node_id', 'n.affiliated_node_id', 'l.level_name', 'p.node_name as parent_name'];
                    if ($scope) {
                        $rows = $rows->concat($this->applyLevelFilters($request, $this->applyEntityFilters($request, clone $scope), $groupId, $filterLevels)
                            ->orderBy('l.level_order')->orderBy('n.node_name')->limit(3000)->get($cols)
                            ->map(function ($r) { $r->kind = 'own'; $r->aff_name = null; return $r; }));
                    }
                    if ($famScope) {
                        $rows = $rows->concat($this->applyEntityFilters($request, clone $famScope)
                            ->leftJoin('cbe_hierarchy_nodes as a', 'a.node_id', '=', 'n.affiliated_node_id')
                            ->orderBy('n.node_name')->limit(3000)->get(array_merge($cols, ['a.node_name as aff_name']))
                            ->map(function ($r) { $r->kind = 'fam'; return $r; }));
                    }
                }
            }
        }

        return view('admin.cbe-kpi.hierarchy-link.index', [
            'groups' => $groups,
            'group' => $group,
            'parents' => $parents,
            'parent' => $parent,
            'parentLevel' => $parentLevel,
            'rows' => $rows,
            'searched' => $searched,
            'cityList' => $cityList,
            'nameList' => $nameList,
            'postcodeList' => $postcodeList,
            'codeList' => $codeList,
            // NEW 27 Sep 2026 — per Chris: District box (whole daerah at once).
            'districtList' => $districtList,
            'showDistrictBox' => true,
            'filterLevels' => $filterLevels,
            'levelLists' => $levelLists,
            'standalone' => $standalone,
            // Plain City box only if the parent is not itself a City and no
            // City-level box is already shown below it.
            'showCityBox' => ! $parentLevel || (! self::isCityLevelName($parentLevel->level_name)
                && ! $filterLevels->contains(fn ($fl) => self::isCityLevelName($fl->level_name))),
            'isOfficer' => $isOfficer,
        ]);
    }

    // CHANGED 26 Sep 2026 — per Chris: "YOU KNOW THE HIERARCHY BELOW ...
    // NO NEED TO SEARCH ITSELF LEVEL". One pick-list box per level BELOW
    // the parent (never the parent's own level, never above it), excluding
    // only the bottom (entity) level, which is what the rows themselves
    // are. The City level therefore gets its own box only when it sits
    // below the parent.
    private function hierarchyFilterLevels($lowerLevels)
    {
        $bottomOrder = $lowerLevels->max('level_order');

        return $lowerLevels->filter(fn ($lv) => $lv->level_order < $bottomOrder)->values();
    }

    private static function isCityLevelName(?string $name): bool
    {
        return (bool) preg_match('/\b(city|bandar)\b|城市/iu', (string) $name);
    }

    // Each level box (e.g. State / Branch): keep rows that ARE a matching
    // node of that level or sit anywhere beneath one.
    private function applyLevelFilters(Request $request, $q, string $groupId, $filterLevels)
    {
        foreach ($filterLevels as $fl) {
            $text = trim((string) $request->input('lv.'.$fl->level_id));
            if ($text === '') {
                continue;
            }
            $ids = DB::table('cbe_hierarchy_nodes')
                ->where('group_label_id', $groupId)
                ->where('level_id', $fl->level_id)
                ->where('node_name', 'like', '%'.$text.'%')
                ->pluck('node_id');
            $q->where(function ($w) use ($ids) {
                $w->whereRaw('1 = 0');
                foreach ($ids as $id) {
                    $w->orWhere('n.node_id', $id)->orWhere('n.hierarchy_path', 'like', '%/'.$id.'/%');
                }
            });
        }

        return $q;
    }

    public function storeHierarchyLink(Request $request)
    {
        $parent = $this->editableNode((string) $request->get('parent_id'));
        [$levels, $parentLevel] = $this->linkLevels($parent);
        $levelOrder = $levels->pluck('level_order', 'level_id');
        $groupId = $parent->group_label_id;

        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        $officerNode = $isOfficer ? $this->currentOfficerNode() : null;

        $shown = array_unique(array_map('strval', (array) $request->get('shown_ids', [])));
        $ticked = array_flip(array_map('strval', (array) $request->get('linked_ids', [])));
        $linked = 0;
        $unlinked = 0;

        // NEW 27 Sep 2026 — HQ-family entities (e.g. temples): affiliated to
        // ONE branch only. Ticked -> affiliated here (only if not already
        // affiliated elsewhere); unticked and affiliated here -> released.
        $famShown = array_unique(array_map('strval', (array) $request->get('aff_shown', [])));
        $famTicked = array_flip(array_map('strval', (array) $request->get('aff_ticked', [])));
        $famScope = $this->familyScope($parent);
        if ($famScope && $famShown) {
            $allowed = array_flip((clone $famScope)->whereIn('n.node_id', $famShown)->pluck('n.node_id')->all());
            DB::transaction(function () use ($famShown, $famTicked, $allowed, $parent, &$linked, &$unlinked) {
                foreach ($famShown as $id) {
                    if (! isset($allowed[$id])) {
                        continue;
                    }
                    $cur = DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->value('affiliated_node_id');
                    if (isset($famTicked[$id])) {
                        if ($cur === null) {
                            DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->update(['affiliated_node_id' => $parent->node_id, 'updated_at' => now()]);
                            $linked++;
                        }
                    } elseif ($cur === $parent->node_id) {
                        DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->update(['affiliated_node_id' => null, 'updated_at' => now()]);
                        $unlinked++;
                    }
                }
            });
        }

        DB::transaction(function () use ($shown, $ticked, $parent, $parentLevel, $levelOrder, $groupId, $isOfficer, $officerNode, &$linked, &$unlinked) {
            foreach ($shown as $id) {
                $row = DB::table('cbe_hierarchy_nodes')->where('node_id', $id)->where('group_label_id', $groupId)->first();
                if (! $row || $row->node_id === $parent->node_id) {
                    continue;
                }
                // Only lower levels, never one of the parent's own ancestors (no loops).
                if (($levelOrder[$row->level_id] ?? -1) <= $parentLevel->level_order) {
                    continue;
                }
                if (str_starts_with((string) $parent->hierarchy_path, (string) $row->hierarchy_path)) {
                    continue;
                }
                if ($isOfficer && $row->parent_node_id && ! str_starts_with((string) $row->hierarchy_path, (string) $officerNode->hierarchy_path)) {
                    continue;
                }

                if (isset($ticked[$id])) {
                    if ($row->parent_node_id !== $parent->node_id) {
                        \App\Services\CbeHierarchyLinkService::reparent($groupId, $row->node_id, $parent->node_id);
                        $linked++;
                    }
                    DB::table('cbe_hierarchy_nodes')->where('node_id', $row->node_id)->update(['link_locked' => false, 'updated_at' => now()]);
                } elseif ($row->parent_node_id === $parent->node_id) {
                    // Unticked = do NOT link to this parent; detached and
                    // locked so automatic postcode linking won't re-attach it.
                    \App\Services\CbeHierarchyLinkService::makeStandalone($groupId, $row->node_id);
                    $unlinked++;
                }
            }
        });

        \App\Services\CbeTierService::afterSave($parent->node_id);

        return redirect()
            ->to(route('admin.cbe-kpi.hierarchy-link').'?'.(string) $request->get('return_query', ''))
            ->with('cbe_link_saved', __('cbe_masterfile.link_entities_saved', ['linked' => $linked, 'unlinked' => $unlinked]));
    }

    // NEW 27 Sep 2026 — the HQ family: the top-most upline of this parent
    // (first id in its hierarchy_path). If that HQ belongs to ANOTHER CBE
    // group, its bottom-level entities (e.g. Temples of 马来西亚道教总会)
    // can be affiliated to this parent. null = no HQ family.
    private function familyScope(object $parent)
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('cbe_hierarchy_nodes', 'affiliated_node_id')) {
            return null;
        }
        $ids = array_values(array_filter(explode('/', (string) $parent->hierarchy_path)));
        $rootId = $ids[0] ?? null;
        if (! $rootId || $rootId === $parent->node_id) {
            return null;
        }
        $root = DB::table('cbe_hierarchy_nodes')->where('node_id', $rootId)->first();
        if (! $root || $root->group_label_id === $parent->group_label_id) {
            return null;
        }
        $bottomLevelId = DB::table('cbe_hierarchy_levels')->where('group_label_id', $root->group_label_id)
            ->orderByDesc('level_order')->value('level_id');
        if (! $bottomLevelId) {
            return null;
        }

        return DB::table('cbe_hierarchy_nodes as n')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->leftJoin('cbe_hierarchy_nodes as p', 'p.node_id', '=', 'n.parent_node_id')
            ->where('n.group_label_id', $root->group_label_id)
            ->where('n.level_id', $bottomLevelId)
            ->where('n.hierarchy_path', 'like', '/'.$rootId.'/%');
    }

    // [all levels, parent's level, levels below it]
    private function linkLevels(object $parent): array
    {
        $levels = DB::table('cbe_hierarchy_levels')
            ->where('group_label_id', $parent->group_label_id)
            ->orderBy('level_order')
            ->get(['level_id', 'level_order', 'level_name']);
        $parentLevel = $levels->firstWhere('level_id', $parent->level_id);
        $parentOrder = $parentLevel->level_order ?? PHP_INT_MAX;
        $lowerLevels = $levels->where('level_order', '>', $parentOrder)->values();

        return [$levels, $parentLevel, $lowerLevels];
    }

    private function linkCandidateScope(object $parent, $lowerLevels)
    {
        $lowerIds = $lowerLevels->pluck('level_id');
        $nextLevelId = $lowerLevels->sortBy('level_order')->first()->level_id ?? null;

        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        $officerNode = $isOfficer ? $this->currentOfficerNode() : null;

        return DB::table('cbe_hierarchy_nodes as n')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->leftJoin('cbe_hierarchy_nodes as p', 'p.node_id', '=', 'n.parent_node_id')
            ->where('n.group_label_id', $parent->group_label_id)
            ->where('n.node_id', '!=', $parent->node_id)
            ->whereIn('n.level_id', $lowerIds)
            ->where(function ($w) use ($nextLevelId, $parent) {
                $w->where('n.level_id', $nextLevelId)
                    ->orWhereNull('n.parent_node_id')
                    ->orWhere('n.parent_node_id', $parent->node_id);
            })
            ->when($isOfficer && $officerNode, function ($w) use ($officerNode) {
                $w->where(function ($x) use ($officerNode) {
                    $x->where('n.hierarchy_path', 'like', $officerNode->hierarchy_path.'%')->orWhereNull('n.parent_node_id');
                });
            });
    }

    // Shared search boxes (Entity Maintenance + Hierarchy Link):
    // City, Postcode From/To, Entity Code From/To, Entity Name.
    private function applyEntityFilters(Request $request, $q)
    {
        // NEW 27 Sep 2026 — per Chris: District box. Matches every postcode
        // of the district, or its town name (Klang, Kapar, Pelabuhan Klang …).
        $district = trim((string) $request->get('district'));
        if ($district !== '' && $this->districtReady()) {
            [$dName, $dState] = array_pad(array_map('trim', explode(',', $district, 2)), 2, '');
            $ref = DB::table('postcode_localities')->where('district', $dName)
                ->when($dState !== '', fn ($w) => $w->where('state', $dState));
            $dPostcodes = (clone $ref)->distinct()->pluck('postcode')->all();
            $dTowns = (clone $ref)->distinct()->pluck('post_office')->all();
            $q->whereExists(function ($x) use ($dPostcodes, $dTowns) {
                $x->select(DB::raw(1))->from('cbe_hierarchy_nodes as d')
                    ->whereColumn('d.group_label_id', 'n.group_label_id')
                    ->whereRaw("d.hierarchy_path LIKE CONCAT(n.hierarchy_path, '%')")
                    ->where(function ($w) use ($dPostcodes, $dTowns) {
                        // FIXED 27 Sep 2026 — town name OR postcode (same as the
                        // CBE KPI Dashboard's Klang count: SGR-KLA-0001 says
                        // city Klang but has postcode 40000).
                        $w->whereIn('d.postcode', $dPostcodes ?: ['#none#'])
                            ->orWhereIn('d.city', $dTowns ?: ['#none#']);
                    });
            });
        }

        // City and Postcode match the row itself OR anything beneath it
        // (so a Branch/State row is found by the cities/postcodes of the
        // entities under it).
        $city = trim((string) $request->get('city'));
        if ($city !== '') {
            // Ticked rows from the City tick-box panel win; otherwise every
            // postcode the typed town/area covers.
            $picked = array_values(array_filter((array) $request->get('city_pc', []), fn ($v) => preg_match('/^\d{5}$/', (string) $v)));
            $townPostcodes = $request->has('city_pc') ? $picked : $this->postcodesForTown($city);
            if ($request->has('city_pc')) {
                $city = '#no-text-match#'; // ticked postcodes only
            }
            $q->whereExists(function ($x) use ($city, $townPostcodes) {
                $x->select(DB::raw(1))->from('cbe_hierarchy_nodes as d')
                    ->whereColumn('d.group_label_id', 'n.group_label_id')
                    ->whereRaw("d.hierarchy_path LIKE CONCAT(n.hierarchy_path, '%')")
                    ->where(function ($w) use ($city, $townPostcodes) {
                        $w->where('d.city', 'like', '%'.$city.'%');
                        if ($townPostcodes) {
                            $w->orWhereIn('d.postcode', $townPostcodes);
                        }
                    });
            });
        }

        $from = preg_replace('/\D/', '', (string) $request->get('pc_from'));
        $to = preg_replace('/\D/', '', (string) $request->get('pc_to'));
        if ($from !== '' || $to !== '') {
            $a = $from !== '' ? (int) $from : 0;
            $b = $to !== '' ? (int) $to : 99999;
            if ($a > $b) {
                [$a, $b] = [$b, $a];
            }
            $q->whereExists(function ($x) use ($a, $b) {
                $x->select(DB::raw(1))->from('cbe_hierarchy_nodes as d')
                    ->whereColumn('d.group_label_id', 'n.group_label_id')
                    ->whereRaw("d.hierarchy_path LIKE CONCAT(n.hierarchy_path, '%')")
                    ->whereNotNull('d.postcode')->where('d.postcode', '!=', '')
                    ->whereRaw('CAST(d.postcode AS UNSIGNED) BETWEEN ? AND ?', [$a, $b]);
            });
        }

        $codeFrom = trim((string) $request->get('code_from'));
        $codeTo = trim((string) $request->get('code_to'));
        if ($codeFrom !== '' && $codeTo !== '') {
            if (strcmp($codeFrom, $codeTo) > 0) {
                [$codeFrom, $codeTo] = [$codeTo, $codeFrom];
            }
            $q->whereBetween('n.node_code', [$codeFrom, $codeTo]);
        } elseif ($codeFrom !== '') {
            $q->where('n.node_code', '>=', $codeFrom);
        } elseif ($codeTo !== '') {
            $q->where('n.node_code', '<=', $codeTo);
        }

        $name = trim((string) $request->get('name'));
        if ($name !== '') {
            $like = '%'.$name.'%';
            $q->where(function ($w) use ($like) {
                $w->where('n.node_name', 'like', $like)->orWhere('n.node_name_zh', 'like', $like);
            });
        }

        return $q;
    }

    // NEW 26 Sep 2026 — per Chris: every pick list shows CODE and
    // DESCRIPTION together (e.g. Postcode "41050 · Meru", Branch
    // "code · name"). Each item: v = what goes into the box, l = the
    // description shown beside it in the drop-down.
    private function pickName($q)
    {
        return $q->orderBy('n.node_name')->limit(3000)->get(['n.node_name', 'n.node_code'])
            ->unique('node_name')->map(fn ($r) => ['v' => $r->node_name, 'l' => (string) $r->node_code])->values();
    }

    private function pickCode($q)
    {
        return $q->whereNotNull('n.node_code')->where('n.node_code', '!=', '')
            ->orderBy('n.node_code')->limit(3000)->get(['n.node_code', 'n.node_name'])
            ->unique('node_code')->map(fn ($r) => ['v' => $r->node_code, 'l' => $r->node_name])->values();
    }

    // CHANGED 27 Sep 2026 — MANDATORY GUIDELINE (per Chris): every CBE
    // search (Entity / City / Branch / State / HQ, any CBE type) uses the
    // national postcode reference `postcode_localities` — the full Pos
    // Malaysia list for ALL districts and states (area/location, post-
    // office town, postcode, state) — never only the entities keyed in.
    private function districtReady(): bool
    {
        static $ok = null;
        return $ok ??= $this->refReady()
            && \Illuminate\Support\Facades\Schema::hasColumn('postcode_localities', 'district')
            && DB::table('postcode_localities')->whereNotNull('district')->where('district', '!=', '')->exists();
    }

    // NEW 27 Sep 2026 — District pick list: "Klang, Selangor" with its
    // towns, e.g. "Kapar, Klang, Pelabuhan Klang, Pulau Indah, Pulau Ketam".
    private function pickDistrict()
    {
        if (! $this->districtReady()) {
            return collect();
        }

        return DB::table('postcode_localities')->whereNotNull('district')->where('district', '!=', '')
            ->selectRaw("district, state, GROUP_CONCAT(DISTINCT post_office ORDER BY post_office SEPARATOR ', ') as towns")
            ->groupBy('district', 'state')->orderBy('district')->orderBy('state')->get()
            ->map(fn ($r) => ['v' => $r->district.', '.$r->state, 'l' => $r->towns])->values();
    }

    private function refReady(): bool
    {
        static $ok = null;
        return $ok ??= \Illuminate\Support\Facades\Schema::hasTable('postcode_localities')
            && \Illuminate\Support\Facades\Schema::hasColumn('postcode_localities', 'area_postcode');
    }

    // Postcode pick list: "41050 · Klang — Bandar Bukit Raja, Klang Sentral …"
    private function pickPostcode($q = null)
    {
        if (! $this->refReady()) {
            return DB::table('malaysia_postcodes')->orderBy('postcode')->get(['postcode', 'city', 'state'])
                ->map(fn ($r) => ['v' => $r->postcode, 'l' => $r->city.', '.$r->state])->values();
        }

        return DB::table('postcode_localities')->where('area_postcode', true)
            ->selectRaw("postcode, MIN(post_office) as post_office, MIN(state) as state, SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT location ORDER BY location SEPARATOR ', '), ', ', 3) as sample")
            ->groupBy('postcode')->orderBy('postcode')->get()
            ->map(fn ($r) => ['v' => $r->postcode, 'l' => $r->post_office.' — '.$r->sample.' …'])->values();
    }

    // City pick list: every post-office town in Malaysia + its postcodes.
    // (Area names like Meru come from the live type-ahead, placeLookup.)
    private function pickCity($q = null)
    {
        $src = $this->refReady()
            ? DB::table('postcode_localities')->where('area_postcode', true)->selectRaw('post_office as town, GROUP_CONCAT(DISTINCT postcode ORDER BY postcode) as pcs, MIN(state) as state')->groupBy('post_office')
            : DB::table('malaysia_postcodes')->selectRaw('city as town, GROUP_CONCAT(DISTINCT postcode ORDER BY postcode) as pcs, MIN(state) as state')->groupBy('city');

        return $src->orderBy('town')->get()->map(function ($r) {
            $pcs = explode(',', (string) $r->pcs);
            $label = count($pcs) > 5 ? $pcs[0].' – '.end($pcs).' ('.count($pcs).')' : implode(', ', $pcs);
            return ['v' => (string) $r->town, 'l' => $label.' · '.$r->state];
        })->values();
    }

    // NEW 27 Sep 2026 — live type-ahead for the City box: post-office
    // towns AND area/location names (e.g. "Meru" -> Taman Meru …, with
    // postcode and town), nationwide. JSON, max 40 rows.
    public function placeLookup(Request $request)
    {
        // CHANGED 27 Sep 2026 — per Chris: City search shows ALL matching
        // towns + areas on one screen as tick boxes (default ticked). Each
        // row carries the postcode(s) it covers.
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 2 || ! $this->refReady()) {
            return response()->json([]);
        }
        $like = '%'.$q.'%';

        $towns = DB::table('postcode_localities')->where('area_postcode', true)
            ->where('post_office', 'like', $like)
            ->selectRaw('post_office as v, GROUP_CONCAT(DISTINCT postcode ORDER BY postcode) as pcs, MIN(state) as state')
            ->groupBy('post_office')->orderBy('post_office')->limit(30)->get()
            ->map(fn ($r) => ['v' => $r->v, 'pc' => $r->pcs, 'town' => '', 'state' => $r->state, 'pcs' => explode(',', (string) $r->pcs)]);

        $areas = DB::table('postcode_localities')->where('area_postcode', true)
            ->where('location', 'like', $like)
            ->orderBy('postcode')->orderBy('location')->limit(500)->get(['location', 'postcode', 'post_office', 'state'])
            ->map(fn ($r) => ['v' => $r->location, 'pc' => $r->postcode, 'town' => $r->post_office, 'state' => $r->state, 'pcs' => [$r->postcode]]);

        return response()->json($towns->concat($areas)->values());
    }

    // All postcodes a City / town / area name covers.
    private function postcodesForTown(string $town)
    {
        $like = '%'.$town.'%';
        $a = DB::table('malaysia_postcodes')->where('city', 'like', $like)->pluck('postcode');
        $b = $this->refReady()
            ? DB::table('postcode_localities')->where('area_postcode', true)
                ->where(fn ($w) => $w->where('post_office', 'like', $like)->orWhere('location', 'like', $like))
                ->distinct()->pluck('postcode')
            : collect();

        return $a->merge($b)->unique()->values()->all();
    }

    // NEW 27 Sep 2026 — live type-ahead for "Affiliated To (Parent)":
    // matches entity name / Chinese name / code / level / CBE group name,
    // across ALL CBE groups. Excludes the entity itself and its downlines.
    public function parentLookup(Request $request)
    {
        $q = trim((string) $request->get('q'));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }
        $node = $request->filled('node') ? DB::table('cbe_hierarchy_nodes')->where('node_id', $request->get('node'))->first() : null;
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        $off = $isOfficer ? $this->currentOfficerNode() : null;
        $like = '%'.$q.'%';

        $rows = DB::table('cbe_hierarchy_nodes as n')
            ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->when($node, fn ($w) => $w->where('n.hierarchy_path', 'not like', $node->hierarchy_path.'%'))
            ->when($isOfficer && $off, fn ($w) => $w->where('n.hierarchy_path', 'like', $off->hierarchy_path.'%'))
            ->where(function ($w) use ($like) {
                $w->where('n.node_name', 'like', $like)->orWhere('n.node_name_zh', 'like', $like)
                    ->orWhere('n.node_code', 'like', $like)->orWhere('g.group_name', 'like', $like)
                    ->orWhere('l.level_name', 'like', $like);
            })
            ->orderBy('l.level_order')->orderBy('g.group_name')->orderBy('n.node_name')
            ->limit(30)
            ->get(['n.node_id', 'n.node_code', 'n.node_name', 'l.level_name', 'g.group_name']);

        return response()->json($rows->map(fn ($r) => [
            'id' => $r->node_id,
            'v' => $r->node_name.' ('.($r->level_name ?: '—').' · '.$r->group_name.')',
            'code' => (string) $r->node_code,
            'level' => (string) $r->level_name,
            'group' => $r->group_name,
            'name' => $r->node_name,
        ])->values());
    }

    // NEW 27 Sep 2026 — "Affiliated To (Parent)" pick list: entities of
    // ALL CBE groups (code · name · level · CBE group), minus the node
    // itself and anything under it. Officer: own subtree only.
    private function parentPickList(?object $node = null)
    {
        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        $off = $isOfficer ? $this->currentOfficerNode() : null;

        return DB::table('cbe_hierarchy_nodes as n')
            ->join('group_labels as g', 'g.group_label_id', '=', 'n.group_label_id')
            ->leftJoin('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
            ->when($node, fn ($q) => $q->where('n.hierarchy_path', 'not like', $node->hierarchy_path.'%'))
            ->when($isOfficer && $off, fn ($q) => $q->where('n.hierarchy_path', 'like', $off->hierarchy_path.'%'))
            ->orderBy('g.group_name')->orderBy('l.level_order')->orderBy('n.node_name')
            ->limit(5000)
            ->get(['n.node_id', 'n.node_code', 'n.node_name', 'l.level_name', 'g.group_name'])
            ->map(fn ($r) => [
                'id' => $r->node_id,
                'v' => $r->node_name.' ('.($r->level_name ?: '—').' · '.$r->group_name.')',
                'l' => (string) $r->node_code,
            ])->values();
    }

    // Loads a node the current user may edit: Admin any node; a CBE node
    // officer only their own node or anything beneath it.
    private function editableNode(string $nodeId): object
    {
        $row = DB::table('cbe_hierarchy_nodes')->where('node_id', $nodeId)->first();
        abort_if(! $row, 404);

        $agent = Auth::guard('agent')->user();
        $isOfficer = $agent && $agent->role !== 'ADMIN';
        if ($isOfficer) {
            $officerNode = $this->currentOfficerNode();
            abort_if(! $officerNode || ! str_starts_with((string) $row->hierarchy_path, (string) $officerNode->hierarchy_path), 403, 'You do not have permission to access this area.');
        }

        return $row;
    }

    private function savePhones(Request $request, string $nodeId): void
    {
        $phoneNumbers = (array) $request->get('phone_number', []);
        $phoneNotes = (array) $request->get('phone_note', []);
        $order = 0;
        foreach ($phoneNumbers as $i => $num) {
            $num = trim((string) $num);
            if ($num === '') {
                continue;
            }
            DB::table('cbe_hierarchy_node_phones')->insert([
                'phone_id' => (string) Str::uuid(),
                'node_id' => $nodeId,
                'phone_number' => mb_substr($num, 0, 30),
                'contact_note' => mb_substr(trim((string) ($phoneNotes[$i] ?? '')), 0, 50) ?: null,
                'display_order' => $order++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // Saves only the postcodes left TICKED on the coverage selection
    // screen ("postcode|city"), and only ones inside the From/To range.
    private function saveCoveragePostcodes(Request $request, string $nodeId): void
    {
        $covStart = (int) preg_replace('/\D/', '', (string) $request->get('coverage_postcode_start'));
        $covEnd = (int) preg_replace('/\D/', '', (string) $request->get('coverage_postcode_end'));
        if ($covStart > $covEnd) {
            [$covStart, $covEnd] = [$covEnd, $covStart];
        }
        $seen = [];
        foreach ((array) $request->get('coverage_postcodes', []) as $item) {
            [$pc, $pcCity] = array_pad(explode('|', (string) $item, 2), 2, '');
            $pc = preg_replace('/\D/', '', $pc);
            if ($pc === '' || strlen($pc) > 5) {
                continue;
            }
            $pcNum = (int) $pc;
            if ($pcNum < $covStart || $pcNum > $covEnd) {
                continue;
            }
            $pc = str_pad($pc, 5, '0', STR_PAD_LEFT);
            $pcCity = mb_substr(trim($pcCity), 0, 100);
            $key = $pc.'|'.$pcCity;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            DB::table('cbe_node_coverage_postcodes')->insert([
                'coverage_id' => (string) Str::uuid(),
                'node_id' => $nodeId,
                'postcode' => $pc,
                'city' => $pcCity !== '' ? $pcCity : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // NEW 10 Sep 2026 (Task #399) — per Chris: different CBE communities
    // reach the same eventual shape (HQ -> State -> Branch -> Temple) in
    // a different real-world ORDER, decided by whoever formalizes first
    // — Tao did Klang Branch before Penang before Rawang before Kuantan;
    // a brand-new community like "Rotary Club Uptown" might start as a
    // single standalone Entity and only grow HQ/State/Branch ABOVE
    // itself years later. The plain create() screen above already lets
    // you add a node at any existing level in any order — what it can't
    // do is (a) introduce a level that doesn't exist yet without
    // breaking existing data, or (b) take nodes that already exist
    // (sitting flat under a State, or standing alone as a root) and
    // slot a new node in between them and their current position.
    //
    // This generalizes the one-off cbe:create-branch console command
    // (10 Sep 2026, Klang) into a reusable admin screen: create ONE new
    // node — at an existing level, or a brand-new level inserted at any
    // position — under any parent (or no parent, to become a new root),
    // and optionally move any number of already-existing nodes to
    // become children of it. Works for "insert a Branch under a State
    // and absorb its Temples" (Klang/Rawang/Kuantan) and "insert
    // Branch/State/HQ above an existing standalone Entity" (Rotary Club
    // Uptown) with the exact same mechanism.
    public function restructure(Request $request)
    {
        $groupId = $request->get('group');

        $groups = DB::table('group_labels')
            ->where('group_type', 'CBE')
            ->orderBy('group_name')
            ->get(['group_label_id', 'group_name']);

        $group = null;
        $levels = collect();
        $nodes = collect();

        if ($groupId) {
            $group = DB::table('group_labels')
                ->where('group_label_id', $groupId)
                ->where('group_type', 'CBE')
                ->first();

            if ($group) {
                $levels = DB::table('cbe_hierarchy_levels')
                    ->where('group_label_id', $groupId)
                    ->orderBy('level_order')
                    ->get(['level_id', 'level_order', 'level_name']);

                $nodes = DB::table('cbe_hierarchy_nodes as n')
                    ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                    ->where('n.group_label_id', $groupId)
                    ->orderBy('l.level_order')
                    ->orderBy('n.node_name')
                    ->select('n.node_id', 'n.node_name', 'n.node_name_zh', 'n.city', 'n.parent_node_id', 'l.level_order', 'l.level_name')
                    ->get();
            }
        }

        return view('admin.cbe-kpi.hierarchy-nodes.restructure', [
            'groups' => $groups,
            'group' => $group,
            'levels' => $levels,
            'nodes' => $nodes,
        ]);
    }

    public function storeRestructure(Request $request)
    {
        $groupId = $request->get('group_label_id');
        $group = DB::table('group_labels')->where('group_label_id', $groupId)->where('group_type', 'CBE')->first();
        abort_if(! $group, 404);

        $name = trim((string) $request->get('node_name'));
        $levelMode = $request->get('level_mode'); // 'existing' or 'new'
        $existingLevelId = $request->get('level_id');
        $newLevelName = trim((string) $request->get('new_level_name'));
        $newLevelPosition = $request->get('new_level_position'); // 'above' or 'below'
        $newLevelAnchorId = $request->get('new_level_anchor_id'); // an existing level_id to insert relative to
        $parentId = $request->get('parent_node_id') ?: null;
        $childIds = array_values(array_filter((array) $request->get('child_node_ids', [])));

        if ($name === '') {
            return back()->withErrors(['node_name' => __('cbe_masterfile.err_name_required')])->withInput();
        }
        if ($levelMode === 'existing' && ! $existingLevelId) {
            return back()->withErrors(['level_id' => __('cbe_masterfile.err_level_required')])->withInput();
        }
        if ($levelMode === 'new' && $newLevelName === '') {
            return back()->withErrors(['new_level_name' => __('cbe_masterfile.err_new_level_name_required')])->withInput();
        }

        DB::beginTransaction();
        try {
            // Resolve or create the level for the new node. Inserting a
            // level never deletes or touches any OTHER level row — only
            // level_order values shift (+1) to make room, so no
            // existing node's level_id foreign key is ever broken.
            if ($levelMode === 'new') {
                $anchor = $newLevelAnchorId
                    ? DB::table('cbe_hierarchy_levels')->where('level_id', $newLevelAnchorId)->where('group_label_id', $groupId)->first()
                    : null;

                if ($anchor) {
                    $newOrder = $newLevelPosition === 'above' ? $anchor->level_order : $anchor->level_order + 1;
                } else {
                    // No anchor picked = brand-new top-of-list level (first
                    // level ever for a group that had none, e.g. a fresh
                    // CBE group with zero levels so far).
                    $newOrder = 1;
                }

                DB::table('cbe_hierarchy_levels')
                    ->where('group_label_id', $groupId)
                    ->where('level_order', '>=', $newOrder)
                    ->increment('level_order');

                $levelId = (string) Str::uuid();
                DB::table('cbe_hierarchy_levels')->insert([
                    'level_id' => $levelId, 'group_label_id' => $groupId,
                    'level_order' => $newOrder, 'level_name' => $newLevelName,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $levelId = $existingLevelId;
            }

            $parent = $parentId
                ? DB::table('cbe_hierarchy_nodes')->where('node_id', $parentId)->where('group_label_id', $groupId)->first()
                : null;

            $nodeId = (string) Str::uuid();
            $nodePath = ($parent->hierarchy_path ?? '/').$nodeId.'/';

            DB::table('cbe_hierarchy_nodes')->insert([
                'node_id' => $nodeId, 'node_code' => null,
                'group_label_id' => $groupId, 'level_id' => $levelId,
                'parent_node_id' => $parent->node_id ?? null,
                'node_name' => $name,
                'node_name_zh' => trim((string) $request->get('node_name_zh')) ?: null,
                'city' => trim((string) $request->get('city')) ?: null,
                'postcode' => trim((string) $request->get('postcode')) ?: null,
                'address' => trim((string) $request->get('address')) ?: null,
                'contact_person_1' => trim((string) $request->get('contact_person_1')) ?: null,
                'contact_person_2' => trim((string) $request->get('contact_person_2')) ?: null,
                'external_reference_no' => null,
                'hierarchy_path' => $nodePath,
                'display_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            // Move each selected existing node (and everything beneath
            // it) under the new node — rewriting hierarchy_path for the
            // moved node itself AND every one of its own descendants,
            // since hierarchy_path is a materialized "full path from
            // root" string that every scoping query in this app relies
            // on (hierarchy_path LIKE 'prefix%').
            foreach ($childIds as $childId) {
                $child = DB::table('cbe_hierarchy_nodes')->where('node_id', $childId)->where('group_label_id', $groupId)->first();
                if (! $child || $child->node_id === $nodeId) {
                    continue;
                }

                $oldPrefix = $child->hierarchy_path;
                $newPrefix = $nodePath.$child->node_id.'/';

                // Every descendant (including the child itself) has a
                // hierarchy_path starting with $oldPrefix — swap that
                // leading segment for $newPrefix, keep the rest as-is.
                $descendants = DB::table('cbe_hierarchy_nodes')
                    ->where('group_label_id', $groupId)
                    ->where('hierarchy_path', 'like', $oldPrefix.'%')
                    ->get(['node_id', 'hierarchy_path']);

                foreach ($descendants as $d) {
                    $rest = substr($d->hierarchy_path, strlen($oldPrefix));
                    DB::table('cbe_hierarchy_nodes')->where('node_id', $d->node_id)->update([
                        'hierarchy_path' => $newPrefix.$rest,
                        'updated_at' => now(),
                    ]);
                }

                DB::table('cbe_hierarchy_nodes')->where('node_id', $childId)->update([
                    'parent_node_id' => $nodeId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withErrors(['node_name' => __('cbe_masterfile.err_restructure_failed', ['error' => $e->getMessage()])])->withInput();
        }

        return redirect()
            ->route('admin.cbe-kpi.hierarchy-nodes.restructure', ['group' => $groupId])
            ->with('cbe_node_saved', $name);
    }

    // NEW 11 Sep 2026 (Task #411) — per Chris: "any temple that is not
    // belong to anyone but standalone example temple Z then you allow to
    // create as per the organization hierarchy and also flexible to link
    // back to the cbe group in future ... rotary club damansara can link
    // back to Petaling Jaya branch for example, Temple Z can link back to
    // Klang Branch in Tao." Moves ONE existing node (source) out of its
    // current group and into a chosen level+parent inside a DIFFERENT
    // CBE group. Deliberately scoped to standalone (childless) nodes only
    // — a node WITH its own descendants would need every descendant's
    // level_id remapped to an equivalent level in the target group's own
    // level list, and there's no safe way to guess that mapping
    // automatically. That's a real gap, disclosed on-screen, not solved
    // silently. The source's old group_label row is left exactly as-is
    // (not deleted) — it may still be referenced elsewhere (e.g. its own
    // cbe_hierarchy_levels), and leaving it empty after the last node
    // moves out is harmless.
    public function linkToGroup(Request $request)
    {
        $sourceGroupId = $request->get('source_group');
        $sourceNodeId = $request->get('source_node');
        $targetGroupId = $request->get('target_group');
        $targetLevelId = $request->get('target_level');

        $groups = DB::table('group_labels')
            ->where('group_type', 'CBE')
            ->orderBy('group_name')
            ->get(['group_label_id', 'group_name']);

        $sourceGroup = null;
        $sourceNodes = collect();
        $sourceNode = null;
        $sourceChildCount = 0;
        $targetGroup = null;
        $targetLevels = collect();
        $targetLevel = null;
        $targetParentCandidates = collect();

        if ($sourceGroupId) {
            $sourceGroup = DB::table('group_labels')->where('group_label_id', $sourceGroupId)->where('group_type', 'CBE')->first();
            if ($sourceGroup) {
                $sourceNodes = DB::table('cbe_hierarchy_nodes as n')
                    ->join('cbe_hierarchy_levels as l', 'l.level_id', '=', 'n.level_id')
                    ->where('n.group_label_id', $sourceGroupId)
                    ->orderBy('l.level_order')
                    ->orderBy('n.node_name')
                    ->select('n.node_id', 'n.node_name', 'l.level_name')
                    ->get();

                if ($sourceNodeId) {
                    $sourceNode = DB::table('cbe_hierarchy_nodes')->where('node_id', $sourceNodeId)->where('group_label_id', $sourceGroupId)->first();
                    if ($sourceNode) {
                        $sourceChildCount = DB::table('cbe_hierarchy_nodes')->where('parent_node_id', $sourceNode->node_id)->count();
                    }
                }
            }
        }

        if ($sourceNode && $targetGroupId) {
            $targetGroup = DB::table('group_labels')->where('group_label_id', $targetGroupId)->where('group_type', 'CBE')->first();
            // Never allow "moving" to the same group it's already in.
            if ($targetGroup && $targetGroup->group_label_id === $sourceGroup->group_label_id) {
                $targetGroup = null;
            }

            if ($targetGroup) {
                $targetLevels = DB::table('cbe_hierarchy_levels')
                    ->where('group_label_id', $targetGroupId)
                    ->orderBy('level_order')
                    ->get(['level_id', 'level_order', 'level_name']);

                if ($targetLevelId) {
                    $targetLevel = $targetLevels->firstWhere('level_id', $targetLevelId);
                }

                if ($targetLevel) {
                    $parentLevel = $targetLevels->where('level_order', '<', $targetLevel->level_order)
                        ->sortByDesc('level_order')
                        ->first();

                    $targetParentCandidates = $parentLevel
                        ? DB::table('cbe_hierarchy_nodes')
                            ->where('group_label_id', $targetGroupId)
                            ->where('level_id', $parentLevel->level_id)
                            ->orderBy('node_name')
                            ->get(['node_id', 'node_name', 'coverage_postcode_start', 'coverage_postcode_end'])
                        : collect();
                }
            }
        }

        return view('admin.cbe-kpi.hierarchy-nodes.link-to-group', [
            'groups' => $groups,
            'sourceGroup' => $sourceGroup,
            'sourceNodes' => $sourceNodes,
            'sourceNode' => $sourceNode,
            'sourceChildCount' => $sourceChildCount,
            'targetGroup' => $targetGroup,
            'targetLevels' => $targetLevels,
            'targetLevel' => $targetLevel,
            'targetParentCandidates' => $targetParentCandidates,
        ]);
    }

    public function storeLinkToGroup(Request $request)
    {
        $sourceNodeId = $request->get('source_node_id');
        $targetGroupId = $request->get('target_group_id');
        $targetLevelId = $request->get('target_level_id');

        $sourceNode = DB::table('cbe_hierarchy_nodes')->where('node_id', $sourceNodeId)->first();
        abort_if(! $sourceNode, 404);

        $targetGroup = DB::table('group_labels')->where('group_label_id', $targetGroupId)->where('group_type', 'CBE')->first();
        abort_if(! $targetGroup, 404);

        $targetLevelOk = DB::table('cbe_hierarchy_levels')->where('level_id', $targetLevelId)->where('group_label_id', $targetGroupId)->exists();
        if (! $targetLevelOk) {
            return back()->withErrors(['target_level_id' => __('cbe_masterfile.err_level_required')])->withInput();
        }

        if ($targetGroupId === $sourceNode->group_label_id) {
            return back()->withErrors(['target_group_id' => __('cbe_masterfile.err_link_same_group')])->withInput();
        }

        // Guard: only a standalone (childless) node can be moved — see
        // the class-level comment on linkToGroup() for why.
        $childCount = DB::table('cbe_hierarchy_nodes')->where('parent_node_id', $sourceNode->node_id)->count();
        if ($childCount > 0) {
            return back()->withErrors(['source_node_id' => __('cbe_masterfile.err_link_has_children', ['count' => $childCount])])->withInput();
        }

        // CHANGED 12 Sep 2026 — per Chris: this is exactly his "Temple A
        // joins TAO Association later" scenario, and exactly where a
        // manually wrong Parent pick would do the most damage. The Target
        // Parent dropdown is gone — the parent within the target
        // community is derived automatically from Temple A's own postcode
        // against whatever City/Branch/State/HQ already exists there
        // (skipping straight to whichever of those actually exists), the
        // same rule used for a brand-new entity.
        $link = \App\Services\CbeHierarchyLinkService::resolveParent($targetGroupId, $targetLevelId, $sourceNode->postcode);

        if ($link['status'] === 'ambiguous') {
            return back()->withErrors(['target_level_id' => __('cbe_masterfile.err_parent_ambiguous')])->withInput();
        }

        $targetParentId = $link['parent_node_id'];
        $targetParent = $targetParentId
            ? DB::table('cbe_hierarchy_nodes')->where('node_id', $targetParentId)->where('group_label_id', $targetGroupId)->first()
            : null;

        $newHierarchyPath = ($targetParent->hierarchy_path ?? '/').$sourceNode->node_id.'/';

        DB::table('cbe_hierarchy_nodes')->where('node_id', $sourceNode->node_id)->update([
            'group_label_id' => $targetGroupId,
            'level_id' => $targetLevelId,
            'parent_node_id' => $targetParent->node_id ?? null,
            'hierarchy_path' => $newHierarchyPath,
            'updated_at' => now(),
        ]);

        // The newly-joined entity may itself be the missing parent for
        // other still-unlinked entities already sitting in the target
        // community (and vice versa, next time one is added there) — see
        // the matching call in store() above for why this always runs.
        \App\Services\CbeHierarchyLinkService::relinkOrphans($targetGroupId);

        return redirect()
            ->route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $targetGroupId])
            ->with('cbe_node_saved', $sourceNode->node_name);
    }
}
