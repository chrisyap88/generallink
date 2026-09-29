<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 22 Jul 2026 — per Chris: Help Desk messages can be translated
// into an agent's preferred language, and a message being composed can
// be cleaned up/rephrased before sending. Both are real, per-call
// Claude API costs (same reasoning as ClaudeDocumentExtractionService,
// which this mirrors), which is why both draw from the Document
// Credit wallet with an up-front cost confirmation in the UI.
class ClaudeTranslationService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    private const LANGUAGE_NAMES = [
        'EN' => 'English',
        'ZH' => 'Simplified Chinese',
        'MS' => 'Bahasa Malaysia',
    ];

    /**
     * @return array{status:string, text?:string, message?:string}
     */
    public function translate(string $text, string $targetLanguageCode): array
    {
        $targetName = self::LANGUAGE_NAMES[$targetLanguageCode] ?? $targetLanguageCode;

        $prompt = <<<PROMPT
Translate the following message into {$targetName}. This is an internal work message between insurance agents in Malaysia, so keep it natural and businesslike, not overly formal or literal. Preserve any numbers, names, policy/reference numbers, and amounts exactly as written.

Respond with ONLY the translated text — no explanation, no quotation marks, no markdown.

Message:
{$text}
PROMPT;

        return $this->callClaude($prompt);
    }

    /**
     * @return array{status:string, text?:string, message?:string}
     */
    public function rephrase(string $text, string $sourceLanguageCode): array
    {
        $sourceName = self::LANGUAGE_NAMES[$sourceLanguageCode] ?? $sourceLanguageCode;

        $prompt = <<<PROMPT
The following is a work message written by an insurance agent in Malaysia, in {$sourceName}. The writer may not be fully confident in {$sourceName} spelling/grammar. Rewrite it to be clear, polite, and correctly spelled, keeping the same language ({$sourceName}) and the same meaning — do not translate it into a different language, and do not add new information. Preserve any numbers, names, policy/reference numbers, and amounts exactly as written.

Respond with ONLY the rewritten text — no explanation, no quotation marks, no markdown.

Message:
{$text}
PROMPT;

        return $this->callClaude($prompt);
    }

    private function callClaude(string $prompt): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['status' => 'ERROR', 'message' => 'ANTHROPIC_API_KEY is not set in .env — this feature is not configured yet.'];
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type'      => 'application/json',
            ])->timeout(30)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => 1024,
                'messages'   => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Claude translation/rephrase request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the translation service. Please try again.'];
        }

        if ($response->failed()) {
            Log::warning('Claude translation/rephrase failed (' . $response->status() . '): ' . $response->body());
            if (in_array($response->status(), [401, 403])) {
                return ['status' => 'ERROR', 'message' => 'The API key was rejected, or the account has run out of credit.'];
            }
            return ['status' => 'ERROR', 'message' => 'Could not reach the translation service (error ' . $response->status() . '). Please try again.'];
        }

        $json = $response->json();
        $text = $json['content'][0]['text'] ?? null;

        if ($text === null) {
            return ['status' => 'ERROR', 'message' => 'The translation service returned an unexpected response.'];
        }

        return ['status' => 'OK', 'text' => trim($text)];
    }
}
