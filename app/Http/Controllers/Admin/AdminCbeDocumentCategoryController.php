<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris ("yes build all this for me" — Document
// Repository was one of the 7 approved secretarial gaps): document
// categories are an Admin-editable catalog, never hardcoded — same
// landing > single-record edit pattern as Ticket Categories/Notice
// Styles/Season Themes.
class AdminCbeDocumentCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('cbe_document_categories')->orderBy('sort_order')->paginate(10);

        return view('admin.cbe-document-categories.index', compact('categories'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $category = $id ? DB::table('cbe_document_categories')->where('id', $id)->first() : null;
        abort_if($id && ! $category, 404);

        return view('admin.cbe-document-categories.edit', compact('category'));
    }

    public function store(Request $request)
    {
        $request->validate(['label' => ['required', 'string', 'max:100']]);
        $label = trim($request->input('label'));

        $baseCode = strtoupper((string) Str::slug($label, '_'));
        $code = $baseCode !== '' ? $baseCode : 'CATEGORY';
        $i = 1;
        while (DB::table('cbe_document_categories')->where('code', $code)->exists()) {
            $i++;
            $code = $baseCode.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_document_categories')->max('sort_order');

        DB::table('cbe_document_categories')->insert([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'label' => $label,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-document-categories.index')->with('success', __('admin_cbe_document_categories.saved_note'));
    }

    public function update(Request $request, string $id)
    {
        $category = DB::table('cbe_document_categories')->where('id', $id)->first();
        abort_if(! $category, 404);

        $request->validate(['label' => ['required', 'string', 'max:100']]);

        DB::table('cbe_document_categories')->where('id', $id)->update([
            'label' => trim($request->input('label')),
            'is_active' => $category->is_system ? true : $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-document-categories.index')->with('success', __('admin_cbe_document_categories.saved_note'));
    }
}
