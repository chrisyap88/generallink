<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NEW 6 Aug 2026 — same pattern as DocumentExtractionPreferenceService,
 * applied to Voice Assistant. Per Chris: an agent should never have to
 * paste an API key on the Profile page after already connecting it in
 * the Integration Hub. Profile now only lets the agent PICK a provider
 * they've already connected (and Test Connection'd) in the Hub — the
 * key itself lives only in the Hub's own encrypted vault.
 */
class VoicePreferenceService
{
    public const OPTIONS = ['ELEVENLABS', 'OPENAI'];

    /** category/provider keys as stored in agent_integrations. */
    private const PROVIDER_KEYS = ['ELEVENLABS' => 'elevenlabs', 'OPENAI' => 'openai'];
    private const LABELS = ['ELEVENLABS' => 'ElevenLabs', 'OPENAI' => 'OpenAI'];

    /**
     * Throws a validation error if the agent tries to pick a voice
     * provider without it being connected AND successfully tested in
     * their Integration Hub.
     */
    public static function assertUsable(string $agentId, ?string $provider): void
    {
        if (empty($provider)) {
            return; // "None — use the free built-in voice" always allowed
        }

        $providerKey = self::PROVIDER_KEYS[$provider] ?? null;
        if (!$providerKey) {
            throw ValidationException::withMessages([
                'voice_provider' => 'Voice Assistant only supports ElevenLabs or OpenAI as a personal voice — please choose one of those, or None.',
            ]);
        }

        $row = DB::table('agent_integrations')
            ->where('agent_id', $agentId)
            ->where('category', 'ai_services')
            ->where('provider', $providerKey)
            ->first();

        if (!$row || $row->status !== 'CONNECTED') {
            $label = self::LABELS[$provider];
            throw ValidationException::withMessages([
                'voice_provider' => "Connect your {$label} key in the Integration Hub and click Test Connection successfully first, before choosing it here.",
            ]);
        }
    }

    /**
     * Connection status (CONNECTED / DISCONNECTED / ERROR / not saved at
     * all) for both supported voice providers, for the Profile screen to
     * show next to each option.
     */
    public static function statusFor(string $agentId): array
    {
        $rows = DB::table('agent_integrations')
            ->where('agent_id', $agentId)
            ->where('category', 'ai_services')
            ->whereIn('provider', ['elevenlabs', 'openai'])
            ->pluck('status', 'provider');

        return [
            'ELEVENLABS' => $rows['elevenlabs'] ?? null,
            'OPENAI' => $rows['openai'] ?? null,
        ];
    }
}
