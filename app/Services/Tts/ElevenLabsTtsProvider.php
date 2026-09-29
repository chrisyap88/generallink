<?php

namespace App\Services\Tts;

use App\Services\ElevenLabsService;

// NEW 3 Aug 2026 — thin adapter so ElevenLabs plugs into the same generic
// TtsProviderInterface as every other provider. All the real HTTP/logging
// logic still lives in ElevenLabsService (unchanged, just extended to
// accept a per-agent key override) — nothing is duplicated here.
class ElevenLabsTtsProvider implements TtsProviderInterface
{
    public function __construct(private ElevenLabsService $service)
    {
    }

    public function speak(string $text, string $apiKey, ?string $voiceId, ?string $languageCode = null): array
    {
        return $this->service->speak($text, $voiceId, null, $apiKey, $languageCode);
    }
}
