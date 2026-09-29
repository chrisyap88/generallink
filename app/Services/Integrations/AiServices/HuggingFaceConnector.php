<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

class HuggingFaceConnector implements IntegrationConnectorInterface
{
    // NEW 5 Aug 2026 — per Chris's mandatory requirement: every failure
    // message across GeneralLink must say what likely happened AND what
    // to do about it, so a user is never left guessing or assuming
    // GeneralLink itself is broken. Never just "Invalid API key."
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your Hugging Face access token first, then click Test Connection.'];
        }
        try {
            $response = Http::withToken($key)->timeout(10)->get('https://huggingface.co/api/whoami-v2');
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Hugging Face rejected this token. Likely reasons: it was copied incorrectly, it has been revoked, or it is missing the permissions it needs. Check it at huggingface.co/settings/tokens, fix it, then try again.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Hugging Face says you have hit a usage limit right now — this is not a problem with the token itself. Wait a minute and try again.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'Hugging Face\'s own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your token or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'Hugging Face returned an unexpected response (HTTP ' . $response->status() . '). Double-check the token was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Hugging Face — check your internet connection and try again. If this keeps happening, Hugging Face\'s service may be temporarily down.'];
        }
    }
}
