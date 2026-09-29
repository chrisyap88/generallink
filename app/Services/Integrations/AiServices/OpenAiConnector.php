<?php

namespace App\Services\Integrations\AiServices;

use App\Services\Integrations\IntegrationConnectorInterface;
use Illuminate\Support\Facades\Http;

class OpenAiConnector implements IntegrationConnectorInterface
{
    // NEW 5 Aug 2026 — per Chris's mandatory requirement: every failure
    // message across GeneralLink must say what likely happened AND what
    // to do about it, so a user is never left guessing or assuming
    // GeneralLink itself is broken. Never just "Invalid API key."
    public function testConnection(array $credentials): array
    {
        $key = trim($credentials['api_key'] ?? '');
        if ($key === '') {
            return ['success' => false, 'message' => 'Paste your OpenAI API key first, then click Test Connection.'];
        }
        try {
            $response = Http::withToken($key)->timeout(10)->get('https://api.openai.com/v1/models');
            if ($response->successful()) {
                return ['success' => true, 'message' => 'Connected — key is valid.'];
            }
            if ($response->status() === 401) {
                return ['success' => false, 'message' => 'OpenAI rejected this key. Likely reasons: it was copied incorrectly, it has been revoked/expired, or your OpenAI account has no billing set up (a ChatGPT Plus subscription does NOT include API access — that is billed separately). Check your key and billing at platform.openai.com, fix it, then try again. If you would rather not troubleshoot this now, you can still read sales documents using GeneralLink Document Credit instead — see My Profile > Document Reading.'];
            }
            if ($response->status() === 429) {
                return ['success' => false, 'message' => 'OpenAI says you have hit a usage or rate limit right now — this is not a problem with the key itself. Wait a minute and try again, or check your usage limits at platform.openai.com.'];
            }
            if ($response->status() >= 500) {
                return ['success' => false, 'message' => 'OpenAI\'s own service returned an error (HTTP ' . $response->status() . ') — this is a temporary problem on their side, not your key or GeneralLink. Try again in a few minutes.'];
            }
            return ['success' => false, 'message' => 'OpenAI returned an unexpected response (HTTP ' . $response->status() . '). Double-check the key was copied in full, then try again.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach OpenAI — check your internet connection and try again. If this keeps happening, OpenAI\'s service may be temporarily down.'];
        }
    }
}
