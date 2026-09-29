<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RoleLabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 23 Jul 2026 — per Chris: "create an option for me to rename or
// edit the 3 level Group Leader Team Leader ... as a sub menu under
// Master [File Maintenance] as Organization Category structure. Only
// 3 category ... just an edit function". Exactly 3 fixed rows — no
// add, no delete, just renaming the display label for GROUP_LEADER /
// TEAM_LEADER / INTRODUCER. Does not touch the `role` column on
// agents, permissions, or commission logic at all — see
// RoleLabelService for exactly what's wired to read this.
//
// UPDATED 24 Jul 2026 — per Chris: PVATM has its own company structure
// and needs its own role labels while other groups keep the default
// (e.g. "Regional Director" / "Agency Manager" / "Sales Agent" instead
// of Group Leader / Team Leader / Introducer). A Group selector was
// added — "System Default" (the original behaviour) or any specific
// group, whose 3 rows are edited independently. Still exactly 3 rows
// per selection — no add, no delete.
//
// RESCOPED 31 Jul 2026 — per Chris (found via the Rank Hierarchy/Rank
// Allocation screens showing the wrong short labels for PVATM): the
// group selector here was picking from `groups` (one individual GL's
// own personal team), the exact same group_id-vs-group_label_id flaw
// already fixed for role_ranks. Switched to `group_labels` (System
// Default/prihatin2u/rela2u/PVATM) — the shared Special Privilege
// Group identity — so "PVATM's labels" is finally one real, single
// thing instead of being tied to whichever individual GL team Admin
// happened to pick.
class OrgCategoryController extends Controller
{
    // Capped at 20 — see the migration comment for why. The Renewal
    // Forecast filter boxes (the narrowest screen found) start
    // truncating past roughly 14-16 characters for the label alone;
    // 20 leaves room for most other screens but Chris should still
    // keep labels as short as practical.
    private const MAX_LABEL_LENGTH = 20;

    // NEW 24 Jul 2026 — per Chris: he wants to type the short badge
    // text himself (org tree / drilldown role tags) instead of the
    // auto-derived initials. Matches the short_label DB column width.
    private const MAX_SHORT_LABEL_LENGTH = 3;

