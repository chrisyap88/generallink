<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * NEW 18 Jul 2026 — replaces the old tesseract/poppler OCR + coordinate
 * calibration pipeline (CoordinateCalibrationService/DocumentTemplateExtractionService)
 * entirely for real document reading. Sends the uploaded document straight
 * to the Claude API, which reads it directly the same way a person would —
 * no local OCR binaries, no per-vendor calibration, no bracket-labeling
 * exercise, and it works across English, Bahasa Malaysia, Chinese, or a mix,
 * on any document layout without ever being "taught" that layout first.
 */
class ClaudeDocumentExtractionService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    // Haiku is the cheapest Claude model and is fully capable of this
    // kind of structured field extraction — no need for a pricier model
    // for this task. See config/services.php for where the key comes from.
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * Reads one document and returns the requested fields as plain values.
     *
     * @param string $filePath Absolute path to the uploaded file on disk.
     * @param string $mimeType 'application/pdf', 'image/jpeg', or 'image/png'.
     * @param array<string,string> $fields Map of field_key => human description of what to extract.
     * @return array{status:string, values?:array<string,mixed>, message?:string}
     */
    public function extract(string $filePath, string $mimeType, array $fields): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return [
                'status'  => 'ERROR',
                'message' => 'ANTHROPIC_API_KEY is not set in .env — document reading is not configured yet.',
            ];
        }

        $binary = @file_get_contents($filePath);
        if ($binary === false) {
            return ['status' => 'ERROR', 'message' => 'Could not read the uploaded file.'];
        }
        $base64 = base64_encode($binary);

        // PDFs go in as a "document" block (Claude renders and reads every
        // page itself — no pre-conversion to an image needed, even for a
        // PDF that's really just a scanned photo with no text layer).
        // Photos go in as an "image" block directly.
        $documentBlock = $mimeType === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64]];

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
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type'      => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model'      => self::MODEL,
                'max_tokens' => 1536,
                'messages'   => [
                    [
                        'role'    => 'user',
                        'content' => [
                            $documentBlock,
                            ['type' => 'text', 'text' => $prompt],
                        ],
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Claude document extraction request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the document reading service — check your internet connection and try again, or fill the form in manually.'];
        }

        if ($response->failed()) {
            Log::warning('Claude document extraction failed (' . $response->status() . '): ' . $response->body());

            // 401/403 almost always means the shared company key is
            // missing/wrong/out of credit. This is never something a
            // normal agent can fix themselves (it's Chris's own key in
            // .env, not theirs) — so the message on-screen must send them
            // to their Admin, not to a place they can't access. The
            // technical detail (console.anthropic.com/.env) stays in the
            // Laravel log above for Chris, never shown to the agent.
            if (in_array($response->status(), [401, 403])) {
                return ['status' => 'ERROR', 'message' => 'GeneralLink\'s document reading service is temporarily unavailable — this is not something wrong with your account. Please let your Admin know, or fill the form in manually for now. If you already have your own OpenAI or Gemini key connected in My Integrations, you can also switch your Document Reading preference to that in My Profile.'];
            }

            return ['status' => 'ERROR', 'message' => 'GeneralLink\'s document reading service could not read this document right now (this looks like a temporary problem, not your account) — try again in a moment, or fill the form in manually.'];
        }

        $json = $response->json();
        $text = $json['content'][0]['text'] ?? null;

        if ($text === null) {
            return ['status' => 'ERROR', 'message' => 'The document reading service returned an unexpected response.'];
        }

        // Claude is asked for pure JSON, but strip a stray code-fence
        // wrapper defensively in case it adds one anyway.
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
        $text = trim($text);

        $values = json_decode($text, true);
        if (!is_array($values)) {
            Log::warning('Claude document extraction returned non-JSON text: ' . $text);
            return ['status' => 'ERROR', 'message' => "Could not understand the document reading service's response. Please fill the form manually."];
        }

        return ['status' => 'OK', 'values' => $values];
    }
}
