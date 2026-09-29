<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * NEW 5 Aug 2026 — Carolyn's long-term memory about one agent. A small,
 * deliberately simple key-fact store: short notes she saves when an
 * agent shares something worth remembering (a preference, a life event,
 * ongoing context), retrieved and folded into her system prompt on every
 * future conversation so she can pick up where things left off instead
 * of starting fresh every time.
 *
 * Privacy design, on purpose: this only ever stores what the agent
 * volunteers naturally in conversation — Carolyn is instructed
 * (AiAssistantService::buildSystemPrompt) to never proactively ask for
 * health details or a family member's personal contact information.
 * Everything here is visible to the agent themselves at any time and can
 * be wiped completely with one click — see AiMemoryController.
 */
class AiMemoryService
{
    public const CATEGORIES = ['family', 'preference', 'life_event', 'work_context', 'health', 'other'];

    /** Saves one short remembered fact. Silently no-ops on an empty note. */
    public function remember(string $agentId, string $category, string $note): void
    {
        $note = trim($note);
        if ($note === '') {
            return;
        }
        if (!in_array($category, self::CATEGORIES, true)) {
            $category = 'other';
        }

        try {
            DB::table('agent_ai_memory')->insert([
                'memory_id' => Str::uuid(),
                'agent_id' => $agentId,
                'category' => $category,
                'note_encrypted' => Crypt::encryptString($note),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let a memory-save failure break the actual
            // conversation the agent is having right now.
            Log::warning('[Carolyn] Failed to save memory note', ['agent_id' => $agentId, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Everything remembered about this agent, decrypted, newest first —
     * for both the system-prompt summary and the agent's own "what
     * Carolyn remembers about me" screen.
     *
     * @return array<int, array{memory_id:string, category:string, note:string, created_at:string}>
     */
    public function all(string $agentId): array
    {
        $rows = DB::table('agent_ai_memory')
            ->where('agent_id', $agentId)
            ->orderByDesc('created_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            try {
                $out[] = [
                    'memory_id' => $row->memory_id,
                    'category' => $row->category,
                    'note' => Crypt::decryptString($row->note_encrypted),
                    'created_at' => $row->created_at,
                ];
            } catch (\Throwable $e) {
                // Skip a single unreadable row (e.g. APP_KEY rotated
                // since it was saved) rather than fail the whole list.
                continue;
            }
        }
        return $out;
    }

    /**
     * Short plain-text block for the system prompt — capped so a very
     * long relationship history never crowds out the actual
     * conversation. Newest and most-relevant facts win.
     */
    public function forSystemPrompt(string $agentId, int $limit = 25): string
    {
        $notes = array_slice($this->all($agentId), 0, $limit);
        if (empty($notes)) {
            return '';
        }

        $lines = array_map(fn($n) => "- ({$n['category']}) {$n['note']}", $notes);
        return implode("\n", $lines);
    }

    /** Self-service "forget everything Carolyn knows about me." */
    public function forgetAll(string $agentId): void
    {
        DB::table('agent_ai_memory')->where('agent_id', $agentId)->delete();
    }

    /** Deletes one specific remembered note. */
    public function forget(string $agentId, string $memoryId): void
    {
        DB::table('agent_ai_memory')->where('agent_id', $agentId)->where('memory_id', $memoryId)->delete();
    }
}
