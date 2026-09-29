<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 -- per Chris: "temple/NGO committee team or SME CBE
// group management team... President, deputy, secretary, treasurer...
// CEO, Finance director... you should have a master file to set up the
// position according to the cbe group." Fully Admin-editable catalog
// of committee/management position titles, never hardcoded -- same
// landing (Add New / Search & View/Edit) > search (typeahead) >
// single-record edit pattern already used for Appointment Position
// Types and Reason Code, per Chris's explicit request to keep every
// master file screen consistent.
//
// REBUILT 24 Sep 2026 -- per Chris: "you must have the position for
// each cbe group, different cbe may have different position... this
// position masterfile set up follow the method how we set up rank in
// org group."
//
// CORRECTED 25 Sep 2026 -- per Chris: "it never have such position in
// CBE goup Persatuan Tao." The 24 Sep version kept the original 6
// positions as ONE row shared by every CBE group (group_label_id
// NULL, "System Default") that could never be turned off -- so
// CEO/Finance Director (SME-style titles) were forced onto every
// religious/NGO group too, with no way to hide them for a group like
// Tao without affecting every other group. This now matches the
// role_ranks pattern exactly, as originally asked: every CBE group
// owns its OWN independent set of positions (no shared/NULL rows at
// all). A brand new CBE group starts with none and Admin adds
// whichever apply to it. See migration
// 2026_09_25_000002_localize_cbe_committee_position_types_per_group.php
// for the one-time conversion of existing data.
class AdminCommitteePositionTypeController extends Controller
{
    // The CBE group currently selected via the picker (or the first
    // CBE group if none was explicitly chosen yet). Shared by every
    // action below so the picker's choice always carries through.
    private function currentGroupLabelId(Request $request, $cbeGroups)
    {
        return $request->filled('group_label_id')
            ? $request->get('group_label_id')
            : ($cbeGroups->first()->group_label_id ?? null);
    }

    public function index(Request $request)
    {
        $cbeGroups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $this->currentGroupLabelId($request, $cbeGroups);

        // FIXED 27 Sep 2026 — an empty search box is dropped from the
        // Next / Prev links (empty -> null), which sent Next back to the
        // Add / Search landing. page / per_page also mean "results shown".
        $hasAnyFilter = $request->hasAny(['search', 'page', 'per_page']);
        $types = null;
        $inactiveCount = 0;

        if ($hasAnyFilter) {
            $query = DB::table('cbe_committee_position_types')->where('group_label_id', $groupLabelId);
            // CHANGED 27 Sep 2026 — per Chris: show only this CBE group's own
            // positions (the ones its Committee tab offers). The old generic
            // English placeholders that were switched off (cbe:clean-generic-
            // committee-positions) are not this group's positions and are
            // never listed; no Active / Inactive shown.
            $query->where('is_active', true);
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $field = $request->get('field', '');
                $allowedFields = ['position_label', 'code'];
                $query->where(function ($sub) use ($s, $field, $allowedFields) {
                    if (in_array($field, $allowedFields, true)) {
                        $sub->where($field, 'like', $s);
                    } else {
                        $sub->where('position_label', 'like', $s)
                            ->orWhere('code', 'like', $s);
                    }
                });
            }
            // CHANGED 26 Sep 2026 — per Chris: "NO SCROLL". Rows per page now
            // come from the screen itself (per_page, measured by the view so
            // the list always fits with no scrolling); default 10.
            $perPageIn = (int) $request->get('per_page');
            $perPage = $perPageIn > 0 ? max(3, min(50, $perPageIn)) : 10;
            $types = $query->orderBy('sort_order')->paginate($perPage)->withQueryString();
        }

