<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\LanguageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// NEW 22 Jul 2026 — quick-switch language control (globe icon in the
// header), separate from the My Profile dropdown but writing to the
// exact same column so both stay in sync. OPENED UP 18 Aug 2026 — per
// Chris, every role can now use this (was TL/Introducer only) — see
// LanguageService::isEligible().
class LanguageController extends Controller
{
    public function quickSwitch(Request $request, LanguageService $languageService)
    {
        $agent = Auth::guard('agent')->user();

        if (!$languageService->isEligible($agent)) {
            abort(403, 'Language preference is not available for your role.');
        }

        $request->validate([
            'language' => 'required|in:' . implode(',', LanguageService::SUPPORTED),
        ]);

        DB::table('agents')->where('agent_id', $agent->agent_id)->update([
            'preferred_language' => $request->input('language'),
            'updated_at'         => now(),
        ]);

        return back()->with('success', 'Language updated to ' . $languageService->label($request->input('language')) . '.');
    }

    // NEW 18 Aug 2026 — guest version of quickSwitch(), for the login page.
    // No agent exists yet, so this just stores the choice in the session
    // under the same key SetAgentLocale checks for guests.
    public function guestSwitch(Request $request)
    {
        $request->validate([
            'language' => 'required|in:' . implode(',', LanguageService::SUPPORTED),
        ]);

        $request->session()->put('guest_language', $request->input('language'));

        return back();
    }
}
