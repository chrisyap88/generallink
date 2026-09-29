<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NEW 5 Aug 2026 — BYOK sibling of ClaudeDocumentExtractionService. Used
 * ONLY when an agent has chosen "My Own OpenAI Key" as their Document
 * Reading preference in My Profile, and that key is connected + tested
 * (status = CONNECTED) in their Integration Hub. Same extract() contract
 * as the Claude version so SalesTransactionController can swap between
 * them without any other code changing.
 *
 * Unlike ClaudeDocumentExtractionService, the API key is NOT read from
 * config/.env — it is passed in per-call, decrypted from the agent's own
 * Hub vault by the caller. Nothing here ever touches a shared/company key.
 */
class OpenAiDocumentExtractionService
{
    private const API_URL = 'https://api.openai.com/v1/responses';
    private const MODEL = 'gpt-4o-mini';

    /**
     * @param string $filePath Absolute path to the uploaded file on disk.
     * @param string $mimeType 'application/pdf', 'image/jpeg', or 'image/png'.
     * @param array<string,string> $fields Map of field_key => human description of what to extract.
     * @param string $apiKey The agent's own OpenAI API key (already decrypted).
     * @return array{status:string, values?:array<string,mixed>, message?:string}
     */
    public function extract(string $filePath, string $mimeType, array $fields, string $apiKey): array
    {
        $binary = @file_get_contents($filePath);
        if ($binary === false) {
            return ['status' => 'ERROR', 'message' => 'Could not read the uploaded file.'];
        }
        $base64 = base64_encode($binary);

        // OpenAI's Responses API takes a PDF as an "input_file" (base64
        // data URI) and a photo as "input_image" — different content
        // block types, same request shape otherwise.
        $fileBlock = $mimeType === 'application/pdf'
            ? ['type' => 'input_file', 'filename' => basename($filePath) . '.pdf', 'file_data' => 'data:application/pdf;base64,' . $base64]
            : ['type' => 'input_image', 'image_url' => 'data:' . $mimeType . ';base64,' . $base64];

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
            $response = Http::withToken($apiKey)
                ->timeout(60)
                ->post(self::API_URL, [
                    'model' => self::MODEL,
                    'input' => [
                        [
                            'role' => 'user',
                            'content' => [
                                $fileBlock,
                                ['type' => 'input_text', 'text' => $prompt],
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAI document extraction request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach OpenAI — check your internet connection and try again. You can also switch to Company Document Credit in My Profile, or fill the form in manually.'];
        }

        if ($response->failed()) {
            Log::warning('OpenAI document extraction failed (' . $response->status() . '): ' . $response->body());

            if (in_array($response->status(), [401, 403])) {
                // NEW 5 Aug 2026 — flagged so the caller can flip this
                // key's Integration Hub status back to ERROR right away,
                // instead of leaving a stale "Connected" badge that isn't
                // true anymore until the agent happens to retest it.
                return ['status' => 'ERROR', 'auth_error' => true, 'message' => 'Your OpenAI key was rejected — it may have been revoked or run out of billing credit. Go to My Integrations > AI Services and click Test Connection on OpenAI to see the exact reason and fix it. In the meantime, you can switch your Document Reading preference back to Company Document Credit in My Profile, or just fill this form in manually.'];
            }

            return ['status' => 'ERROR', 'message' => 'OpenAI could not read this document right now (error ' . $response->status() . ') — this looks like a temporary problem on OpenAI\'s side, not your key. Try again in a moment, switch to Company Document Credit in My Profile, or fill the form in manually.'];
        }

        $json = $response->json();

        // Responses API returns an "output" array of items; the text we
        // want is nested inside the first message item's content.
        $text = null;
        foreach (($json['output'] ?? []) as $item) {
            if (($item['type'] ?? null) === 'message') {
                foreach (($item['content'] ?? []) as $content) {
                    if (isset($content['text'])) {
                        $text = $content['text'];
                        break 2;
                    }
                }
            }
        }
        // Fallback for the SDK's flattened convenience field, if present.
        $text = $text ?? ($json['output_text'] ?? null);

        if ($text === null) {
            return ['status' => 'ERROR', 'message' => 'OpenAI returned an unexpected response.'];
        }

        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $text = trim($text);

        $values = json_decode($text, true);
        if (!is_array($values)) {
            Log::warning('OpenAI document extraction returned non-JSON text: ' . $text);
            return ['status' => 'ERROR', 'message' => "Could not understand OpenAI's response. Please fill the form manually."];
        }

        return ['status' => 'OK', 'values' => $values];
    }
}
