<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;

// NEW 22 Jul 2026 — per Chris: TL/Introducer logins see their chosen
// screen language (English/Chinese/Malay); GL/Admin always see
// English. This is the one place that actually flips Laravel's active
// locale, based on LanguageService::effectiveLanguage() — every view
// in the app can now use __('file.key') and it will automatically
// come out in the right language for an eligible agent, with zero
// per-screen logic. Registered globally on the 'web' middleware group
// (see bootstrap/app.php) since agent route groups are split across
// several separate ->middleware('auth:agent') blocks in routes/web.php
// — safer to catch every one of them here than to remember to add it
// to each.
class SetAgentLocale
{
    private const LOCALE_MAP = ['EN' => 'en', 'ZH' => 'zh', 'MS' => 'ms'];

    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('agent')->check()) {
            $agent = Auth::guard('agent')->user();
            $effective = app(LanguageService::class)->effectiveLanguage($agent);
            App::setLocale(self::LOCALE_MAP[$effective] ?? 'en');
        } elseif ($request->session()->has('guest_language')) {
            // NEW 18 Aug 2026 — guest fallback (login page, before any
            // agent session exists). Set via LanguageController::guestSwitch().
            $guest = $request->session()->get('guest_language');
            App::setLocale(self::LOCALE_MAP[$guest] ?? 'en');
        }

        return $next($request);
    }
}
