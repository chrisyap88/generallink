<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

class GroqConnector implements IntegrationConnectorInterface
{
    // NEW 5 Aug 2026 — per Chris's mandatory requirement: every failure
    // message across GeneralLink must say what likely happened AND what
    // to do about it, so a user is never left guessing or assuming
    // GeneralLink itself is broken. Never just "Invalid API key."
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your Groq API key first, then click Test Connection.'];
        }
        try {
            $response = Http::withToken($key)->timeout(10)->get('https://api.groq.com/openai/v1/models');
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'Groq rejected this key. Likely reasons: it was copied incorrectly, it has been revoked, or your Groq account has no credit left. Check it at console.groq.com/keys, fix it, then try again.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'Groq says you have hit a usage limit right now — this is not a problem with the key itself. Wait a minute and try again.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'Groq\'s own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your key or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'Groq returned an unexpected response (HTTP ' . $response->status() . '). Double-check the key was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Groq — check your internet connection and try again. If this keeps happening, Groq\'s service may be temporarily down.'];
        }
    }
}
