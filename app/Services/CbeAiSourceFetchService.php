<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

// NEW 18 Sep 2026 — fetches and caches plain-text content for the two
// new AI FAQ & Answers knowledge source types: a plain web link/news
// article, or a YouTube video's caption transcript. Runs ONCE when an
// officer attaches the source (see CbeAiAssistantController::store()),
// never on every question — same "cache at attach time" reasoning as
// documented on the migration. Every result is honest about failure
// (fetch_status=FAILED + a plain-language fetch_error) rather than
// silently returning nothing — same pattern already used elsewhere in
// GeneralLink for AI features that can't always succeed (e.g. the
// vendor due-diligence "always UNAVAILABLE" checks).
class CbeAiSourceFetchService
{
    private const MAX_CHARS = 8000;

    /** @return array{status:string, content:?string, error:?string} */
    public function fetch(string $type, string $url): array
    {
        return $type === 'YOUTUBE' ? $this->fetchYoutube($url) : $this->fetchUrl($url);
    }

    private function fetchUrl(string $url): array
    {
        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; GeneralLinkBot/1.0)'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('CBE AI source URL fetch failed: ' . $e->getMessage());
            return ['status' => 'FAILED', 'content' => null, 'error' => 'Could not reach that web address. Please check the link and try again.'];
        }

        if ($response->failed()) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'That web address returned an error (HTTP ' . $response->status() . ').'];
        }

        $text = $this->htmlToText($response->body());
        if ($text === '') {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'No readable text could be found on that page.'];
        }

        return ['status' => 'OK', 'content' => mb_substr($text, 0, self::MAX_CHARS), 'error' => null];
    }

    private function fetchYoutube(string $url): array
    {
        $videoId = $this->extractYoutubeId($url);
        if (! $videoId) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'That does not look like a valid YouTube video link.'];
        }

        try {
            $watchPage = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; GeneralLinkBot/1.0)'])
                ->get('https://www.youtube.com/watch', ['v' => $videoId]);
        } catch (\Throwable $e) {
            Log::warning('CBE AI source YouTube fetch failed: ' . $e->getMessage());
            return ['status' => 'FAILED', 'content' => null, 'error' => 'Could not reach YouTube right now. Please try again in a moment.'];
        }

        if ($watchPage->failed()) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'That YouTube video could not be found or is private.'];
        }

        // Best-effort: YouTube embeds each caption track's fetch URL as
        // "baseUrl":"..." inside a captionTracks JSON blob on the watch
        // page. No official transcript API is used (none is free) — if
        // YouTube changes this markup, or the video simply has no
        // captions, this fails honestly rather than guessing.
        if (! preg_match('/"captionTracks":(\[.*?\])/', $watchPage->body(), $m)) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video does not appear to have captions available, so a transcript could not be read.'];
        }

        $tracks = json_decode($m[1], true);
        if (! is_array($tracks) || empty($tracks)) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video does not appear to have captions available, so a transcript could not be read.'];
        }

        $englishTrack = null;
        foreach ($tracks as $t) {
            if (str_starts_with($t['languageCode'] ?? '', 'en')) {
                $englishTrack = $t;
                break;
            }
        }
        $track = $englishTrack ?? $tracks[0];
        $baseUrl = str_replace('\\u0026', '&', $track['baseUrl'] ?? '');
        if (! $baseUrl) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video\'s captions could not be read.'];
        }

        try {
            $captionXml = Http::timeout(15)->get($baseUrl);
        } catch (\Throwable $e) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video\'s captions could not be downloaded.'];
        }

        if ($captionXml->failed()) {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video\'s captions could not be downloaded.'];
        }

        $text = trim(html_entity_decode(strip_tags($captionXml->body()), ENT_QUOTES));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        if ($text === '') {
            return ['status' => 'FAILED', 'content' => null, 'error' => 'This video\'s captions were empty.'];
        }

        return ['status' => 'OK', 'content' => mb_substr($text, 0, self::MAX_CHARS), 'error' => null];
    }

    private function extractYoutubeId(string $url): ?string
    {
        if (preg_match('/(?:v=|youtu\.be\/|\/embed\/|\/shorts\/)([A-Za-z0-9_-]{11})/', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    private function htmlToText(string $html): string
    {
        $html = preg_replace('/<(script|style|nav|footer|header)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
