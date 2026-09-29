<?php

namespace App\Services\Tts;

use App\Services\HubVaultService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// -------------------------------------------------------
// NEW 3 Aug 2026 — the single place that decides WHOSE voice provider
// (and whose key) gets used for a given /speak request. The rule, per
// Chris's explicit instruction: a guest (not logged in) or an agent who
// hasn't connected their own provider ALWAYS gets no paid provider —
// meaning the browser's free built-in voice is used instead. Nothing
// ever silently falls back to a shared or admin key.
//
// REWIRED 6 Aug 2026 — per Chris: the agent should never have to paste
// their API key twice. The key now lives ONLY in the Integration Hub's
// encrypted vault (agent_integrations.api_key_encrypted, via
// HubVaultService) — Profile just stores WHICH connected provider to
// use (agents.voice_provider) plus an optional voice_id. resolve() now
// returns a status-tagged result instead of a bare array|null, so
// callers can show the agent a specific reason (not connected / Hub
// locked / key unreadable) instead of one generic message. Mirrors the
// exact fallback precedent already used by Document Reading — see
// SalesTransactionController::extractDocument().
// -------------------------------------------------------
class TtsProviderResolver
{
    public function __construct(private HubVaultService $vault)
    {
    }

    /**
     * @param ?object $agent The authenticated agent row (Auth::guard('agent')->user()), or null for guests.
     * @return array{
     *   status: 'NONE'|'NOT_CONNECTED'|'LOCKED'|'UNREADABLE'|'READY',
     *   providerLabel?: string,
     *   provider?: TtsProviderInterface,
     *   apiKey?: string,
     *   voiceId?: ?string
     * }
     */
    public function resolve(?object $agent): array
    {
        if (!$agent || empty($agent->voice_provider)) {
            // Guest, or agent chose "None — free built-in voice".
            return ['status' => 'NONE'];
        }

        $providerLabel = $agent->voice_provider === 'ELEVENLABS' ? 'ElevenLabs' : ($agent->voice_provider === 'OPENAI' ? 'OpenAI' : $agent->voice_provider);
        $providerKey = strtolower($agent->voice_provider); // 'elevenlabs' | 'openai'

        $row = DB::table('agent_integrations')
            ->where('agent_id', $agent->agent_id)
            ->where('category', 'ai_services')
            ->where('provider', $providerKey)
            ->first();

        if (!$row || $row->status !== 'CONNECTED') {
            return ['status' => 'NOT_CONNECTED', 'providerLabel' => $providerLabel];
        }

        if (!$this->vault->isUnlocked($agent->agent_id)) {
            return ['status' => 'LOCKED', 'providerLabel' => $providerLabel];
        }

        try {
            $apiKey = $this->vault->decryptSecret($agent->agent_id, $row->api_key_encrypted);
        } catch (\Throwable $e) {
            Log::warning('[AiVoice] Could not decrypt agent voice provider key from Hub vault — falling back to browser voice.', ['agent_id' => $agent->agent_id]);
            return ['status' => 'UNREADABLE', 'providerLabel' => $providerLabel];
        }

        $provider = match ($agent->voice_provider) {
            'ELEVENLABS' => app(ElevenLabsTtsProvider::class),
            'OPENAI' => app(OpenAiTtsProvider::class),
            default => null,
        };

        if (!$provider) {
            Log::warning('[AiVoice] Unknown voice_provider on agent record — falling back to browser voice.', ['voice_provider' => $agent->voice_provider]);
            return ['status' => 'NONE'];
        }

        return ['status' => 'READY', 'provider' => $provider, 'apiKey' => $apiKey, 'voiceId' => $agent->voice_id ?? null];
    }
}
