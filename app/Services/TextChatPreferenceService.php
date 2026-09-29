<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NEW 6 Aug 2026 — same pattern as VoicePreferenceService /
 * DocumentExtractionPreferenceService, applied to Carolyn's Text Chat.
 * Only Anthropic is supported as a personal key for text chat.
 */
class TextChatPreferenceService
{
    public const OPTIONS = ['ANTHROPIC'];

    private const PROVIDER_KEYS = ['ANTHROPIC' => 'anthropic'];
    private const LABELS = ['ANTHROPIC' => 'Anthropic (Claude)'];

    /**
     * Throws a validation error if the agent tries to pick a text-chat
     * provider without it being connected AND successfully tested in
     * their Integration Hub.
     */
    public static function assertUsable(string $agentId, ?string $provider): void
    {
        if (empty($provider)) {
            return; // "None — use the shared default key" always allowed
        }

        $providerKey = self::PROVIDER_KEYS[$provider] ?? null;
        if (!$providerKey) {
            throw ValidationException::withMessages([
                'text_chat_provider' => 'Text Chat only supports Anthropic (Claude) as a personal key — please choose that, or None.',
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
                'text_chat_provider' => "Connect your {$label} key in the Integration Hub and click Test Connection successfully first, before choosing it here.",
            ]);
        }
    }

    /**
     * Connection status for Anthropic, for the Profile screen to show.
     */
    public static function statusFor(string $agentId): array
    {
        $rows = DB::table('agent_integrations')
            ->where('agent_id', $agentId)
            ->where('category', 'ai_services')
            ->whereIn('provider', ['anthropic'])
            ->pluck('status', 'provider');

        return [
            'ANTHROPIC' => $rows['anthropic'] ?? null,
        ];
    }
}
