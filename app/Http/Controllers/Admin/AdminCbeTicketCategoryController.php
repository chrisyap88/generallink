<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris's own answer: CBE Support Ticket
// categories are an Admin-editable catalog, never hardcoded — same
// landing > single-record edit pattern as Faith Types/Practitioner
// Types/Notice Styles.
class AdminCbeTicketCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('cbe_ticket_categories')->orderBy('sort_order')->paginate(10);

        return view('admin.cbe-ticket-categories.index', compact('categories'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $category = $id ? DB::table('cbe_ticket_categories')->where('id', $id)->first() : null;
        abort_if($id && ! $category, 404);

        return view('admin.cbe-ticket-categories.edit', compact('category'));
    }

    public function store(Request $request)
    {
        $request->validate(['label' => ['required', 'string', 'max:100']]);
        $label = trim($request->input('label'));

        $baseCode = strtoupper((string) Str::slug($label, '_'));
        $code = $baseCode !== '' ? $baseCode : 'CATEGORY';
        $i = 1;
        while (DB::table('cbe_ticket_categories')->where('code', $code)->exists()) {
            $i++;
            $code = $baseCode.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_ticket_categories')->max('sort_order');

        DB::table('cbe_ticket_categories')->insert([
            'id' => (string) Str::uuid(),
            'code' => $code,
            'label' => $label,
            'is_system' => false,
            'is_active' => true,
            'sort_order' => $nextSort,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-ticket-categories.index')->with('success', __('admin_cbe_ticket_categories.saved_note'));
    }

    public function update(Request $request, string $id)
    {
        $category = DB::table('cbe_ticket_categories')->where('id', $id)->first();
        abort_if(! $category, 404);

        $request->validate(['label' => ['required', 'string', 'max:100']]);

        DB::table('cbe_ticket_categories')->where('id', $id)->update([
            'label' => trim($request->input('label')),
            'is_active' => $category->is_system ? true : $request->boolean('is_active'),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.cbe-ticket-categories.index')->with('success', __('admin_cbe_ticket_categories.saved_note'));
    }
}
