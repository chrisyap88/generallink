<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 3 Aug 2026 — OpenAI's text-to-speech API (separate from the Claude
// chat model that powers Carolyn's replies — this is purely for turning
// text into audio, using an agent's OWN OpenAI account/key). Same
// response contract as every other provider: raw MP3 bytes on success.
class OpenAiTtsProvider implements TtsProviderInterface
{
    private const API_URL = 'https://api.openai.com/v1/audio/speech';
    private const DEFAULT_VOICE = 'alloy'; // OpenAI's own default named voice — used whenever an agent hasn't picked one

    // NEW 6 Aug 2026 — $languageCode accepted for interface consistency but
    // unused: OpenAI's TTS API has no language parameter at all, it always
    // auto-detects from the input text itself.
    public function speak(string $text, string $apiKey, ?string $voiceId, ?string $languageCode = null): array
    {
        $requestId = substr(md5(uniqid('', true)), 0, 8);
        $voice = $voiceId ?: self::DEFAULT_VOICE;
        $text = mb_substr($text, 0, 600);

        Log::info("[Carolyn][$requestId] OpenAI TTS speak() resolving voice", ['voice' => $voice, 'text_length' => mb_strlen($text)]);

        $startedAt = microtime(true);
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model' => 'tts-1',
                'voice' => $voice,
                'input' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::warning("[Carolyn][$requestId] OpenAI TTS network/exception failure: " . $e->getMessage());
            return ['status' => 'ERROR', 'message' => "Couldn't reach OpenAI right now."];
        }

        $elapsedMs = round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            Log::warning("[Carolyn][$requestId] OpenAI TTS FAILED (HTTP {$response->status()}) after {$elapsedMs}ms: " . $response->body());
            return ['status' => 'ERROR', 'message' => 'OpenAI could not generate speech (check the API key or account quota).'];
        }

        Log::info("[Carolyn][$requestId] OpenAI TTS SUCCESS in {$elapsedMs}ms — voice={$voice}, audio_bytes=" . strlen($response->body()));

        return ['status' => 'OK', 'audio' => $response->body(), 'voice_id' => $voice];
    }
}
