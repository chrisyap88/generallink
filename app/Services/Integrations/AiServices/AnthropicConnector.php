<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

// NEW 6 Aug 2026 — per Chris: Text Chat now connects through the
// Integration Hub instead of a separate key field on the Profile page,
// so this makes Anthropic a real, testable Hub connector (it was
// previously listed with connector => null). testConnection() calls
// Anthropic's models list endpoint — cheap, read-only, never a billable
// chat message.
class AnthropicConnector implements IntegrationConnectorInterface
{
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your Anthropic API key first, then click Test Connection.'];
        }
        try {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->timeout(10)->get('https://api.anthropic.com/v1/models');
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Anthropic rejected this key. Likely reasons: it was copied incorrectly, it has been revoked/expired, or your Anthropic account has no billing set up (a Claude.ai subscription does NOT include API access — that is billed separately). Check your key and billing at console.anthropic.com, fix it, then try again. If you would rather not troubleshoot this now, Carolyn can keep using the shared default key instead.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Anthropic says you have hit a usage or rate limit right now — this is not a problem with the key itself. Wait a minute and try again, or check your usage at console.anthropic.com.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'Anthropic\'s own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your key or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'Anthropic returned an unexpected response (HTTP ' . $response->status() . '). Double-check the key was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Anthropic — check your internet connection and try again. If this keeps happening, Anthropic\'s service may be temporarily down.'];
        }
    }
}
