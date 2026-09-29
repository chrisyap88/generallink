<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 18 Sep 2026 — per Chris's uploaded "Online Meeting Attendance &
// Meeting Minutes System" requirement (section 7, AI Meeting Minutes):
// reads an uploaded transcript/summary/notes file (or pasted text) plus
// the attendee list already recorded on the meeting, and drafts a
// structured Meeting Minutes body in the five sections his spec asks
// for (Discussion, Decisions, Action Items, Other Matters, Next
// Meeting). It must NOT invent anything not in the uploaded material —
// same "attached-file, ask fresh, no embedding" pattern as
// CbeKnowledgeBaseAssistantService, own small service, zero shared
// state. The organizer always reviews/edits the draft before Approve
// (see MeetingMinutesController::approveMinutes) — this never writes a
// final, unreviewed minute on its own.
class CbeMeetingMinutesAiDraftService
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const ANTHROPIC_VERSION = '2023-06-01';

    /**
     * @param array{path:?string, mime:?string, name:?string} $transcript Absolute local file path of the uploaded transcript/notes/summary, or null if only pasted text was given.
     * @return array{status:string, message?:string, sections?: array<int, array{heading:string, text:string}>}
     */
    public function draft(array $meetingInfo, ?array $transcript, ?string $pastedNotes): array
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return ['status' => 'ERROR', 'message' => 'ANTHROPIC_API_KEY is not set in .env — the AI draft is not configured yet.'];
        }

        $contentBlocks = [];
        if ($transcript && ! empty($transcript['path'])) {
            $binary = @file_get_contents($transcript['path']);
            if ($binary !== false) {
                $base64 = base64_encode($binary);
                $contentBlocks[] = ($transcript['mime'] ?? '') === 'application/pdf'
                    ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $base64]]
                    : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $transcript['mime'] ?? 'image/png', 'data' => $base64]];
            }
        }

        $pastedNotes = trim((string) $pastedNotes);
        if (empty($contentBlocks) && $pastedNotes === '') {
            return ['status' => 'ERROR', 'message' => 'Upload a meeting summary/transcript/notes file, or paste some notes, before generating a draft.'];
        }

        $system = <<<PROMPT
You are a Meeting Minutes Assistant for a Malaysian community/business entity inside the GeneralLink system. You are given the raw meeting information below plus an uploaded transcript/summary/notes (and/or pasted notes). Organize this into a professional set of Meeting Minutes.

You must ONLY use information actually present in the uploaded/pasted material — never invent discussion points, decisions, action items, or names that are not there. If a section has nothing in the source material, write exactly: "Nothing recorded." for that section.

Meeting information:
Title: {$meetingInfo['title']}
Agenda: {$meetingInfo['agenda']}
Date: {$meetingInfo['date']}
Attendees present: {$meetingInfo['attendees']}

Reply with ONLY a JSON array (no other text, no markdown fences), of exactly 5 objects in this order, each shaped {"heading": "...", "text": "..."}:
1. heading "Discussion" — important discussion points and key information raised.
2. heading "Decisions" — decisions made during the meeting.
3. heading "Action Items" — action required, person responsible, and target date if available, one per line.
4. heading "Other Matters" — other relevant matters discussed.
5. heading "Next Meeting" — date and time of the next meeting, if mentioned; otherwise "Nothing recorded."
PROMPT;

        $userContent = $contentBlocks;
        $userText = 'Meeting summary/transcript/notes to organize:';
        if ($pastedNotes !== '') {
            $userText .= "\n\n" . $pastedNotes;
        }
        $userContent[] = ['type' => 'text', 'text' => $userText];

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type' => 'application/json',
            ])->timeout(60)->post(self::API_URL, [
                'model' => self::MODEL,
                'max_tokens' => 2048,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $userContent]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('CBE meeting minutes AI draft request failed: ' . $e->getMessage());
            return ['status' => 'ERROR', 'message' => 'Could not reach the AI service right now — please try again in a moment.'];
        }

        if ($response->failed()) {
            Log::warning('CBE meeting minutes AI draft failed (' . $response->status() . '): ' . $response->body());
            return ['status' => 'ERROR', 'message' => 'The AI service could not draft the minutes right now — this looks like a temporary problem. Please try again.'];
        }

        $text = trim((string) $response->json('content.0.text'));
        // Strip stray markdown fences if the model added them anyway.
        $text = preg_replace('/^```(json)?/', '', $text);
        $text = preg_replace('/```$/', '', trim($text));

        $decoded = json_decode(trim($text), true);
        if (! is_array($decoded)) {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response — please try again.'];
        }

        $sections = [];
        foreach ($decoded as $s) {
            if (! empty($s['heading'])) {
                $sections[] = ['heading' => (string) $s['heading'], 'text' => (string) ($s['text'] ?? '')];
            }
        }
        if (empty($sections)) {
            return ['status' => 'ERROR', 'message' => 'The AI service returned an unexpected response — please try again.'];
        }

        return ['status' => 'OK', 'sections' => $sections];
    }
}
