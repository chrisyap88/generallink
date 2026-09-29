<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// -------------------------------------------------------
// NEW 3 Aug 2026 — real text-to-speech for Carolyn (the AI Assistant)
// via ElevenLabs, so she can speak with an actual Malaysian-accent
// female voice instead of the generic browser voice. Chris created his
// own ElevenLabs account and API key (stored in .env as
// ELEVENLABS_API_KEY) — this service just calls their API with it.
//
// FIX 3 Aug 2026 — Chris's chosen voice (picked on voice-test.html) was
// only ever saved in that ONE browser's localStorage, so it silently
// didn't apply if the app was opened via a different address (localhost
// vs 127.0.0.1 are different origins) or a different browser. The
// chosen voice is now persisted server-side (a small JSON file in
// storage/app/), so it's a single app-wide setting that always applies
// no matter how the site is accessed.
//
// If ElevenLabs is unavailable (no key, quota used up, network issue),
// callers should fall back to the browser's built-in speech — see
// aiAssistantSpeak() in ai-assistant-widget.blade.php, which does this
// automatically on any error response from this service.
// -------------------------------------------------------
class ElevenLabsService
{
    private const BASE_URL = 'https://api.elevenlabs.io/v1';

    // Preferred voice names, in order, ONLY used if Chris hasn't picked
    // one via the voice-test.html page yet — these are ElevenLabs' own
    // Malaysian-accent library voices as of Aug 2026.
    private const PREFERRED_VOICE_NAMES = ['Athira', 'Aisyah', 'Syalala', 'Shazrina'];

    private function voiceSettingsPath(): string
    {
        return storage_path('app/carolyn-voice.json');
    }

