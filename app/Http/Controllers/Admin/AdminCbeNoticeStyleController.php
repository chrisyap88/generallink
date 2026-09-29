<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "all is automatic as by default from the
// system unless the admin want to turn off or edit or add." Admin-
// editable catalog behind the CBE Notice Board's content-aware styling
// (Security Alert, Scam Warning, Celebration, Birthday, Promo, etc) —
// same landing > single-record edit pattern as Faith Types/Practitioner
// Types, minus the typeahead search (this list stays short).
class AdminCbeNoticeStyleController extends Controller
{
    public function index()
    {
        $styles = DB::table('cbe_notice_styles')->orderBy('sort_order')->paginate(10);

        return view('admin.cbe-notice-styles.index', compact('styles'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $style = $id ? DB::table('cbe_notice_styles')->where('id', $id)->first() : null;
        abort_if($id && ! $style, 404);

        return view('admin.cbe-notice-styles.edit', compact('style'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $baseKey = strtoupper((string) Str::slug($data['label'], '_'));
        $key = $baseKey !== '' ? $baseKey : 'STYLE';
        $i = 1;
        while (DB::table('cbe_notice_styles')->where('style_key', $key)->exists()) {
            $i++;
            $key = $baseKey.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_notice_styles')->max('sort_order');

        DB::table('cbe_notice_styles')->insert(array_merge($data, [
            'id' => (string) Str::uuid(),
            'style_key' => $key,
            'is_system' => false,
            'sort_order' => $nextSort,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return redirect()->route('admin.cbe-notice-styles.index')->with('success', __('admin_cbe_notice_styles.saved_note'));
    }

    public function update(Request $request, string $id)
    {
        $style = DB::table('cbe_notice_styles')->where('id', $id)->first();
        abort_if(! $style, 404);

        $data = $this->validated($request);
        DB::table('cbe_notice_styles')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

        return redirect()->route('admin.cbe-notice-styles.index')->with('success', __('admin_cbe_notice_styles.saved_note'));
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'icon_key' => ['nullable', 'string', 'max:30'],
            'bg_color' => ['required', 'string', 'max:20'],
            'accent_color' => ['required', 'string', 'max:20'],
            'text_color' => ['required', 'string', 'max:20'],
            'keywords' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            'label' => trim($request->input('label')),
            'icon_key' => $request->input('icon_key', 'bell'),
            'bg_color' => $request->input('bg_color'),
            'accent_color' => $request->input('accent_color'),
            'text_color' => $request->input('text_color'),
            'keywords' => trim((string) $request->input('keywords')),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
