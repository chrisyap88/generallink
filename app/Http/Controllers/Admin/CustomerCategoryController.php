<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 19 Jul 2026 — per Chris: Customer Category (Individual, Family,
// SME, Corporate, Government, NGO/Charity, Association, Educational
// Institution, or anything else Admin adds) — a fully user-configurable
// optional tag, same convention as Customer Type/Status.
class CustomerCategoryController extends Controller
{
    // REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
    // records by default. Nothing is queried or shown until Chris
    // actually types something into the search box and submits.
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $categories = null;
        if ($q !== '') {
            $needle = '%'.$q.'%';
            $categories = DB::table('customer_categories')
                ->where(function ($qr) use ($needle) {
                    $qr->where('description', 'like', $needle)->orWhere('code', 'like', $needle);
                })
                ->orderBy('description')
                ->paginate(10)->withQueryString();
        }
        return view('masterfile.customer-categories', compact('categories'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $category = $id ? DB::table('customer_categories')->where('category_id', $id)->firstOrFail() : null;
        return view('masterfile.customer-category-edit', compact('category'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code'        => ['required', 'string', 'max:50', 'regex:/^[A-Z_]+$/', 'unique:customer_categories,code'],
            'description' => ['required', 'string', 'max:100'],
        ], ['code.regex' => 'Code must be UPPERCASE_WITH_UNDERSCORES only (e.g. SME).']);

        DB::table('customer_categories')->insert([
            'category_id' => (string) Str::uuid(),
            'code'        => strtoupper($request->code),
            'description' => $request->description,
            'is_active'   => true,
            'is_system'   => false,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-categories')->with('success', 'Customer Category created successfully.');
    }

    public function update(Request $request, string $id)
    {
        DB::table('customer_categories')->where('category_id', $id)->firstOrFail();

        $request->validate([
            'description' => ['required', 'string', 'max:100'],
            'is_active'   => ['required', 'in:0,1'],
        ]);

        DB::table('customer_categories')->where('category_id', $id)->update([
            'description' => $request->description,
            'is_active'   => $request->is_active,
            'updated_at'  => now(),
        ]);

        return redirect()->route('admin.masterfile.customer-categories')->with('success', 'Customer Category updated successfully.');
    }

    public function destroy(string $id)
    {
        DB::table('customer_categories')->where('category_id', $id)->firstOrFail();
        abort_if(DB::table('customers')->where('customer_category_id', $id)->exists(), 422, 'Cannot delete — customers are currently assigned this category.');

        DB::table('customer_categories')->where('category_id', $id)->delete();

        return redirect()->route('admin.masterfile.customer-categories')->with('success', 'Customer Category removed.');
    }
}
