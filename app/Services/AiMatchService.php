<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 3 (Task #90). Real
// AI business-opportunity matching, per Chris's requirement doc — beyond
// Phase 2's rule-based category/recency scoring, this asks Claude to
// write one short, plain-language sentence on WHY a specific promotion
// might matter to a specific agent, using the same Claude API Carolyn's
// Text Chat already calls (same model, same shared .env key — no new
// billing account, no cost surprise; per Chris's standing rule, nothing
// here is ever billed to his PERSONAL account, only the existing shared
// company key already used for every Carolyn conversation).
//
// Cost control, deliberately conservative:
//   - Only ever called for PROMOTION-category notices (never Important
//     Update/Holiday/Contact Info/General — those aren't "opportunities").
//   - Only called ONCE per (notice, agent) pair — see NoticeDeliveryService,
//     which checks notice_ai_matches for an existing row first and skips
//     the call entirely if one exists (even a failed/null one, so a
//     temporary outage never retries forever on every notice post).
//   - Tiny max_tokens (60) and the cheapest model already in use
//     (claude-haiku-4-5), never a bigger/pricier model.
//   - Any failure (timeout, bad key, HTTP error) returns null quietly —
//     never blocks a notice from being posted or delivered.
class AiMatchService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Generates and stores (or looks up an existing) one-sentence AI blurb
     * explaining why this PROMOTION notice might matter to this agent.
     * Idempotent — safe to call for the same (notice, agent) pair many
     * times; only the first call actually reaches Claude.
     */
    public function matchFor(object $notice, object $agent): ?string
    {
        if ($notice->category !== 'PROMOTION') {
            return null;
        }

        $existing = DB::table('notice_ai_matches')
            ->where('notice_id', $notice->notice_id)
            ->where('agent_id', $agent->agent_id)
            ->first();
        if ($existing) {
            return $existing->blurb; // may be null (a prior attempt failed) — deliberately not retried
        }

        $blurb = $this->generateBlurb($notice, $agent);

        try {
            DB::table('notice_ai_matches')->insert([
                'notice_id' => $notice->notice_id,
                'agent_id' => $agent->agent_id,
                'blurb' => $blurb,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AiMatchService: failed to store blurb', ['notice_id' => $notice->notice_id, 'agent_id' => $agent->agent_id]);
        }

        return $blurb;
    }

    private function generateBlurb(object $notice, object $agent): ?string
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return null; // not configured — same silent-skip Carolyn's chat uses when this happens
        }

        $roleLabel = match ($agent->role) {
            'GROUP_LEADER' => 'Group Leader, who leads a whole team of agents',
            'TEAM_LEADER' => 'Team Leader, who leads a small team of Introducers',
            'INTRODUCER' => 'Introducer, who sells directly to customers',
            default => 'agent',
        };

        $system = 'You write ONE short, plain-language sentence (max 20 words, no markdown, no quotation marks) explaining why a business promotion might be worth an insurance agent\'s attention. Be specific and practical, never generic hype. If the promotion genuinely has nothing useful to say to this agent role, reply with exactly: SKIP';

        $prompt = "Promotion title: {$notice->title}\nPromotion details: " . mb_substr($notice->body, 0, 500) . "\nAgent role: {$roleLabel}\n\nWrite the one sentence now.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(15)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 60,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->failed()) {
                Log::warning('AiMatchService: Anthropic call failed', ['status' => $response->status()]);
                return null;
            }

            $text = trim($response->json('content.0.text') ?? '');
            if ($text === '' || strtoupper($text) === 'SKIP') {
                return null;
            }

            return mb_substr($text, 0, 250);
        } catch (\Throwable $e) {
            Log::warning('AiMatchService: Anthropic call exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
