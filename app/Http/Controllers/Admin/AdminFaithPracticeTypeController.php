<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 11 Sep 2026 — per Chris: "Faith Practice Type" was hardcoded to 5
// fixed choices — "why is only offer this few type? what if new type
// introduce? you should not hard code" (same rule he gave for GLADE
// Membership Tiers). This screen lets Admin add, edit, and activate/
// deactivate wording sets in cbe_faith_practice_types at any time — no
// code change or deployment ever needed for a new one.
//
// Each "type" here is really a wording set: which words a CBE group's
// Appointments tab uses (tab name, practitioner title, duty name, log
// form title). It never stores or implies a group's actual religion.
//
// REBUILT 14 Sep 2026 — per Chris: "confusing, you just display search
// bar first with type ahead or search all, choose edit or add new...
// can you follow the other programs like product or vendor master
// file... example debtor, creditor, chart account transaction type."
// Was a single screen listing every row with its own Save button
// (confusing wall of duplicate buttons). Rebuilt to match the same
// landing > search (typeahead) > single-record edit pattern already
// used everywhere else in Master File Maintenance (Reason Code, Group
// Name & Hierarchy Levels): one record, one edit screen, one Save.
class AdminFaithPracticeTypeController extends Controller
{
    public function index(Request $request)
    {
        $hasAnyFilter = $request->has('search');
        $types = null;

        if ($hasAnyFilter) {
            $query = DB::table('cbe_faith_practice_types');
            if ($request->filled('search')) {
                // CHANGED 24 Sep 2026 -- per Chris: "add search all
                // criteria" -- matches on ANY of this position's editable
                // wording fields (not just Position Name), same
                // multi-column OR-search pattern used for Group Name.
                $s = '%' . $request->search . '%';
                $field = $request->get('field', '');
                $allowedFields = ['option_label', 'tab_label', 'practitioner_label', 'duty_label', 'log_form_title', 'reason_options'];
                $query->where(function ($sub) use ($s, $field, $allowedFields) {
                    if (in_array($field, $allowedFields, true)) {
                        $sub->where($field, 'like', $s);
                    } else {
                        $sub->where('option_label', 'like', $s)
                            ->orWhere('tab_label', 'like', $s)
                            ->orWhere('practitioner_label', 'like', $s)
                            ->orWhere('duty_label', 'like', $s)
                            ->orWhere('log_form_title', 'like', $s)
                            ->orWhere('reason_options', 'like', $s);
                    }
                });
            }
            $types = $query->orderBy('sort_order')->paginate(10)->withQueryString();
        }

        return view('admin.faith-practice-types.index', compact('hasAnyFilter', 'types'));
    }

    public function searchForm()
    {
        return view('admin.faith-practice-types.search-form');
    }

    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // CHANGED 24 Sep 2026 -- per Chris: "add search all criteria" --
        // the dropdown assist now finds a position by ANY of its
        // wording fields too, not just Position Name (e.g. typing
        // "Legal" now finds the "Legal Advisor" position even if the
        // match is really in its Practitioner Title or Duty Name).
        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['option_label', 'tab_label', 'practitioner_label', 'duty_label', 'log_form_title', 'reason_options'];

        $s = '%' . $q . '%';
        $results = DB::table('cbe_faith_practice_types')
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('option_label', 'like', $s)
                        ->orWhere('tab_label', 'like', $s)
                        ->orWhere('practitioner_label', 'like', $s)
                        ->orWhere('duty_label', 'like', $s)
                        ->orWhere('log_form_title', 'like', $s)
                        ->orWhere('reason_options', 'like', $s);
                }
            })
            ->orderBy('option_label')
            ->limit(15)
            ->get(['id', 'option_label', 'tab_label']);

        return response()->json($results);
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $type = $id ? DB::table('cbe_faith_practice_types')->where('id', $id)->first() : null;
        abort_if($id && ! $type, 404);

        return view('admin.faith-practice-types.edit', compact('type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'option_label' => ['required', 'string', 'max:150'],
            'tab_label' => ['required', 'string', 'max:100'],
            'practitioner_label' => ['required', 'string', 'max:100'],
            'duty_label' => ['required', 'string', 'max:100'],
            'log_form_title' => ['required', 'string', 'max:150'],
            // NEW 12 Sep 2026 — per Chris: each position now needs its OWN
            // list of appointment reasons (a temple's "Prayer, Blessing"
            // makes no sense for a Legal Advisor) — free text, admin
            // typed, comma-separated, exactly like Hierarchy Level Names.
            'reason_options' => ['required', 'string', 'max:1000'],
        ]);

        // code is an internal stable identifier (used as the value stored
        // on group_labels.faith_practice_type) — auto-derived from the
        // option label, uniqued the same way GLADE tier codes are.
        $baseCode = strtoupper((string) Str::slug($request->option_label, '_'));
        $code = $baseCode !== '' ? $baseCode : 'TYPE';
        $i = 1;
        while (DB::table('cbe_faith_practice_types')->where('code', $code)->exists()) {
            $i++;
            $code = $baseCode.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_faith_practice_types')->max('sort_order');

        DB::table('cbe_faith_practice_types')->insert([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'option_label' => trim($request->option_label),
            'tab_label' => trim($request->tab_label),
            'practitioner_label' => trim($request->practitioner_label),
            'duty_label' => trim($request->duty_label),
            'log_form_title' => trim($request->log_form_title),
            'reason_options' => self::cleanReasons($request->reason_options),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.faith-practice-types.index')->with('cbe_faith_type_saved', true);
    }

    public function update(Request $request, string $id)
    {
        $type = DB::table('cbe_faith_practice_types')->where('id', $id)->first();
        abort_if(! $type, 404);

        $request->validate([
            'option_label' => ['required', 'string', 'max:150'],
            'tab_label' => ['required', 'string', 'max:100'],
            'practitioner_label' => ['required', 'string', 'max:100'],
            'duty_label' => ['required', 'string', 'max:100'],
            'log_form_title' => ['required', 'string', 'max:150'],
            'reason_options' => ['required', 'string', 'max:1000'],
        ]);

        DB::table('cbe_faith_practice_types')->where('id', $id)->update([
            'option_label' => trim($request->option_label),
            'tab_label' => trim($request->tab_label),
            'practitioner_label' => trim($request->practitioner_label),
            'duty_label' => trim($request->duty_label),
            'log_form_title' => trim($request->log_form_title),
            'reason_options' => self::cleanReasons($request->reason_options),
            // The 5 original wording sets (is_system = true) can never be
            // switched off — CBE groups already saved with NONE, TAOIST,
            // etc. must always resolve to something. Only Admin-added
            // ones can be deactivated.
            'is_active' => $type->is_system ? true : $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.faith-practice-types.index')->with('cbe_faith_type_saved', true);
    }

    // Normalizes free-typed "Prayer, Counseling,, Blessing" into a clean
    // "Prayer,Counseling,Blessing" — trims each entry, drops empties,
    // exactly the same parsing rule used for Hierarchy Level Names.
    private static function cleanReasons(string $raw): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), fn ($s) => $s !== ''));

        return implode(',', $parts);
    }
}
