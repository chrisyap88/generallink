<?php

namespace App\Services;

// NEW 22 Jul 2026 — per Chris: only TEAM_LEADER and INTRODUCER logins
// may set/use a preferred language (English / Chinese / Malay). GL and
// Admin always render in English, full stop, regardless of whatever
// value happens to sit in agents.preferred_language for them — this
// class is the single place that rule lives, so no controller or view
// has to remember it independently.
//
// OPENED UP 18 Aug 2026 — per Chris: every role (including Group
// Leader, Admin, and future CBE members) may now set/use a preferred
// language, not just Team Leader/Introducer — needed for CBE's
// bilingual plan. Default stays English (agents.preferred_language
// defaults to 'EN' in the DB, unchanged) — this only removes the
// role-based BLOCK, it doesn't change anyone's language for them.
class LanguageService
{
    public const SUPPORTED = ['EN', 'ZH', 'MS'];

    public const LABELS = [
        'EN' => 'English',
        'ZH' => 'Chinese',
        'MS' => 'Bahasa Malaysia',
    ];

    // NEW 6 Aug 2026 — per Chris: Carolyn's Mandarin speech was coming out
    // English-accented because nothing downstream of the text reply ever
    // knew what language was actually being spoken. These map our internal
    // EN/ZH/MS codes to the codes each TTS provider actually expects — see
    // AiVoiceController::speak() / ElevenLabsService::speak() / GoogleTtsProvider.
    public const ISO_639_1 = [
        'EN' => 'en',
        'ZH' => 'zh',
        'MS' => 'ms',
    ];

    // Google Cloud TTS is the odd one out — it uses 'cmn-CN' for Mandarin,
    // not 'zh-CN', and needs a full BCP-47 locale rather than a bare
    // ISO 639-1 code for every language, including English. Keyed by the
    // same lowercase ISO 639-1 code ISO_639_1/iso639() produce, so every
    // TTS provider is handed and reads the same code format.
    public const GOOGLE_LOCALE = [
        'en' => 'en-US',
        'zh' => 'cmn-CN',
        'ms' => 'ms-MY',
    ];

    public const GOOGLE_DEFAULT_VOICE = [
        'en' => 'en-US-Neural2-F',
        'zh' => 'cmn-CN-Wavenet-A',
        'ms' => 'ms-MY-Wavenet-A',
    ];

    public function iso639(string $code): string
    {
        return self::ISO_639_1[$code] ?? 'en';
    }

    /**
     * Is this agent even eligible to have a non-English preference?
     * OPENED UP 18 Aug 2026 — every role now qualifies (was previously
     * TEAM_LEADER/INTRODUCER only). Kept as its own method rather than
     * removed outright, so a future role-based exception is still a
     * one-line change in exactly one place, not scattered everywhere.
     */
    public function isEligible(object $agent): bool
    {
        return true;
    }

    /**
     * The language this agent's screens/messages should actually
     * render in right now — falls back to English if nothing valid is
     * stored.
     */
    public function effectiveLanguage(object $agent): string
    {
        if (!$this->isEligible($agent)) {
            return 'EN';
        }

        $value = $agent->preferred_language ?? 'EN';
        return in_array($value, self::SUPPORTED, true) ? $value : 'EN';
    }

    public function label(string $code): string
    {
        return self::LABELS[$code] ?? $code;
    }
}