    /**
     * The voice Chris explicitly picked and saved on voice-test.html, if
     * any. This is the single source of truth — a server-side setting,
     * not tied to any one browser.
     *
     * @return array{voice_id:string, speed:?float}|null
     */
    public function getPersistedVoice(): ?array
    {
        $path = $this->voiceSettingsPath();
        if (!file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data) || empty($data['voice_id'])) {
            return null;
        }
        return ['voice_id' => $data['voice_id'], 'speed' => $data['speed'] ?? null];
    }

    public function setPersistedVoice(string $voiceId, ?float $speed = null): void
    {
        file_put_contents($this->voiceSettingsPath(), json_encode([
            'voice_id' => $voiceId,
            'speed' => $speed,
            'saved_at' => now()->toDateTimeString(),
        ]));
    }

    /**
     * @param ?string $apiKeyOverride NEW 3 Aug 2026 — list voices under a
     *   specific agent's own connected key instead of the global default.
     * @return array{status:string, voices?:array, message?:string}
     */
    public function listVoices(?string $apiKeyOverride = null): array
    {
        $apiKey = $apiKeyOverride ?: config('services.elevenlabs.key');
        if (empty($apiKey)) {
            return ['status' => 'ERROR', 'message' => 'ElevenLabs is not configured yet (missing ELEVENLABS_API_KEY in .env).'];
        }

        try {
            $response = Http::withHeaders(['xi-api-key' => $apiKey])
                ->timeout(20)
                ->get(self::BASE_URL . '/voices');
        } catch (\Throwable $e) {
            Log::warning('ElevenLabs listVoices failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => "Couldn't reach ElevenLabs right now."];
        }

        if ($response->failed()) {
            Log::warning('ElevenLabs listVoices failed (' . $response->status() . '): ' . $response->body());
            return ['status' => 'ERROR', 'message' => 'ElevenLabs rejected the request — check the API key or account quota.'];
        }

        $voices = collect($response->json('voices', []))->map(function ($v) {
            return [
                'voice_id' => $v['voice_id'] ?? null,
                'name' => $v['name'] ?? 'Unnamed',
                'preview_url' => $v['preview_url'] ?? null,
                'labels' => $v['labels'] ?? [],
            ];
        })->filter(fn ($v) => $v['voice_id'])->values()->all();

        return ['status' => 'OK', 'voices' => $voices];
    }

    /**
     * Auto-pick a sensible voice_id (a Malaysian-accent voice if one
     * exists on this account, else the account's first available voice).
     * Only used when Chris hasn't explicitly saved a choice yet. Cached
     * for an hour so every chat reply doesn't re-list voices.
     */
    private function autoPickVoiceId(): ?string
    {
        return Cache::remember('elevenlabs_default_voice_id', 3600, function () {
            $result = $this->listVoices();
            if ($result['status'] !== 'OK' || empty($result['voices'])) {
                return null;
            }
            $voices = $result['voices'];

            // 1) One of our known-good Malaysian-accent names, if present.
            foreach (self::PREFERRED_VOICE_NAMES as $preferredName) {
                foreach ($voices as $v) {
                    if (strcasecmp($v['name'], $preferredName) === 0) {
                        return $v['voice_id'];
                    }
                }
            }

            // 2) FIX 3 Aug 2026 — those names don't exist on every
            // account, and falling back to "just the first voice" could
            // land on a male voice (that's what happened to Chris).
            // Prefer any voice explicitly labelled female instead.
            foreach ($voices as $v) {
                if (strcasecmp($v['labels']['gender'] ?? '', 'female') === 0) {
                    return $v['voice_id'];
                }
            }

            // 3) Last resort — genuinely nothing labelled, just use the first one.
            return $voices[0]['voice_id'];
        });
    }

    /**
     * Same auto-pick logic as autoPickVoiceId(), but scoped to a specific
     * agent's OWN connected API key (never the global config default, and
     * cached per-key so different agents' picks never mix).
     */
    public function autoPickVoiceIdForKey(string $apiKey): ?string
    {
        return Cache::remember('elevenlabs_default_voice_id:' . md5($apiKey), 3600, function () use ($apiKey) {
            $result = $this->listVoices($apiKey);
            if ($result['status'] !== 'OK' || empty($result['voices'])) {
                return null;
            }
            $voices = $result['voices'];

            foreach (self::PREFERRED_VOICE_NAMES as $preferredName) {
                foreach ($voices as $v) {
                    if (strcasecmp($v['name'], $preferredName) === 0) {
                        return $v['voice_id'];
                    }
                }
            }
            foreach ($voices as $v) {
                if (strcasecmp($v['labels']['gender'] ?? '', 'female') === 0) {
                    return $v['voice_id'];
                }
            }
            return $voices[0]['voice_id'];
        });
    }

    /**
     * Resolve which voice_id + speed to use when the caller didn't pin
     * one explicitly: Chris's saved choice if he's made one, else an
     * auto-picked sensible default.
     *
     * @return array{voice_id:?string, speed:?float}
     */
    public function resolveDefault(): array
    {
        $persisted = $this->getPersistedVoice();
        if ($persisted) {
            return $persisted;
        }
        return ['voice_id' => $this->autoPickVoiceId(), 'speed' => null];
    }

    /**
     * @param ?string $apiKeyOverride NEW 3 Aug 2026 — per-agent Voice
     *   Assistant. When supplied (by Tts\ElevenLabsTtsProvider, using an
     *   agent's OWN connected key), that key is used instead of the
     *   global .env key — voice usage is billed to that agent's own
     *   account, never Chris's or the shared config default.
     * @param ?string $languageCode NEW 6 Aug 2026 — ISO 639-1 code ('zh', 'ms', etc).
     *   ElevenLabs' default model (eleven_multilingual_v2) auto-detects language from the
     *   text itself and has NO way to be told explicitly — which is exactly why Mandarin
     *   replies were coming out read with an English accent (the model guesses, and guesses
     *   wrong often enough to sound off). Only eleven_turbo_v2_5 / eleven_flash_v2_5 support
     *   an explicit language_code to force correct native pronunciation, so when this is a
     *   non-English language we switch models for this one request. English keeps using
     *   eleven_multilingual_v2 as before — unchanged, since that was never the problem.
     * @return array{status:string, audio?:string, voice_id?:string, message?:string}
     */
    public function speak(string $text, ?string $voiceId = null, ?float $speed = null, ?string $apiKeyOverride = null, ?string $languageCode = null): array
    {
        $requestId = substr(md5(uniqid('', true)), 0, 8); // ties together the log lines for one call
        $apiKey = $apiKeyOverride ?: config('services.elevenlabs.key');
        if (empty($apiKey)) {
            Log::info("[Carolyn][$requestId] speak() aborted — ElevenLabs not configured.");
            return ['status' => 'ERROR', 'message' => 'ElevenLabs is not configured yet.'];
        }

        $requestedVoiceId = $voiceId; // RCA logging — did the caller pin a voice, or are we resolving one?
        if ($voiceId === null) {
            if ($apiKeyOverride) {
                // Per-agent key with no voice chosen — pick a sensible
                // default from THAT account's own voices, never Chris's
                // old global pick (the two must never mix).
                $voiceId = $this->autoPickVoiceIdForKey($apiKeyOverride);
            } else {
                $default = $this->resolveDefault();
                $voiceId = $default['voice_id'];
                if ($speed === null) {
                    $speed = $default['speed'];
                }
            }
        }

        Log::info("[Carolyn][$requestId] speak() resolving voice", [
            'requested_voice_id' => $requestedVoiceId,
            'resolved_voice_id' => $voiceId,
            'speed' => $speed,
            'language_code' => $languageCode,
            'text_length' => mb_strlen($text),
        ]);

        if (!$voiceId) {
            Log::warning("[Carolyn][$requestId] speak() aborted — no voice_id available at all.");
            return ['status' => 'ERROR', 'message' => 'No ElevenLabs voice is available on this account yet.'];
        }

        // Keep requests short — this is a chat widget, not a narration
        // tool, and it protects Chris's monthly character quota.
        $text = mb_substr($text, 0, 600);

        $voiceSettings = [
            'stability' => 0.5,
            'similarity_boost' => 0.75,
        ];
        if ($speed !== null) {
            $voiceSettings['speed'] = max(0.7, min(1.2, $speed));
        }

        // NEW 6 Aug 2026 — see @param doc above. English (or no language
        // given) keeps the exact behaviour this always had.
        $isNonEnglish = $languageCode && $languageCode !== 'en';
        $payload = [
            'text' => $text,
            'model_id' => $isNonEnglish ? 'eleven_turbo_v2_5' : 'eleven_multilingual_v2',
            'voice_settings' => $voiceSettings,
        ];
        if ($isNonEnglish) {
            $payload['language_code'] = $languageCode;
        }

        $startedAt = microtime(true);
        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'accept' => 'audio/mpeg',
                'content-type' => 'application/json',
            ])->timeout(30)->post(self::BASE_URL . "/text-to-speech/{$voiceId}", $payload);
        } catch (\Throwable $e) {
            Log::warning("[Carolyn][$requestId] speak() network/exception failure after " . round((microtime(true) - $startedAt) * 1000) . 'ms: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => "Couldn't reach ElevenLabs right now."];
        }

        $elapsedMs = round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning("[Carolyn][$requestId] speak() FAILED (HTTP {$response->status()}) after {$elapsedMs}ms — voice_id={$voiceId}: " . $response->body());
            return ['status' => 'ERROR', 'message' => 'ElevenLabs could not generate speech (check API key or quota).'];
        }

        Log::info("[Carolyn][$requestId] speak() SUCCESS in {$elapsedMs}ms — voice_id={$voiceId}, audio_bytes=" . strlen($response->body()));

        return ['status' => 'OK', 'audio' => $response->body(), 'voice_id' => $voiceId];
    }
}
