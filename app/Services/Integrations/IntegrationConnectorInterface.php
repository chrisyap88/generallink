<?php

namespace App\Services\Integrations;

// NEW 4 Aug 2026 — Integration Hub Phase 1. Every provider (OpenAI,
// Gemini, WhatsApp, Stripe, ...) implements this the same way the
// existing TtsProviderInterface does for voice providers. testConnection()
// must be a cheap, side-effect-free call (list models / fetch account
// info) — it is what the "Test Connection" button in the Integration
// Hub calls, never a real billable action.
interface IntegrationConnectorInterface
{
    /**
     * @param array $credentials keys depend on credential_type — e.g. ['api_key' => '...']
     * @return array{success:bool, message:string}
     */
    public function testConnection(array $credentials): array;
}
