<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 17 Sep 2026 — per Chris (his "Gemini Gem" idea): a named CBE
// Assistant answers a member's question using ONLY the entity's own
// attached documents (bylaws, ROS garis panduan, etc) — never guessing
// from general knowledge. Every attached document's actual file bytes
// are sent to Claude at question time (same pattern as
// ClaudeDocumentExtractionService — a PDF/image "document" block, no
// separate text-extraction/embedding step), so a document that's been
// replaced with a new version is always read fresh, never stale.
//
// Kept as its OWN small service, deliberately NOT wired into the
// existing app-wide AiAssistantService::chat() — that assistant is a
// different, already-shipped feature (login/navigation help + ticket
// logging) and Chris's own rule is "do not introduce avoidable
// regressions." Same HTTP call shape, own file, zero shared state.
class CbeKnowledgeBaseAssistantService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @param array<int, array{path:string, mime:string, name:string}> $documents Absolute local file paths.
     * @param array<int, array{title:string, text:string}> $webSources Cached plain-text content from URL/YouTube sources (see CbeAiSourceFetchService).
     * @param array<int, array{question:string, answer:string}> $history Prior turns in this chat, oldest first (kept short).
     */
    public function ask(string $assistantName, ?string $instructions, array $documents, array $webSources, array $history, string $question): array
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return ['status' => 'ERROR', 'message' => 'ANTHROPIC_API_KEY is not set in .env — this assistant is not configured yet.'];
        }

        if (empty($documents) && empty($webSources)) {
            return ['status' => 'ERROR', 'message' => 'This assistant has no knowledge sources attached yet — ask an officer/Secretary to attach at least one document or link.'];
        }

        $contentBlocks = [];
        foreach ($documents as $doc) {
            $binary = @file_get_contents($doc['path']);
            if ($binary === false) {
                continue;
            }
            $base64 = base64_encode($binary);
            $contentBlocks[] = $doc['mime'] === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $doc['mime'], 'data' => $base64]];
        }

        if (empty($contentBlocks) && empty($webSources)) {
            return ['status' => 'ERROR', 'message' => 'None of this assistant\'s attached knowledge sources could be read.'];
        }

        $sourceNames = array_merge(
            array_map(fn ($d) => $d['name'], $documents),
            array_map(fn ($w) => $w['title'], $webSources)
        );
        $sourceNamesList = implode(', ', $sourceNames);

        $webSourcesText = '';
        foreach ($webSources as $w) {
            $webSourcesText .= "\n\n----- Source: {$w['title']} -----\n" . $w['text'];
        }

        $system = <<<PROMPT
You are "{$assistantName}", a knowledge-base assistant for a Malaysian community/business entity (CBE) inside the GeneralLink system. You answer questions using ONLY the attached knowledge sources ({$sourceNamesList}) — never from general knowledge, never guessing, and never inventing anything not actually in them. Sources may be in English, Bahasa Malaysia, or Chinese, and may include documents, web links/articles, and YouTube video transcripts.

If the answer is genuinely not in the attached sources, say plainly that the sources don't cover that, and suggest the member check with an officer/Secretary — do NOT guess or fill the gap with outside knowledge, even if you personally know the general answer (e.g. general ROS rules) — only what's actually in these specific sources.

Additional instructions from the Secretary who set up this assistant:
{$instructions}

Answer in the same language the question was asked in. Keep answers clear and concise — this is read by ordinary members, not lawyers. When helpful, mention which source (document, link, or video) the answer came from.
PROMPT;
        if ($webSourcesText !== '') {
            $system .= "\n\nAttached web/video source text:" . $webSourcesText;
        }

        $messages = [];
        foreach ($history as $turn) {
            $messages[] = ['role' => 'user', 'content' => $turn['question']];
            $messages[] = ['role' => 'assistant', 'content' => $turn['answer']];
        }
        $messages[] = [
            'role' => 'user',
            'content' => array_merge($contentBlocks, [['type' => 'text', 'text' => $question]]),
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 1024,
                'system' => $system,
                'messages' => $messages,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CBE knowledge base assistant request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the AI service right now — please try again in a moment.'];
        }

        if ($response->failed()) {
            Log::warning('CBE knowledge base assistant failed (' . $response->status() . '): ' . $response->body());
            return ['status' => 'ERROR', 'message' => 'The AI service could not answer right now — this looks like a temporary problem. Please try again.'];
        }

        $text = $response->json('content.0.text');
        if ($text === null) {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response.'];
        }

        return ['status' => 'OK', 'answer' => trim($text)];
    }
}
