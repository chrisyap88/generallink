<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 31 Jul 2026 — Rank system redesign, Phase 1. Per Chris: "i suggest
// you open a totally new screen when want to set up by rank." Moved the
// rank % grid out of the Earning Income Structure Add/Edit form.
//
// REDESIGNED 31 Jul 2026 (2) — per Chris: "when setting up the product
// earning chart, you point to the same format like this screen [the
// Organization Rank Hierarchy Structure table] different is there is %
// input at each rank row. meaning if i set by ranks after specify the
// group label you already know what is the rank assignment." So this
// screen now: (1) has the same Group Label picker as the Rank Hierarchy
// screen — pick a group, and you already know its exact rank ladder;
// (2) shows that ladder as the SAME unified table (Rank No / Rank Name /
// Mgt / Ops / Aff), one row per rank, with a % input bolted on — instead
// of the old 3-column "all groups at once" grid. Rank No is the same
// globally-running number (Mgt block, then Ops, then Aff) as the Rank
// Hierarchy screen, computed the same way.
//
// IMPORTANT FIX made during this redesign: the old screen showed every
// group's ranks on one page and, on save, deleted+reinserted
// commission_rank_allocations for ALL ranks system-wide. Since the new
// screen only shows ONE group's ranks at a time, save must only touch
// that group's own rank rows — otherwise saving System Default's
// allocation would silently wipe out every Organization Rewards Group's
// saved percentages on the same structure. All delete/insert calls below
// are scoped to the ranks belonging to the selected group only.
class RankAllocationController extends Controller
{
    private const ROLES = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];

    private function orderedRanksForGroup(?string $groupLabelId)
    {
        $ranks = DB::table('role_ranks')->where('is_active', true)->where('group_label_id', $groupLabelId)->get();

        $roleOrder = array_flip(self::ROLES);
        $sorted = $ranks->sort(function ($a, $b) use ($roleOrder) {
            return $roleOrder[$a->role] <=> $roleOrder[$b->role] ?: strnatcmp($a->rank_no ?? '0', $b->rank_no ?? '0');
        })->values();

        return $sorted->map(function ($r, $i) {
            $r->display_rank_no = $i + 1;
            return $r;
        });
    }

    public function edit(Request $request, string $structureId)
    {
        $structure = DB::table('commission_structures as cs')
            ->join('vendors as v', 'v.vendor_id', '=', 'cs.vendor_id')
            ->join('products as p', 'p.product_id', '=', 'cs.product_id')
            ->where('cs.structure_id', $structureId)
            ->first(['cs.*', 'v.vendor_name', 'p.product_name']);

        abort_if(!$structure, 404, 'Earning Income Structure not found.');

        // FIXED 18 Aug 2026 — per Chris: Rank Allocation only applies
        // to Organization Rewards Group (ORG) ranks — picker must only
        // offer ORG groups, no System Default, no DSG/CBE mixed in.
        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);
        $groupLabelId = $request->filled('group_label_id') ? $request->get('group_label_id') : ($groupLabels->first()->group_label_id ?? null);

        $ranks = $this->orderedRanksForGroup($groupLabelId);
        $rankIds = $ranks->pluck('rank_id');

        $existingRankAllocations = DB::table('commission_rank_allocations')
            ->where('structure_id', $structureId)
            ->whereIn('rank_id', $rankIds)
            ->pluck('rank_pct', 'rank_id');

        $existingOverrides = DB::table('commission_rank_overrides')
            ->where('structure_id', $structureId)
            ->whereIn('rank_id', $rankIds)
            ->get()
            ->groupBy('rank_id')
            ->map(fn($rows) => $rows->pluck('override_pct', 'target_role'));

        $roleShortLabels = collect(self::ROLES)->mapWithKeys(fn($role) => [$role => RoleLabelService::shortLabel($role, 3, $groupLabelId)]);

        return view('masterfile.rank-allocation', compact(
            'structure', 'ranks', 'groupLabels', 'groupLabelId',
            'existingRankAllocations', 'existingOverrides', 'roleShortLabels'
        ));
    }

    public function update(Request $request, string $structureId)
    {
        $structure = DB::table('commission_structures')->where('structure_id', $structureId)->first();
        abort_if(!$structure, 404, 'Earning Income Structure not found.');

        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;
        $ranks = $this->orderedRanksForGroup($groupLabelId);

        if ($structure->is_rank_only) {
            $rankError = $this->validateRankOnlyAllocations($request, $ranks, (float) $structure->total_commission_pct);
        } else {
            $rankError = $this->validateRankAllocations($request, $structure, $ranks, $groupLabelId);
        }

        if ($rankError) {
            return back()->withErrors(['splits' => $rankError])->withInput();
        }

        $this->saveRankAllocations($request, $structureId, $ranks, (bool) $structure->is_rank_only);

        // NEW 1 Aug 2026 — per Chris: once you break the 10% down by rank,
        // the HQ/Cwg/Introducer numbers on the Edit screen should stop
        // being separately-typed-in numbers and instead just SHOW what the
        // ranks actually add up to per category, kept in sync automatically.
        // Only done for System Default (group_label_id is null): the
        // commission_structures table has ONE shared set of role %
        // columns, not one per group, so rolling up a Special Privilege
        // Group's own rank split (e.g. PVATM's) into those shared columns
        // would silently overwrite System Default's numbers every time a
        // different group's ranks were saved — the same "last write wins"
        // bug already hit once this session with role_label_overrides.
        // System Default is the one glogal case where this structure's own
        // rank ladder IS the definitive one, so it's safe to sync there.
        if ($groupLabelId === null) {
            $this->rollUpRoleTotals($structureId, $ranks, $request);
        }

        AuditService::logChange('commission_structures', $structureId, 'RANK_ALLOCATION_UPDATED', null, $request->all());

        return redirect()->route('admin.masterfile.commissions.rank-allocation', [$structureId, 'group_label_id' => $groupLabelId])
            ->with('success', 'Rank allocation saved.');
    }

    // Every role's pool (its own ranks within THIS group + any cross-role
    // overrides drawn into it from other ranks in the SAME group) must
    // sum to EXACTLY that role's flat % on this structure.
    private function validateRankAllocations(Request $request, object $structure, $ranks, ?string $groupLabelId = null): ?string
    {
        $roleFieldMap = [
            'GROUP_LEADER' => 'group_leader_pct',
            'TEAM_LEADER'  => 'team_leader_pct',
            'INTRODUCER'   => 'introducer_pct',
        ];

        $rankPctInput     = $request->input('rank_pct', []);
        $overridePctInput = $request->input('override_pct', []);

        foreach ($roleFieldMap as $role => $field) {
            $roleTotal   = (float) $structure->{$field};
            $ranksOfRole = $ranks->where('role', $role)->values();

            $overrideDescriptions = [];
            $overridesIntoThisRole = 0.0;
            foreach ($ranks as $rank) {
                if ($rank->role === $role) {
                    continue;
                }
                $pct = (float) ($overridePctInput[$rank->rank_id][$role] ?? 0);
                if ($pct > 0) {
                    $overridesIntoThisRole += $pct;
                    $overrideDescriptions[] = "{$rank->rank_name} override ({$pct}%)";
                }
            }

            if ($ranksOfRole->isEmpty() && $overridesIntoThisRole <= 0) {
                continue; // no ranks defined for this role in this group — keeps using the flat % directly
            }

            $rankSum = 0.0;
            foreach ($ranksOfRole as $rank) {
                $rankSum += (float) ($rankPctInput[$rank->rank_id] ?? 0);
            }
            $totalDrawn = $rankSum + $overridesIntoThisRole;

            if (abs($totalDrawn - $roleTotal) > 0.001) {
                $roleLabel = RoleLabelService::shortLabel($role, 3, $groupLabelId);
                $parts = $ranksOfRole->pluck('rank_name')->all();
                $desc  = implode(' + ', array_merge($parts, $overrideDescriptions));
                return "{$desc} (drawing from {$roleLabel}'s pool) must add up to exactly {$roleLabel}'s {$roleTotal}%. Currently: {$totalDrawn}%.";
            }
        }

        return null;
    }

    // Rank-Only Structure mode — every active rank in this group (any
    // category) must add up to the structure's own Total %.
    private function validateRankOnlyAllocations(Request $request, $ranks, float $total): ?string
    {
        if ($ranks->isEmpty()) {
            return null;
        }

        $rankPctInput = $request->input('rank_pct', []);
        $sum = 0.0;
        foreach ($ranks as $rank) {
            $sum += (float) ($rankPctInput[$rank->rank_id] ?? 0);
        }

        if (abs($sum - $total) > 0.001) {
            $desc = implode(' + ', $ranks->pluck('rank_name')->all());
            return "{$desc} must add up to exactly the Total Earning Income % ({$total}%). Currently: {$sum}%.";
        }

        return null;
    }

    // Scoped to the ranks belonging to the SELECTED GROUP ONLY — other
    // groups' saved allocations on this same structure are untouched.
    private function saveRankAllocations(Request $request, string $structureId, $ranks, bool $isRankOnly = false): void
    {
        $rankPctInput     = $request->input('rank_pct', []);
        $overridePctInput = $request->input('override_pct', []);
        $rankIds = $ranks->pluck('rank_id');

        $toInsert = [];
        foreach ($ranks as $rank) {
            $toInsert[] = [
                'allocation_id' => (string) Str::uuid(),
                'structure_id'  => $structureId,
                'rank_id'       => $rank->rank_id,
                'rank_pct'      => (float) ($rankPctInput[$rank->rank_id] ?? 0),
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::table('commission_rank_allocations')->where('structure_id', $structureId)->whereIn('rank_id', $rankIds)->delete();
        if (!empty($toInsert)) {
            DB::table('commission_rank_allocations')->insert($toInsert);
        }

        $overridesToInsert = [];
        if (!$isRankOnly) {
            foreach ($ranks as $rank) {
                foreach (self::ROLES as $targetRole) {
                    if ($targetRole === $rank->role) {
                        continue;
                    }
                    $pct = (float) ($overridePctInput[$rank->rank_id][$targetRole] ?? 0);
                    if ($pct > 0) {
                        $overridesToInsert[] = [
                            'override_id'  => (string) Str::uuid(),
                            'structure_id' => $structureId,
                            'rank_id'      => $rank->rank_id,
                            'target_role'  => $targetRole,
                            'override_pct' => $pct,
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ];
                    }
                }
            }
        }

        DB::table('commission_rank_overrides')->where('structure_id', $structureId)->whereIn('rank_id', $rankIds)->delete();
        if (!empty($overridesToInsert)) {
            DB::table('commission_rank_overrides')->insert($overridesToInsert);
        }
    }
}
