<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\ElevenLabsService;
use App\Services\LanguageService;
use App\Services\Tts\TtsProviderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

// -------------------------------------------------------
// NEW 3 Aug 2026 — real ElevenLabs voices for Carolyn (the AI
// Assistant), plus the voice-test.html tester page. Both endpoints are
// public (no login needed) since Carolyn's voice greets people on the
// login page too — but both are throttled to protect Chris's ElevenLabs
// character quota from being burned by repeated/automated calls.
// -------------------------------------------------------
class AiVoiceController extends Controller
{
    public function voices(ElevenLabsService $service)
    {
        $result = $service->listVoices();
        return response()->json($result);
    }

    // NEW 3 Aug 2026 — per-agent Voice Assistant providers. Chris's
    // explicit instruction: voice usage is NEVER shared or billed to his
    // own account. The rule is now simple and absolute —
    //   - Guest (not logged in): ALWAYS the free browser voice. There is
    //     no agent identity yet, so there is no legitimate account to
    //     charge — this endpoint doesn't even attempt a provider call.
    //   - Logged-in agent with their own provider connected (Profile ->
    //     Voice Assistant): uses THEIR key, THEIR credits.
    //   - Logged-in agent with nothing connected: free browser voice —
    //     never a silent fallback to anyone else's key.
    // In every "no provider" case this returns an ERROR-shaped response,
    // which the existing widget JS already falls back to the browser
    // voice for automatically — no frontend change needed for that path.
    public function speak(Request $request, TtsProviderResolver $resolver, LanguageService $languageService)
    {
        $request->validate([
            'text' => 'required|string|max:600',
            'voice_id' => 'nullable|string|max:150', // client-pinned voice for THIS conversation, if already resolved once
        ]);

        $agent = Auth::guard('agent')->user();
        $resolved = $resolver->resolve($agent);
        $status = $resolved['status'];

        // NEW 6 Aug 2026 — per Chris: Carolyn's Mandarin/Bahasa replies were
        // being spoken with an English accent because nothing here ever
        // told the TTS provider what language it was actually reading —
        // see LanguageService, ElevenLabsService::speak(), GoogleTtsProvider.
        // Guests always get 'en' (LanguageService::effectiveLanguage needs
        // a real agent row; there's no language preference before login).
        $languageCode = $agent ? $languageService->iso639($languageService->effectiveLanguage($agent)) : 'en';

        Log::info('[Carolyn] /speak request', [
            'mode' => $agent ? 'agent' : 'guest',
            'agent_id' => $agent->agent_id ?? null,
            'resolver_status' => $status,
            'pinned_voice_id_from_client' => $request->input('voice_id'),
            'language_code' => $languageCode,
            'text_preview' => mb_substr($request->input('text'), 0, 60),
        ]);

        if ($status !== 'READY') {
            // Guest, or agent with no usable provider right now — never a
            // paid call, never anyone else's key. The widget's own
            // fallback handles this exactly like any other TTS failure.
            // Each status gets its own plain-language cause + fix so the
            // agent knows exactly what to do next (per Chris's standing
            // rule: every message must explain cause, fix, and alternative).
            $label = $resolved['providerLabel'] ?? 'your provider';
            $message = match ($status) {
                'NOT_CONNECTED' => "You picked {$label} for Voice Assistant, but it isn't connected yet. Go to Integration Hub, paste your {$label} key, and click Test Connection — using the built-in voice for now.",
                'LOCKED' => "Your Integration Hub is locked this session, so GeneralLink can't read your {$label} key. Unlock the Hub with your Hub password to use your own voice — using the built-in voice for now.",
                'UNREADABLE' => "Your saved {$label} key couldn't be read back (it may have been saved under an old Hub password). Reconnect it in Integration Hub — using the built-in voice for now.",
                default => $agent
                    ? 'Connect a voice provider in Integration Hub, then pick it in My Profile > Voice Assistant, to hear your own AI voice — using the built-in voice for now.'
                    : 'Using the built-in voice until you log in.',
            };
            return response()->json(['status' => 'ERROR', 'message' => $message], 200);
        }

        // Client-pinned voice for this conversation takes priority over
        // the agent's saved default, same pinning discipline as before —
        // it just now always belongs to THIS agent's own account.
        $voiceId = $request->input('voice_id') ?: $resolved['voiceId'];

        $result = $resolved['provider']->speak($request->input('text'), $resolved['apiKey'], $voiceId, $languageCode);

        if ($result['status'] !== 'OK') {
            return response()->json($result, 200);
        }

        return response($result['audio'], 200)
            ->header('Content-Type', 'audio/mpeg')
            ->header('X-Voice-Id', $result['voice_id']);
    }

    // NEW 3 Aug 2026 — voice-test.html's preview/browsing tool only. Uses
    // the global .env ElevenLabs key exactly like the old /speak endpoint
    // used to (before per-agent providers existed) — this is a testing
    // page for trying out ElevenLabs voices, not part of the real Carolyn
    // conversation flow, so it deliberately does NOT go through
    // TtsProviderResolver/per-agent logic.
    public function previewSpeak(Request $request, ElevenLabsService $service)
    {
        $request->validate([
            'text' => 'required|string|max:600',
            'voice_id' => 'nullable|string|max:150',
            'speed' => 'nullable|numeric|min:0.7|max:1.2',
        ]);

        $result = $service->speak(
            $request->input('text'),
            $request->input('voice_id'),
            $request->input('speed') !== null ? (float) $request->input('speed') : null
        );

        if ($result['status'] !== 'OK') {
            return response()->json($result, 200);
        }

        return response($result['audio'], 200)
            ->header('Content-Type', 'audio/mpeg')
            ->header('X-Voice-Id', $result['voice_id']);
    }

    // NEW 3 Aug 2026 (fix) — persists Chris's chosen voice server-side
    // (see ElevenLabsService::setPersistedVoice) instead of only in one
    // browser's localStorage, so it's a single setting that applies
    // everywhere the app is opened from.
    public function setDefault(Request $request, ElevenLabsService $service)
    {
        $request->validate([
            'voice_id' => 'required|string|max:100',
            'speed' => 'nullable|numeric|min:0.7|max:1.2',
        ]);

        $service->setPersistedVoice(
            $request->input('voice_id'),
            $request->input('speed') !== null ? (float) $request->input('speed') : null
        );

        return response()->json(['status' => 'OK']);
    }
}
