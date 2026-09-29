<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReasonCodeController extends Controller
{
    public function index(Request $request)
    {
        $hasAnyFilter = $request->has('search') || $request->filled('category');
        $reasonCodes = null;

        if ($hasAnyFilter) {
            $query = DB::table('reason_codes');
            if ($request->filled('search')) {
                $s = '%' . $request->search . '%';
                $field = $request->get('field', '');
                $allowedFields = ['description', 'code'];
                $query->where(function ($q) use ($s, $field, $allowedFields) {
                    if (in_array($field, $allowedFields, true)) {
                        $q->where($field, 'like', $s);
                    } else {
                        $q->where('description', 'like', $s)
                          ->orWhere('code', 'like', $s);
                    }
                });
            }
            if ($request->filled('category')) {
                $query->where('category', $request->category);
            }
            $reasonCodes = $query->orderBy('category')->orderBy('description')->paginate(10)->withQueryString();
        }

        $categories = DB::table('reason_codes')->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('masterfile.reason-codes', compact('hasAnyFilter', 'reasonCodes', 'categories'));
    }

    public function searchForm()
    {
        $categories = DB::table('reason_codes')->select('category')->distinct()->orderBy('category')->pluck('category');
        return view('masterfile.reason-code-search-form', compact('categories'));
    }

    public function typeahead(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 1) {
            return response()->json([]);
        }

        // CHANGED 24 Sep 2026 -- per Chris: "add search all criteria" --
        // now matches Code too, same as the full search-list (index)
        // already does.
        // NEW 25 Sep 2026 -- per Chris: every search screen must offer
        // an explicit "Search All" choice, listed first, followed by
        // each specific field -- not just a silently-merged OR match.
        $field = $request->get('field', '');
        $allowedFields = ['description', 'code'];

        $s = '%' . $q . '%';
        $results = DB::table('reason_codes')
            ->where(function ($sub) use ($s, $field, $allowedFields) {
                if (in_array($field, $allowedFields, true)) {
                    $sub->where($field, 'like', $s);
                } else {
                    $sub->where('description', 'like', $s)
                        ->orWhere('code', 'like', $s);
                }
            })
            ->orderBy('description')
            ->limit(15)
            ->get(['reason_code_id', 'description', 'category']);

        return response()->json($results);
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $reasonCode = $id ? DB::table('reason_codes')->where('reason_code_id', $id)->first() : null;
        $categories = DB::table('reason_codes')->select('category')->distinct()->orderBy('category')->pluck('category');

        return view('masterfile.reason-code-edit', compact('reasonCode', 'categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category'    => ['required', 'string', 'max:50'],
            'code'        => ['required', 'string', 'max:50', 'regex:/^[A-Z_]+$/', 'unique:reason_codes,code'],
            'description' => ['required', 'string', 'max:200'],
        ], [
            'code.regex' => 'Code must be UPPERCASE_WITH_UNDERSCORES only (e.g. DUPLICATE_ENTRY).',
        ]);

        DB::table('reason_codes')->insert([
            'reason_code_id' => (string) Str::uuid(),
            'category'       => strtoupper($request->category),
            'code'           => strtoupper($request->code),
            'description'    => $request->description,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return redirect()->route('admin.masterfile.reason-codes')->with('success', 'Reason Code created successfully.');
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'category'    => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:200'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        DB::table('reason_codes')->where('reason_code_id', $id)->update([
            'category'    => strtoupper($request->category),
            'description' => $request->description,
            'is_active'   => $request->is_active,
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.reason-codes')->with('success', 'Reason Code updated successfully.');
    }
}
