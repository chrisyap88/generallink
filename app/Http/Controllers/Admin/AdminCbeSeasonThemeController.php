<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: automatic seasonal header styling
// (Christmas, Chinese New Year, etc) plus the auto-posted festival
// greeting notice — both driven by this admin-editable date-range
// catalog. Same landing > single-record edit pattern as the other CBE
// master files.
class AdminCbeSeasonThemeController extends Controller
{
    public function index()
    {
        $themes = DB::table('cbe_season_themes')->orderBy('sort_order')->paginate(10);

        return view('admin.cbe-season-themes.index', compact('themes'));
    }

    public function create()
    {
        return $this->edit(null);
    }

    public function edit(?string $id = null)
    {
        $theme = $id ? DB::table('cbe_season_themes')->where('id', $id)->first() : null;
        abort_if($id && ! $theme, 404);

        return view('admin.cbe-season-themes.edit', compact('theme'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $baseKey = strtoupper((string) Str::slug($data['label'], '_'));
        $key = $baseKey !== '' ? $baseKey : 'SEASON';
        $i = 1;
        while (DB::table('cbe_season_themes')->where('season_key', $key)->exists()) {
            $i++;
            $key = $baseKey.'_'.$i;
        }

        $nextSort = 1 + (int) DB::table('cbe_season_themes')->max('sort_order');

        DB::table('cbe_season_themes')->insert(array_merge($data, [
            'id' => (string) Str::uuid(),
            'season_key' => $key,
            'sort_order' => $nextSort,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return redirect()->route('admin.cbe-season-themes.index')->with('success', __('admin_cbe_season_themes.saved_note'));
    }

    public function update(Request $request, string $id)
    {
        $theme = DB::table('cbe_season_themes')->where('id', $id)->first();
        abort_if(! $theme, 404);

        $data = $this->validated($request);
        DB::table('cbe_season_themes')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));

        return redirect()->route('admin.cbe-season-themes.index')->with('success', __('admin_cbe_season_themes.saved_note'));
    }

    private function validated(Request $request): array
    {
        $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'start_month' => ['required', 'integer', 'min:1', 'max:12'],
            'start_day' => ['required', 'integer', 'min:1', 'max:31'],
            'end_month' => ['required', 'integer', 'min:1', 'max:12'],
            'end_day' => ['required', 'integer', 'min:1', 'max:31'],
            'bg_color' => ['required', 'string', 'max:20'],
            'accent_color' => ['required', 'string', 'max:20'],
            'greeting_text' => ['nullable', 'string', 'max:200'],
        ]);

        return [
            'label' => trim($request->input('label')),
            'start_month' => (int) $request->input('start_month'),
            'start_day' => (int) $request->input('start_day'),
            'end_month' => (int) $request->input('end_month'),
            'end_day' => (int) $request->input('end_day'),
            'bg_color' => $request->input('bg_color'),
            'accent_color' => $request->input('accent_color'),
            'greeting_text' => trim((string) $request->input('greeting_text')),
            'post_greeting_notice' => $request->boolean('post_greeting_notice'),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
