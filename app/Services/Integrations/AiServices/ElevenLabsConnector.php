<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 6 Aug 2026 — per Chris: Voice Assistant now connects through the
// Integration Hub instead of a separate key field on the Profile page,
// so this makes ElevenLabs a real, testable Hub connector (it was
// previously listed with connector => null). testConnection() calls
// ElevenLabs' own "get my account" endpoint — cheap, read-only, never a
// billable text-to-speech call.
class ElevenLabsConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your ElevenLabs API key first, then click Test Connection.'];
        }
        try {
            $response = Http::withHeaders(['xi-api-key' => $key])->timeout(10)->get('https://api.elevenlabs.io/v1/user');
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'ElevenLabs rejected this key. Likely reasons: it was copied incorrectly, it has been revoked, or it belongs to a different ElevenLabs account than you expect. Check the key at elevenlabs.io/app/settings/api-keys, fix it, then try again. If you would rather not troubleshoot this now, Carolyn can keep using the free built-in voice instead.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'ElevenLabs says you have hit a usage or rate limit right now — this is not a problem with the key itself. Wait a minute and try again, or check your usage at elevenlabs.io.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'ElevenLabs\' own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your key or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'ElevenLabs returned an unexpected response (HTTP ' . $response->status() . '). Double-check the key was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach ElevenLabs — check your internet connection and try again. If this keeps happening, ElevenLabs\' service may be temporarily down.'];
        }
    }
}