    public function index(Request $request)
    {
        // FIXED 18 Aug 2026 — per Chris: this screen is reached only
        // from inside the Organization Rewards Group sidebar section
        // now, so it must only ever offer ORG-type groups — no "System
        // Default" (that's DSG's own naming, not ORG's) and no DSG
        // groups like "My Way" mixed into the picker. Defaults to the
        // first ORG group alphabetically so the screen always opens on
        // real, editable data instead of an empty/invalid state.
        $groupLabels = DB::table('group_labels')->where('group_type', 'ORG')->orderBy('group_name')->get(['group_label_id', 'group_name']);

        $groupLabelId = $request->filled('group_label_id')
            ? $request->get('group_label_id')
            : ($groupLabels->first()->group_label_id ?? null);

        $effective = RoleLabelService::all($groupLabelId);
        $effectiveShort = RoleLabelService::shortOverrides($groupLabelId);
        $roles = ['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'];

        $rows = collect($roles)->map(fn ($role) => (object) [
            'role'        => $role,
            'label'       => $effective[$role],
            'short_label' => $effectiveShort[$role] ?? null,
        ]);

        // Passes the currently-selected ORG group so "Original Name"
        // shows Management/Operation/Staff-Affiliate — the real ORG
        // baseline — not the DSG wording.
        $defaults = RoleLabelService::defaults($groupLabelId);
        $maxLength = self::MAX_LABEL_LENGTH;
        $maxShortLength = self::MAX_SHORT_LABEL_LENGTH;
        // What each role's short badge would show if Chris leaves the
        // Short Label box blank — shown as a placeholder/hint only.
        $autoShort = [];
        foreach ($roles as $role) {
            $autoShort[$role] = RoleLabelService::autoShortLabel($role, self::MAX_SHORT_LABEL_LENGTH, $groupLabelId);
        }

        return view('admin.masterfile.org-category.index', compact('rows', 'defaults', 'maxLength', 'maxShortLength', 'autoShort', 'groupLabels', 'groupLabelId'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'group_label_id'            => ['nullable', 'exists:group_labels,group_label_id'],
            'labels'                  => ['required', 'array'],
            'labels.GROUP_LEADER'     => ['required', 'string', 'max:' . self::MAX_LABEL_LENGTH],
            'labels.TEAM_LEADER'      => ['required', 'string', 'max:' . self::MAX_LABEL_LENGTH],
            'labels.INTRODUCER'       => ['required', 'string', 'max:' . self::MAX_LABEL_LENGTH],
            'short_labels.GROUP_LEADER' => ['nullable', 'string', 'max:' . self::MAX_SHORT_LABEL_LENGTH],
            'short_labels.TEAM_LEADER'  => ['nullable', 'string', 'max:' . self::MAX_SHORT_LABEL_LENGTH],
            'short_labels.INTRODUCER'   => ['nullable', 'string', 'max:' . self::MAX_SHORT_LABEL_LENGTH],
        ], [
            'labels.GROUP_LEADER.max' => 'Group Leader label must be ' . self::MAX_LABEL_LENGTH . ' characters or fewer.',
            'labels.TEAM_LEADER.max'  => 'Team Leader label must be ' . self::MAX_LABEL_LENGTH . ' characters or fewer.',
            'labels.INTRODUCER.max'   => 'Introducer label must be ' . self::MAX_LABEL_LENGTH . ' characters or fewer.',
            'short_labels.*.max'      => 'Short label must be ' . self::MAX_SHORT_LABEL_LENGTH . ' characters or fewer.',
        ]);

        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        foreach (['GROUP_LEADER', 'TEAM_LEADER', 'INTRODUCER'] as $role) {
            $shortLabel = trim((string) $request->input("short_labels.$role"));
            $values = [
                'label'       => trim($request->input("labels.$role")),
                // Blank box = go back to the auto-derived short form.
                // Kept exactly as typed — Chris wants to control
                // upper/lower case himself, not have it forced.
                'short_label' => $shortLabel !== '' ? $shortLabel : null,
                'updated_at'  => now(),
            ];

            // where('group_label_id', $groupLabelId) correctly matches
            // the NULL (system default) row when $groupLabelId is null —
            // Laravel's query builder turns a 2-arg where() with a null
            // value into a whereNull() automatically.
            $exists = DB::table('role_label_overrides')->where('role', $role)->where('group_label_id', $groupLabelId)->exists();

            if ($exists) {
                DB::table('role_label_overrides')->where('role', $role)->where('group_label_id', $groupLabelId)->update($values);
            } else {
                DB::table('role_label_overrides')->insert(array_merge($values, [
                    'override_id'    => (string) Str::uuid(),
                    'role'           => $role,
                    'group_label_id' => $groupLabelId,
                    'created_at'     => now(),
                ]));
            }
        }

        RoleLabelService::forgetCache($groupLabelId);

        $backParams = $groupLabelId ? ['group_label_id' => $groupLabelId] : [];
        return redirect()->route('admin.masterfile.org-category', $backParams)->with('success', 'Organization Category labels updated successfully.');
    }

    public function reset(Request $request)
    {
        $groupLabelId = $request->filled('group_label_id') ? $request->input('group_label_id') : null;

        if ($groupLabelId) {
            // For a specific group, "reset" means stop overriding — go
            // back to inheriting whatever the system default is, rather
            // than hardcoding the literal default words onto this group.
            DB::table('role_label_overrides')->where('group_label_id', $groupLabelId)->delete();
        } else {
            foreach (RoleLabelService::defaults() as $role => $label) {
                DB::table('role_label_overrides')->where('role', $role)->whereNull('group_label_id')->update([
                    'label'       => $label,
                    'short_label' => null,
                    'updated_at'  => now(),
                ]);
            }
        }

        RoleLabelService::forgetCache($groupLabelId);

        $backParams = $groupLabelId ? ['group_label_id' => $groupLabelId] : [];
        $msg = $groupLabelId ? 'Restored to Management / Operation / Staff-Affiliate for this group.' : 'Restored to the original Group Leader / Team Leader / Introducer labels.';
        return redirect()->route('admin.masterfile.org-category', $backParams)->with('success', $msg);
    }
}
