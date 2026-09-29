<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * NEW 5 Aug 2026 — shared by every role's ProfileController (Admin, GL,
 * TL, Introducer) so the "connected AND tested before you can pick it"
 * rule can never be bypassed by editing one role's controller and
 * forgetting the others.
 *
 * Document Reading (the sales-document OCR/AI extraction feature) only
 * ever supports two BYOK providers — OpenAI and Gemini — never any other
 * Integration Hub AI Services provider, since only these two have had
 * their extraction accuracy checked against the existing human Confirm
 * step. COMPANY_CREDIT (the default) needs no connection at all.
 */
class DocumentExtractionPreferenceService
{
    public const OPTIONS = ['COMPANY_CREDIT', 'OPENAI', 'GEMINI'];

    /** category/provider keys as stored in agent_integrations. */
    private const PROVIDER_KEYS = ['OPENAI' => 'openai', 'GEMINI' => 'gemini'];
    private const LABELS = ['OPENAI' => 'OpenAI', 'GEMINI' => 'Gemini'];

    /**
     * Throws a validation error (same shape as any other form field
     * error) if the agent tries to set their preference to OPENAI/GEMINI
     * without that provider being connected AND successfully tested in
     * their Integration Hub.
     */
    public static function assertUsable(string $agentId, string $provider): void
    {
        if ($provider === 'COMPANY_CREDIT' || empty($provider)) {
            return;
        }

        $providerKey = self::PROVIDER_KEYS[$provider] ?? null;
        if (!$providerKey) {
            throw ValidationException::withMessages([
                'document_extraction_provider' => 'Document Reading only supports OpenAI or Gemini as a personal key — please choose one of those, or Company Credit.',
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
                'document_extraction_provider' => "Connect your {$label} key in the Integration Hub and click Test Connection successfully first, before choosing it here.",
            ]);
        }
    }

    /**
     * Connection status (CONNECTED / DISCONNECTED / ERROR / not saved at
     * all) for both supported BYOK providers, for the Profile edit
     * screen to show next to each option.
     */
    public static function statusFor(string $agentId): array
    {
        $rows = DB::table('agent_integrations')
            ->where('agent_id', $agentId)
            ->where('category', 'ai_services')
            ->whereIn('provider', ['openai', 'gemini'])
            ->pluck('status', 'provider');

        return [
            'OPENAI' => $rows['openai'] ?? null,
            'GEMINI' => $rows['gemini'] ?? null,
        ];
    }
}
