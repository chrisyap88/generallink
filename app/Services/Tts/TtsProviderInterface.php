<?php

namespace App\Services\Tts;

// -------------------------------------------------------
// NEW 3 Aug 2026 — the ONE shared shape every voice provider must speak
// in, so AiVoiceController and the widget never need to know or care
// which specific provider an agent connected. Adding a new provider later
// (Google/Amazon/Microsoft/etc.) means writing one small class that
// implements this interface — nothing else in the app changes.
// -------------------------------------------------------
interface TtsProviderInterface
{
    /**
     * @param ?string $languageCode NEW 6 Aug 2026 — ISO 639-1 code ('en','zh','ms') for the
     *   agent's effective language (see LanguageService::effectiveLanguage). Lets a provider
     *   force correct native pronunciation instead of defaulting to an English-accented read
     *   of non-English text. Null/omitted means "assume English."
     * @return array{status:string, audio?:string, voice_id?:string, message?:string}
     *   status OK   => 'audio' is raw binary MP3 bytes, 'voice_id' is whatever voice was actually used.
     *   status ERROR => 'message' is a short, user-facing reason (never leaks the raw API key).
     */
    public function speak(string $text, string $apiKey, ?string $voiceId, ?string $languageCode = null): array;
}
