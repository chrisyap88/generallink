<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 24 Jul 2026 — Rank Definition Maintenance. Per Chris: define any
// number of Ranks under each of the 3 fixed system roles (e.g. Regional
// Director + Master Agency under Mgt). This screen only names/organizes
// ranks — the % breakdown per rank is set later, per Earning Income
// Structure, on the Rank Allocation screen (each structure can split a
// role's % differently across vendors/products).
//
// UPDATED 24 Jul 2026 — group-scoped ranks.
// RESCOPED 31 Jul 2026 — per Chris: ranks are scoped to
// group_labels.group_label_id — ONE shared rank ladder per Special
// Privilege Group (prihatin2u/rela2u/PVATM), no matter which individual
// GL's team an agent is organizationally under. NULL group_label_id =
// "System Default".
//
// REDESIGNED 31 Jul 2026 (2) — unified single table (one row per rank,
// tick which of Mgt/Ops/Aff it belongs to), Rank No system-generated,
// Move Up/Down instead of manual renumbering.
//
// REDESIGNED 31 Jul 2026 (3) — per Chris: the Rank No must be ONE running
// sequence across the whole screen (Mgt block, then Ops block, then Aff
// block — never separate counters per category), and adding a rank must
// happen inline, right on the row you clicked "+" from, without leaving
// the screen. To do this without losing the ability to insert in the
// middle of a category (Chris's original "insert row" complaint), the
// DB still stores rank_no as a per-(role, group_label) cluster counter
// (unchanged storage/insert/move logic below) — but the NUMBER SHOWN ON
// SCREEN is always recomputed at read time as the rank's position in the
// full Mgt-then-Ops-then-Aff list (see orderedList()). That single change
// satisfies "always in sequence order" without any schema change, because
// the stored per-category rank_no is only ever used internally now as a
// stable sort/tiebreak key. All add/edit/delete/move actions respond to
// AJAX (wantsJson()) so the screen never navigates away.
class RoleRankController extends Controller
{
    private const ROLES = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];
    private const PER_PAGE = 10; // Chris: exactly 10 rows per screen, no scroll — row 11 starts page 2.

    // Full ordered list (Mgt block, then Ops, then Aff — never mixed) for
    // a group_label scope, with a transient ->display_rank_no (1..N,
    // continuous across the whole list) attached to each row. This is
    // what the screen shows in the "Rank No" column — never the raw
    // per-category role_ranks.rank_no.
    private function orderedList(?string $groupLabelId)
    {
        $ranks = DB::table('role_ranks')->where('group_label_id', $groupLabelId)->get();

        $roleOrder = array_flip(self::ROLES);
        $sorted = $ranks->sort(function ($a, $b) use ($roleOrder) {
            return $roleOrder[$a->role] <=> $roleOrder[$b->role] ?: strnatcmp($a->rank_no ?? '0', $b->rank_no ?? '0');
        })->values();

        return $sorted->map(function ($r, $i) {
            $r->display_rank_no = $i + 1;
            return $r;
        });
    }

    private function paginate($sorted, int $page, string $url, array $query): LengthAwarePaginator
    {
        $items = $sorted->forPage($page, self::PER_PAGE);
        return new LengthAwarePaginator($items, $sorted->count(), self::PER_PAGE, $page, [
            'path' => $url,
            'query' => $query,
        ]);
    }

    public function index(Request $request)
    {
        // FIXED 18 Aug 2026 — per Chris: Rank Hierarchy Structure is an
        // Organization Rewards Group (ORG) program — the picker must
        // only ever offer ORG groups (PVATM, etc.), never "System
        // Default" (that's DSG's own concept) and never DSG/CBE groups
        // like "My Way" mixed in. Defaults to the first ORG group so
        // the screen always opens on real, editable data.
        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : ($groupLabels->first()->group_label_id ?? null);

        $sorted = $this->orderedList($groupLabelId);
        $page = max((int) $request->get('page', 1), 1);
        $paginator = $this->paginate($sorted, $page, $request->url(), $request->query());

        $roleLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::label($role, $groupLabelId)]);
        $roleShortLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::shortLabel($role, 3, $groupLabelId)]);

        return view('masterfile.role-ranks', compact('paginator', 'roleLabels', 'roleShortLabels', 'groupLabels', 'groupLabelId'));
    }

    // AJAX refresh — returns just the <tbody> rows (+ footer pagination
    // strip) as rendered HTML, so the inline add/edit/delete/move actions
    // can update the table without ever navigating away from this screen.
    public function rows(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : null;
        $sorted = $this->orderedList($groupLabelId);
        $page = max((int) $request->get('page', 1), 1);
        $paginator = $this->paginate($sorted, $page, route('admin.masterfile.role-ranks'), ['group_label_id' => $groupLabelId]);

        $roleShortLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::shortLabel($role, 3, $groupLabelId)]);

        $html = view('masterfile.partials.role-ranks-rows', compact('paginator', 'roleShortLabels'))->render();
        $footer = view('masterfile.partials.role-ranks-footer', compact('paginator'))->render();

        return response()->json(['html' => $html, 'footer' => $footer]);
    }

    public function create(Request $request)
    {
        return $this->edit(null, $request);
    }

    public function edit(?string $id = null, ?Request $request = null)
    {
        $request = $request ?? request();
        $rank = $id ? DB::table('role_ranks')->where('rank_id', $id)->firstOrFail() : null;

        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $rank ? $rank->group_label_id : ($request->filled('group_label_id') ? $request->get('group_label_id') : ($groupLabels->first()->group_label_id ?? null));
        $groupLabelName = $groupLabelId ? ($groupLabels->firstWhere('group_label_id', $groupLabelId)->group_name ?? 'Unknown Group') : 'System Default';

        $roleLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::label($role, $groupLabelId)]);
        $roleShortLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::shortLabel($role, 3, $groupLabelId)]);
        return view('masterfile.role-rank-edit', compact('rank', 'roleLabels', 'roleShortLabels', 'groupLabelId', 'groupLabelName'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'role'                 => ['required', 'in:' . implode(',', self::ROLES)],
            'group_label_id'       => ['nullable', 'exists:group_labels,group_label_id'],
            'rank_name'            => ['required', 'string', 'max:100'],
            'insert_after_rank_id' => ['nullable', 'exists:role_ranks,rank_id'],
        ]);

        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        if ($request->filled('insert_after_rank_id')) {
            $rankNo = $this->insertAfterRankNo($request->role, $groupLabelId, $request->input('insert_after_rank_id'));
        } else {
            $rankNo = $this->nextRankNo($request->role, $groupLabelId);
        }

        $newId = (string) Str::uuid();
        DB::table('role_ranks')->insert([
            'rank_id'        => $newId,
            'role'           => $request->role,
            'group_label_id' => $groupLabelId,
            'rank_no'        => $rankNo,
            'rank_name'      => $request->rank_name,
            'display_order'  => 0,
            'is_active'      => true,
            'created_by'     => Auth::guard('agent')->id(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Rank added.', 'rank_id' => $newId]);
        }

        return redirect()->route('admin.masterfile.role-ranks', $groupLabelId ? ['group_label_id' => $groupLabelId] : [])->with('success', 'Rank added.');
    }

    public function update(Request $request, string $id)
    {
        $rank = DB::table('role_ranks')->where('rank_id', $id)->firstOrFail();

        $request->validate([
            'role'      => ['required', 'in:' . implode(',', self::ROLES)],
            'rank_name' => ['required', 'string', 'max:100'],
            'is_active' => ['nullable', 'in:0,1'],
        ]);

        // If the category itself changed (re-ticked a different box),
        // this rank leaves its old category's number sequence entirely
        // and gets appended to the end of the new one — its old number
        // meant nothing outside that category anyway.
        $rankNo = $rank->rank_no;
        if ($request->role !== $rank->role) {
            $rankNo = $this->nextRankNo($request->role, $rank->group_label_id);
        }

        DB::table('role_ranks')->where('rank_id', $id)->update([
            'role'       => $request->role,
            'rank_no'    => $rankNo,
            'rank_name'  => $request->rank_name,
            'is_active'  => $request->filled('is_active') ? $request->is_active : $rank->is_active,
            'updated_at' => now(),
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Rank updated.']);
        }

        return redirect()->route('admin.masterfile.role-ranks', $rank->group_label_id ? ['group_label_id' => $rank->group_label_id] : [])->with('success', 'Rank updated successfully.');
    }

    // Next free Rank No within a (role, group_label) cluster — appended
    // at the end. Used when there's no neighbour to insert after (e.g.
    // the very first rank in an empty category).
    private function nextRankNo(string $role, ?string $groupLabelId): string
    {
        $existing = DB::table('role_ranks')
            ->where('role', $role)
            ->when($groupLabelId, fn($q) => $q->where('group_label_id', $groupLabelId), fn($q) => $q->whereNull('group_label_id'))
            ->pluck('rank_no');

        $max = $existing->map(fn($n) => (int) $n)->max() ?? 0;
        return (string) ($max + 1);
    }

    // NEW 31 Jul 2026 (3) — insert directly after a specific neighbour
    // rank, within that neighbour's own (role, group_label) cluster.
    // Shifts every rank_no greater than the neighbour's by +1 first, so
    // the new rank can slot into neighbour's rank_no + 1 without a
    // collision. This is what powers the "+" button's Excel-style
    // insert-row-below behaviour.
    private function insertAfterRankNo(string $role, ?string $groupLabelId, string $afterRankId): string
    {
        $neighbour = DB::table('role_ranks')->where('rank_id', $afterRankId)->first();
        if (!$neighbour || $neighbour->role !== $role || ($neighbour->group_label_id ?? null) !== $groupLabelId) {
            // Neighbour isn't actually in this cluster (e.g. Admin
            // re-ticked category before saving) — just append at the end.
            return $this->nextRankNo($role, $groupLabelId);
        }

        $afterNo = (int) $neighbour->rank_no;

        DB::table('role_ranks')
            ->where('role', $role)
            ->when($groupLabelId, fn($q) => $q->where('group_label_id', $groupLabelId), fn($q) => $q->whereNull('group_label_id'))
            ->get()
            ->filter(fn($r) => (int) $r->rank_no > $afterNo)
            ->each(function ($r) {
                DB::table('role_ranks')->where('rank_id', $r->rank_id)->update([
                    'rank_no'    => (string) ((int) $r->rank_no + 1),
                    'updated_at' => now(),
                ]);
            });

        return (string) ($afterNo + 1);
    }

    // NEW 31 Jul 2026 — Move Up/Down replaces manual renumbering. Swaps
    // this rank's Rank No with whichever neighbour (within the same role
    // + group_label cluster) currently sits immediately before/after it,
    // so reordering never risks two ranks landing on the same number.
    public function moveUp(Request $request, string $id)
    {
        $this->swapWithNeighbour($id, 'up');
        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Rank moved up.');
    }

    public function moveDown(Request $request, string $id)
    {
        $this->swapWithNeighbour($id, 'down');
        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Rank moved down.');
    }

    private function swapWithNeighbour(string $id, string $direction): void
    {
        $rank = DB::table('role_ranks')->where('rank_id', $id)->firstOrFail();

        $cluster = DB::table('role_ranks')
            ->where('role', $rank->role)
            ->when($rank->group_label_id, fn($q) => $q->where('group_label_id', $rank->group_label_id), fn($q) => $q->whereNull('group_label_id'))
            ->get()
            ->sortBy(fn($r) => $r->rank_no, SORT_NATURAL)->values();

        $index = $cluster->search(fn($r) => $r->rank_id === $id);
        if ($index === false) {
            return;
        }
        $neighbourIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($neighbourIndex < 0 || $neighbourIndex >= $cluster->count()) {
            return; // already at the top/bottom of this cluster
        }
        $neighbour = $cluster[$neighbourIndex];

        DB::transaction(function () use ($rank, $neighbour) {
            DB::table('role_ranks')->where('rank_id', $rank->rank_id)->update(['rank_no' => $neighbour->rank_no, 'updated_at' => now()]);
            DB::table('role_ranks')->where('rank_id', $neighbour->rank_id)->update(['rank_no' => $rank->rank_no, 'updated_at' => now()]);
        });
    }

    public function destroy(Request $request, string $id)
    {
        $rank = DB::table('role_ranks')->where('rank_id', $id)->firstOrFail();

        abort_if(DB::table('agents')->where('rank_id', $id)->exists(), 422, 'Cannot remove — agents are currently assigned this rank.');
        abort_if(DB::table('commission_rank_allocations')->where('rank_id', $id)->exists(), 422, 'Cannot remove — this rank is used in an Earning Income Structure allocation.');

        DB::table('role_ranks')->where('rank_id', $id)->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Rank removed.']);
        }

        return redirect()->route('admin.masterfile.role-ranks', $rank->group_label_id ? ['group_label_id' => $rank->group_label_id] : [])->with('success', 'Rank removed.');
    }
}
