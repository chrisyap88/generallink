<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

class GeminiConnector implements IntegrationConnectorInterface
{
    // NEW 5 Aug 2026 — per Chris's mandatory requirement: every failure
    // message across GeneralLink must say what likely happened AND what
    // to do about it, so a user is never left guessing or assuming
    // GeneralLink itself is broken. Never just "Invalid API key."
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your Gemini API key first, then click Test Connection.'];
        }
        try {
            $response = Http::timeout(10)->get('https://generativelanguage.googleapis.com/v1beta/models', ['key' => $key]);
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if (in_array($response->status(), [400, 401, 403])) {
                return ['success' => false, 'message' => 'Google rejected this key. Likely reasons: it was copied incorrectly, it has been deleted/revoked in AI Studio, or it is restricted to a different project. This is separate from a Gemini Pro/Advanced subscription, which does not include API access. Check it at aistudio.google.com, fix it, then try again. If you would rather not troubleshoot this now, you can still read sales documents using GeneralLink Document Credit instead — see My Profile > Document Reading.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Google says you have hit the free-tier rate limit right now — this is not a problem with the key itself. Wait a minute and try again.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'Google\'s own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your key or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'Google returned an unexpected response (HTTP ' . $response->status() . '). Double-check the key was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Google — check your internet connection and try again. If this keeps happening, Google\'s service may be temporarily down.'];
        }
    }
}
