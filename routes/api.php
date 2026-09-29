<?php

use App\Http\Controllers\EmailIngestionController;
use App\Http\Controllers\Shared\AiVoiceController;
use Illuminate\Support\Facades\Route;

// NEW 18 Jul 2026 — inbound-email webhook. No CSRF here on purpose (see
// bootstrap/app.php comment) — an external mail provider's webhook POST
// has no way to carry a Laravel CSRF token, and shouldn't need to.
// Protected instead by a shared-secret query token — see
// EmailIngestionController::inbound() and config/services.php.
// NEW 22 Jul 2026 — security hardening: this endpoint previously had no
// rate limit at all — even WITH a valid shared-secret token, nothing
// stopped a flood of requests. throttle:30,1 (per IP) is generous for
// a real mail provider's webhook traffic but stops abuse if the token
// ever leaks.
Route::post('/email-ingestion/inbound', [EmailIngestionController::class, 'inbound'])->name('api.email-ingestion.inbound')->middleware('throttle:30,1');

// NEW 3 Aug 2026 — voice-test.html's browsing/preview tool. A plain
// static file with no Laravel session/CSRF token at all, so it stays on
// the 'api' group (no CSRF check needed) — same reasoning as the
// email-ingestion webhook above. This lists/plays voices under the
// global .env ElevenLabs key only — it's a testing tool, never part of
// the real per-agent Carolyn conversation flow.
Route::get('/ai-assistant/voices', [AiVoiceController::class, 'voices'])->name('ai-assistant.voices')->middleware('throttle:15,1');
Route::post('/ai-assistant/preview-speak', [AiVoiceController::class, 'previewSpeak'])->name('ai-assistant.preview-speak')->middleware('throttle:20,1');
Route::post('/ai-assistant/set-default-voice', [AiVoiceController::class, 'setDefault'])->name('ai-assistant.set-default-voice')->middleware('throttle:10,1');

// NEW 5 Aug 2026 — Outbound Partner API, Phase 1. Outside systems
// (insurance vendor, hotel PMS, customer ERP/POS) authenticate with a
// GeneralLink-issued key via the partner.auth middleware (checks
// Authorization: Bearer <key> against app/Http/Middleware/PartnerApiAuth.php),
// not a Laravel session — this correctly belongs on the 'api' group,
// unlike the Carolyn /speak endpoint above. Read-only for now; see
// PartnerApiKeyController for how Admin issues/revokes keys.
Route::prefix('partner/v1')->name('partner.')->middleware('throttle:60,1')->group(function () {
    Route::get('/policies/{policyNumber}', [\App\Http\Controllers\PartnerApi\PolicyLookupController::class, 'show'])
        ->middleware('partner.auth:policy.read')
        ->name('policies.show');
});

// NOTE 3 Aug 2026 — the REAL Carolyn /ai-assistant/speak endpoint moved
// to routes/web.php. Reason: it now needs to know WHICH agent is logged
// in (per-agent voice providers), which requires a real session — the
// 'api' group here deliberately has no session/cookie middleware at all,
// so Auth::guard('agent')->user() would always resolve to null here even
// for a logged-in agent. web.php already carries a session + CSRF token
// via the page's <meta name="csrf-token">, same as the existing chat
// endpoints, so that's where agent-aware voice calls belong.
