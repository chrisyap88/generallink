<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NEW 5 Aug 2026 — BYOK sibling of ClaudeDocumentExtractionService. Used
 * ONLY when an agent has chosen "My Own Gemini Key" as their Document
 * Reading preference in My Profile, and that key is connected + tested
 * (status = CONNECTED) in their Integration Hub. Same extract() contract
 * as the Claude version so SalesTransactionController can swap between
 * them without any other code changing.
 *
 * The API key is NOT read from config/.env — it is passed in per-call,
 * decrypted from the agent's own Hub vault by the caller. Nothing here
 * ever touches a shared/company key.
 */
class GeminiDocumentExtractionService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    /**
     * @param string $filePath Absolute path to the uploaded file on disk.
     * @param string $mimeType 'application/pdf', 'image/jpeg', or 'image/png'.
     * @param array<string,string> $fields Map of field_key => human description of what to extract.
     * @param string $apiKey The agent's own Gemini API key (already decrypted).
     * @return array{status:string, values?:array<string,mixed>, message?:string}
     */
    public function extract(string $filePath, string $mimeType, array $fields, string $apiKey): array
    {
        $binary = @file_get_contents($filePath);
        if ($binary === false) {
            return ['status' => 'ERROR', 'message' => 'Could not read the uploaded file.'];
        }
        $base64 = base64_encode($binary);

        $fieldList = '';
        foreach ($fields as $key => $description) {
            $fieldList .= "- \"{$key}\": {$description}\n";
        }

        $prompt = <<<PROMPT
You are reading a real business document (an insurance policy, receipt, invoice, or similar) for a Malaysian insurance agency's record-keeping system. The document may be in English, Bahasa Malaysia, Chinese, or a mix of these — read it in whatever language it is actually written in.

Extract exactly these fields:
{$fieldList}

Rules:
- If a field is genuinely not present anywhere in the document, use null for it — never guess or invent a value.
- Dates must be returned as DD-MM-YYYY.
- Money amounts must be plain numbers with no currency symbol or thousands separator (e.g. 456.00, not "RM456.00").
- If handwriting makes a value genuinely ambiguous, still give your best reading, but prefix the value with "UNSURE: " so a human knows to double-check it against the original.
- Respond with ONLY a single JSON object mapping each field key above to its value (or null). No other text, no explanation, no markdown code fences.
PROMPT;

        try {
            $response = Http::timeout(60)->post(self::API_URL . '?key=' . $apiKey, [
                'contents' => [
                    [
                        'parts' => [
                            ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gemini document extraction request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach Gemini — check your internet connection and try again. You can also switch to Company Document Credit in My Profile, or fill the form in manually.'];
        }

        if ($response->failed()) {
            Log::warning('Gemini document extraction failed (' . $response->status() . '): ' . $response->body());

            if (in_array($response->status(), [401, 403])) {
                // NEW 5 Aug 2026 — flagged so the caller can flip this
                // key's Integration Hub status back to ERROR right away,
                // instead of leaving a stale "Connected" badge that isn't
                // true anymore until the agent happens to retest it.
                return ['status' => 'ERROR', 'auth_error' => true, 'message' => 'Your Gemini key was rejected — it may have been deleted/revoked in AI Studio or hit a quota limit. Go to My Integrations > AI Services and click Test Connection on Gemini to see the exact reason and fix it. In the meantime, you can switch your Document Reading preference back to Company Document Credit in My Profile, or just fill this form in manually.'];
            }

            return ['status' => 'ERROR', 'message' => 'Gemini could not read this document right now (error ' . $response->status() . ') — this looks like a temporary problem on Google\'s side, not your key. Try again in a moment, switch to Company Document Credit in My Profile, or fill the form in manually.'];
        }

        $json = $response->json();
        $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            return ['status' => 'ERROR', 'message' => 'Gemini returned an unexpected response.'];
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $text = trim($text);

        $values = json_decode($text, true);
        if (!is_array($values)) {
            Log::warning('Gemini document extraction returned non-JSON text: ' . $text);
            return ['status' => 'ERROR', 'message' => "Could not understand Gemini's response. Please fill the form manually."];
        }

        return ['status' => 'OK', 'values' => $values];
    }
}
