<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 15 Sep 2026 — per Chris: the appointment booking module needs an
// Admin-editable catalog of practitioner types (Sensei, Consultant,
// Legal Advisor, etc.), never hardcoded — same landing (Add New /
// Search & View/Edit) > search (typeahead) > single-record edit
// pattern as every other master file in the app (Committee Positions,
// Appointment Terminology Types, Reason Code).
class AdminPractitionerTypeController extends Controller
{
    public function index(Request $request)
    {
        $hasAnyFilter = $request->has('search');
        $types = null;

        if ($hasAnyFilter) {
            $query = DB::table('cbe_practitioner_types');
            if ($request->filled('search')) {
                // CHANGED 24 Sep 2026 -- per Chris: "add search all
                // criteria" -- matches on Practitioner Type OR its
                // internal Code, same multi-column OR-search pattern
                // used for Group Name / Appointment Position Types.
                $s = '%' . $request->search . '%';
                $field = $request->get('field', '');
                $allowedFields = ['type_label', 'code'];
                $query->where(function ($sub) use ($s, $field, $allowedFields) {
                    if (in_array($field, $allowedFields, true)) {
                        $sub->where($field, 'like', $s);
                    } else {
                        $sub->where('type_label', 'like', $s)
                            ->orWhere('code', 'like', $s);
                    }
                });
            }
            $types = $query->orderBy('sort_order')->paginate(10)->withQueryString();
        }

        return view('admin.practitioner-types.index', compact('hasAnyFilter', 'types'));
    }

    public function searchForm()
    {
        return view('admin.practitioner-types.search-form');
    }

    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // CHANGED 24 Sep 2026 -- per Chris: "add search all criteria" --
        // the dropdown assist now finds a type by Code too, not just
        // Practitioner Type name.
        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['type_label', 'code'];

        $s = '%' . $q . '%';
        $results = DB::table('cbe_practitioner_types')
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('type_label', 'like', $s)
                        ->orWhere('code', 'like', $s);
                }
            })
            ->orderBy('type_label')
            ->limit(15)
            ->get(['id', 'type_label']);

        return response()->json($results);
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $type = $id ? DB::table('cbe_practitioner_types')->where('id', $id)->first() : null;
        abort_if($id && ! $type, 404);

        return view('admin.practitioner-types.edit', compact('type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'type_label' => ['required', 'string', 'max:150'],
        ]);

        $baseCode = strtoupper((string) Str::slug($request->type_label, '_'));
        $code = $baseCode !== '' ? $baseCode : 'PRACTITIONER';
        $i = 1;
        while (DB::table('cbe_practitioner_types')->where('code', $code)->exists()) {
            $i++;
            $code = $baseCode.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_practitioner_types')->max('sort_order');

        DB::table('cbe_practitioner_types')->insert([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'type_label' => trim($request->type_label),
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.practitioner-types.index')->with('practitioner_type_saved', true);
    }

    public function update(Request $request, string $id)
    {
        $type = DB::table('cbe_practitioner_types')->where('id', $id)->first();
        abort_if(! $type, 404);

        $request->validate([
            'type_label' => ['required', 'string', 'max:150'],
        ]);

        DB::table('cbe_practitioner_types')->where('id', $id)->update([
            'type_label' => trim($request->type_label),
            // The 3 starter types (is_system = true) can never be
            // switched off — same rule as the other master files.
            'is_active' => $type->is_system ? true : $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.practitioner-types.index')->with('practitioner_type_saved', true);
    }
}
