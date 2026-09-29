<?php

namespace App\Services\Tts;

use App\Services\LanguageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 3 Aug 2026 — Google Cloud Text-to-Speech, using an agent's OWN
// Google Cloud API key. Google's REST API returns JSON with base64-
// encoded audio (not raw bytes like ElevenLabs/OpenAI), so this decodes
// it before returning — callers never need to know the difference.
//
// FIX 6 Aug 2026 — this used to hardcode 'languageCode' => 'en-US' on
// EVERY request no matter what language Carolyn was actually replying in,
// which is a real bug (not just an accent quality issue like ElevenLabs
// had): Google's API voice names are language-locked, so an agent-picked
// Mandarin voice combined with a forced 'en-US' languageCode could
// outright fail or silently mispronounce. Also note Google uses 'cmn-CN'
// for Mandarin, not 'zh-CN' — see LanguageService::GOOGLE_LOCALE.
class GoogleTtsProvider implements TtsProviderInterface
{
    private const API_URL = 'https://texttospeech.googleapis.com/v1/text:synthesize';

    public function speak(string $text, string $apiKey, ?string $voiceId, ?string $languageCode = null): array
    {
        $requestId = substr(md5(uniqid('', true)), 0, 8);

        // $languageCode arrives as a lowercase ISO 639-1 code ('en'/'zh'/'ms',
        // see LanguageService::iso639) — Google needs its own locale format
        // and its own per-language default voice name.
        $code = ($languageCode && isset(LanguageService::GOOGLE_LOCALE[$languageCode])) ? $languageCode : 'en';
        $googleLocale = LanguageService::GOOGLE_LOCALE[$code];
        $voice = $voiceId ?: LanguageService::GOOGLE_DEFAULT_VOICE[$code];
        $text = mb_substr($text, 0, 600);

        Log::info("[Carolyn][$requestId] Google TTS speak() resolving voice", ['voice' => $voice, 'locale' => $googleLocale, 'text_length' => mb_strlen($text)]);

        $startedAt = microtime(true);
        try {
            $response = Http::timeout(30)->post(self::API_URL . '?key=' . $apiKey, [
                'input' => ['text' => $text],
                'voice' => ['languageCode' => $googleLocale, 'name' => $voice],
                'audioConfig' => ['audioEncoding' => 'MP3'],
            ]);
        } catch (\Throwable $e) {
            Log::warning("[Carolyn][$requestId] Google TTS network/exception failure: " . $e->getMessage());
            return ['status' => 'ERROR', 'message' => "Couldn't reach Google right now."];
        }

        $elapsedMs = round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning("[Carolyn][$requestId] Google TTS FAILED (HTTP {$response->status()}) after {$elapsedMs}ms: " . $response->body());
            return ['status' => 'ERROR', 'message' => 'Google could not generate speech (check the API key or account quota).'];
        }

        $base64Audio = $response->json('audioContent');
        if (empty($base64Audio)) {
            Log::warning("[Carolyn][$requestId] Google TTS returned no audioContent after {$elapsedMs}ms: " . $response->body());
            return ['status' => 'ERROR', 'message' => 'Google returned an unexpected response.'];
        }

        Log::info("[Carolyn][$requestId] Google TTS SUCCESS in {$elapsedMs}ms — voice={$voice}");

        return ['status' => 'OK', 'audio' => base64_decode($base64Audio), 'voice_id' => $voice];
    }
}
