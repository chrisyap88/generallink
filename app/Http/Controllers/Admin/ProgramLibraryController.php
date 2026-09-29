<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 12 Sep 2026 (Task #416) — per Chris: "the paid or free is wrong...
// NOT the entire CBE is free or paid... you must have another master
// file... a program library master file that you retrieve for me from
// the entire system... i have a checkbox to tick the crown logo, if i
// click crown logo means paid... must always update because we may have
// future new develop program that i need to flag again... you must have
// the unlock program if the user willing to subscription the paid
// version." Two screens in this one controller, same spirit as
// AdminGladeTierController (catalog + community-level action in one
// place):
//   1. index() — the Program Library itself: every program scanned out
//      of the app's real sidebars (see program_catalog migration),
//      Admin ticks a checkbox to flag it Paid (crown) or Free.
//   2. unlocks()/unlock()/lock() — per-community manual unlock of a
//      specific Paid program, since no payment gateway exists yet
//      (Chris confirmed: manual Admin toggle for now, not real billing).
class ProgramLibraryController extends Controller
{
    public function index(Request $request)
    {
        $portal = $request->get('portal', '');
        $search = trim((string) $request->get('search', ''));

        $query = DB::table('program_catalog');
        if ($portal !== '') {
            $query->where('portal', $portal);
        }
        if ($search !== '') {
            $query->where('label', 'like', '%' . $search . '%');
        }

        $programs = $query->orderBy('portal')->orderBy('section')->orderBy('label')->get();
        $portals = DB::table('program_catalog')->distinct()->orderBy('portal')->pluck('portal');

        return view('masterfile.program-library', compact('programs', 'portals', 'portal', 'search'));
    }

    // Flips one program's Paid/Free flag. Plain toggle, not a form post
    // per row, so ticking the crown feels instant on a 192-row list.
    public function toggle(Request $request, string $id)
    {
        $program = DB::table('program_catalog')->where('id', $id)->first();
        abort_if(! $program, 404);

        DB::table('program_catalog')->where('id', $id)->update([
            'is_paid' => ! $program->is_paid,
            'updated_at' => now(),
        ]);

        return back();
    }

    // NEW 13 Sep 2026 — per Chris: "you should have select all so that i
    // no need to tick one by one and even i select All, i still can
    // untick for those not relevant." Replaces the old per-row toggle()
    // with one bulk save: all_ids[] is every program that was visible
    // under the current portal/search filter when the page loaded, and
    // paid_ids[] is whichever of those the admin left checked. Anything
    // in all_ids[] but NOT in paid_ids[] gets set back to Free — programs
    // outside the current filter are never touched.
    public function bulkUpdate(Request $request)
    {
        $allIds = $request->input('all_ids', []);
        $paidIds = collect($request->input('paid_ids', []))->map(fn ($id) => (string) $id)->all();

        foreach ($allIds as $id) {
            DB::table('program_catalog')->where('id', $id)->update([
                'is_paid' => in_array((string) $id, $paidIds, true),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.masterfile.program-library', [
            'portal' => $request->input('portal', ''),
            'search' => $request->input('search', ''),
        ]);
    }

    // NEW 12 Sep 2026 — per-community unlock screen. Chris picks a
    // community (same typeahead-style search already used for Group
    // Name Maintenance elsewhere), sees only the PAID programs, and
    // ticks which ones that community is unlocked/subscribed for. A
    // community with no row in program_unlocks for a given paid program
    // stays locked (crown shown, no access) — this screen is the only
    // way to unlock one, since there is no payment checkout yet.
    public function unlocks(Request $request)
    {
        $groupId = $request->get('group');
        $group = $groupId ? DB::table('group_labels')->where('group_label_id', $groupId)->first() : null;

        $groupResults = collect();
        $search = trim((string) $request->get('group_search', ''));
        if ($search !== '' && ! $group) {
            $groupResults = DB::table('group_labels')
                ->where('group_name', 'like', '%' . $search . '%')
                ->orderBy('group_name')
                ->limit(10)
                ->get(['group_label_id', 'group_name', 'group_type']);
        }

        $paidPrograms = collect();
        $unlockedIds = collect();
        if ($group) {
            $paidPrograms = DB::table('program_catalog')
                ->where('is_paid', true)
                ->orderBy('portal')->orderBy('section')->orderBy('label')
                ->get();

            $unlockedIds = DB::table('program_unlocks')
                ->where('group_label_id', $group->group_label_id)
                ->pluck('program_id');
        }

        return view('masterfile.program-unlocks', compact('group', 'groupResults', 'search', 'paidPrograms', 'unlockedIds'));
    }

    public function unlock(Request $request, string $groupId, string $programId)
    {
        $group = DB::table('group_labels')->where('group_label_id', $groupId)->first();
        $program = DB::table('program_catalog')->where('id', $programId)->where('is_paid', true)->first();
        abort_if(! $group || ! $program, 404);

        $existing = DB::table('program_unlocks')
            ->where('group_label_id', $groupId)
            ->where('program_id', $programId)
            ->first();

        if (! $existing) {
            DB::table('program_unlocks')->insert([
                'id' => (string) Str::uuid(),
                'group_label_id' => $groupId,
                'program_id' => $programId,
                'unlocked_by' => auth('agent')->id(),
                'unlocked_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect()->route('admin.masterfile.program-unlocks', ['group' => $groupId]);
    }

    public function lock(Request $request, string $groupId, string $programId)
    {
        DB::table('program_unlocks')
            ->where('group_label_id', $groupId)
            ->where('program_id', $programId)
            ->delete();

        return redirect()->route('admin.masterfile.program-unlocks', ['group' => $groupId]);
    }
}
