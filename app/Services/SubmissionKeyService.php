<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

/**
 * NEW 18 Jul 2026 — the "encrypted key" method Chris asked for so a
 * sales transaction can be submitted by EMAIL instead of logging into
 * the website. Each agent's key is just their own agent_id, encrypted
 * with Laravel's built-in encrypter (uses APP_KEY from .env — the same
 * key that already protects NRIC numbers elsewhere in this app). No
 * new database column needed: the key is generated on demand and
 * verified by decrypting it back, which also proves it wasn't tampered
 * with (decryption fails outright if even one character is changed).
 *
 * The agent puts this key anywhere in their email's subject line (e.g.
 * "SUBMIT KEY:xxxxx") when emailing a document to admin@generallink.my
 * — see EmailIngestionController::inbound(), which is what actually
 * reads it back out.
 */
class SubmissionKeyService
{
    /**
     * Generate this agent's submission key — safe to show them
     * on-screen or regenerate any time (it always encrypts to a
     * different-looking string, but always decrypts back to the same
     * agent_id, so showing it again later is not a security problem).
     */
    public function generateFor(string $agentId): string
    {
        return Crypt::encryptString($agentId);
    }

    /**
     * Resolve a submission key back to an agent_id. Returns null if the
     * key is missing, malformed, or was tampered with — callers must
     * treat null as "reject this submission," never guess.
     */
    public function resolveAgentId(?string $key): ?string
    {
        if (empty($key)) {
            return null;
        }

        try {
            $agentId = Crypt::decryptString($key);
        } catch (\Throwable $e) {
            return null;
        }

        return $agentId ?: null;
    }

    /**
     * Pull a submission key out of free-form email subject/body text.
     * Looks for "KEY:<token>" (case-insensitive, optional space), since
     * that's the format agents are told to include. Falls back to null
     * if no such pattern is found anywhere in the given text.
     */
    public function extractKeyFromText(string $text): ?string
    {
        if (preg_match('/KEY\s*:\s*([A-Za-z0-9+\/=]+)/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