        return view('admin.committee-position-types.index', compact('hasAnyFilter', 'types', 'cbeGroups', 'groupLabelId', 'inactiveCount'));
    }

    public function searchForm(Request $request)
    {
        $cbeGroups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $this->currentGroupLabelId($request, $cbeGroups);

        return view('admin.committee-position-types.search-form', compact('cbeGroups', 'groupLabelId'));
    }

    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // Scoped to whichever CBE group the search screen has selected
        // (read from the page at request time, same fix used for Group
        // Name & Hierarchy Levels' own type-ahead).
        $groupLabelId = $request->get('group_label_id') ?: null;

        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['position_label', 'code'];

        $s = '%' . $q . '%';
        $results = DB::table('cbe_committee_position_types')
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('position_label', 'like', $s)
                        ->orWhere('code', 'like', $s);
                }
            })
            ->where('group_label_id', $groupLabelId)
            ->orderBy('position_label')
            ->limit(15)
            ->get(['id', 'position_label']);

        return response()->json($results);
    }

    public function create(Request $request)
    {
        return $this->edit(null, $request);
    }

    public function edit(?string $id = null, ?Request $request = null)
    {
        $request = $request ?? request();
        $type = $id ? DB::table('cbe_committee_position_types')->where('id', $id)->first() : null;
        abort_if($id && ! $type, 404);

        $cbeGroups = DB::table('group_labels')->where('group_type', 'CBE')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        // An existing position keeps the group it was created for
        // (never changes on edit, same rule Role Ranks follows); a new
        // one is created under whichever group the picker currently has
        // selected.
        $groupLabelId = $type ? $type->group_label_id : $this->currentGroupLabelId($request, $cbeGroups);
        $groupLabelName = $cbeGroups->firstWhere('group_label_id', $groupLabelId)->group_name ?? __('admin_committee_types.unknown_group');

        return view('admin.committee-position-types.edit', compact('type', 'cbeGroups', 'groupLabelId', 'groupLabelName'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'position_label' => ['required', 'string', 'max:150'],
            'group_label_id' => ['required', 'exists:group_labels,group_label_id'],
        ]);

        $groupLabelId = $request->input('group_label_id');

        $baseCode = strtoupper((string) Str::slug($request->position_label, '_'));
        $code = $baseCode !== '' ? $baseCode : 'POSITION';
        $i = 1;
        // 'code' only needs to be unique WITHIN this CBE group now --
        // two different groups can both have a "PRESIDENT" position.
        while (DB::table('cbe_committee_position_types')->where('group_label_id', $groupLabelId)->where('code', $code)->exists()) {
            $i++;
            $code = $baseCode.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_committee_position_types')->where('group_label_id', $groupLabelId)->max('sort_order');

        DB::table('cbe_committee_position_types')->insert([
            'id' => (string) Str::uuid(),
            'group_label_id' => $groupLabelId,
            'code' => $code,
            'position_label' => trim($request->position_label),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.committee-position-types.index', ['group_label_id' => $groupLabelId])->with('cbe_committee_type_saved', true);
    }

    public function update(Request $request, string $id)
    {
        $type = DB::table('cbe_committee_position_types')->where('id', $id)->first();
        abort_if(! $type, 404);

        $request->validate([
            'position_label' => ['required', 'string', 'max:150'],
        ]);

        // CHANGED 25 Sep 2026 -- per Chris: a position now belongs to
        // exactly one CBE group, so turning it off here can never
        // affect any other group. The old "the 6 original positions
        // can never be switched off" rule is gone -- every position is
        // fully editable/deactivatable per group, same as any custom
        // one.
        DB::table('cbe_committee_position_types')->where('id', $id)->update([
            'position_label' => trim($request->position_label),
            'is_active' => $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.committee-position-types.index', ['group_label_id' => $type->group_label_id])->with('cbe_committee_type_saved', true);
    }

    // NEW 24 Sep 2026 -- per Chris: Move Up/Move Down, same as Role
    // Ranks. A position only ever swaps places with its neighbour
    // WITHIN its own CBE group's list -- never crossing into another
    // group's list, since sort_order is otherwise a single number
    // shared by every position in the table.
    public function moveUp(Request $request, string $id)
    {
        $this->swapWithNeighbour($id, 'up');

        return redirect()->route('admin.committee-position-types.index', $request->only(['group_label_id', 'search', 'per_page', 'page']));
    }

    public function moveDown(Request $request, string $id)
    {
        $this->swapWithNeighbour($id, 'down');

        return redirect()->route('admin.committee-position-types.index', $request->only(['group_label_id', 'search', 'per_page', 'page']));
    }

    private function swapWithNeighbour(string $id, string $direction): void
    {
        $item = DB::table('cbe_committee_position_types')->where('id', $id)->first();
        if (! $item) {
            return;
        }

        $cluster = DB::table('cbe_committee_position_types')
            ->where('group_label_id', $item->group_label_id)
            ->orderBy('sort_order')
            ->get();

        $index = $cluster->search(fn ($r) => $r->id === $id);
        if ($index === false) {
            return;
        }
        $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($neighbourIndex < 0 || $neighbourIndex >= $cluster->count()) {
            return; // already at the top/bottom of this group's list
        }
        $neighbour = $cluster[$neighbourIndex];

        DB::transaction(function () use ($item, $neighbour) {
            DB::table('cbe_committee_position_types')->where('id', $item->id)->update(['sort_order' => $neighbour->sort_order, 'updated_at' => now()]);
            DB::table('cbe_committee_position_types')->where('id', $neighbour->id)->update(['sort_order' => $item->sort_order, 'updated_at' => now()]);
        });
    }
}
