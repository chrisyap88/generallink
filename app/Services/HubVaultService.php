<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;

// NEW 5 Aug 2026 — Integration Hub per-agent vault. See the migration
// comment (2026_08_05_000002_add_hub_vault_fields_to_agents.php) for the
// full design. This is the ONLY class that ever touches the vault key or
// the Hub password's derived key — every other part of the app (the
// Integration Hub controller, connectors, etc.) goes through
// encryptSecret()/decryptSecret() and never sees key material directly.
class HubVaultService
{
    private const CIPHER = 'aes-256-cbc';
    private const PBKDF2_ITERATIONS = 100000;
    private const SESSION_PREFIX = 'hub_vault_key:'; // + agent_id

    public function hasVault(string $agentId): bool
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->first(['hub_vault_key_encrypted']);
        return !empty($agent?->hub_vault_key_encrypted);
    }

    public function isUnlocked(string $agentId): bool
    {
        return Session::has(self::SESSION_PREFIX . $agentId);
    }

    /** First-time setup: generates a brand-new random vault key, locks it with the given Hub password, and unlocks it in this session. */
    public function setup(string $agentId, string $hubPassword): void
    {
        $vaultKey = random_bytes(32);
        $salt = bin2hex(random_bytes(16));
        $derivedKey = $this->deriveKey($hubPassword, $salt);

        DB::table('agents')->where('agent_id', $agentId)->update([
            'hub_password_hash' => Hash::make($hubPassword),
            'hub_vault_salt' => $salt,
            'hub_vault_key_encrypted' => $this->wrapVaultKey($vaultKey, $derivedKey),
            'hub_vault_created_at' => now(),
        ]);

        Session::put(self::SESSION_PREFIX . $agentId, base64_encode($vaultKey));
    }

    /** Returns true and unlocks the session if the password is correct; false (and does nothing) if it's wrong. */
    public function unlock(string $agentId, string $hubPassword): bool
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->first(['hub_password_hash', 'hub_vault_salt', 'hub_vault_key_encrypted']);
        if (!$agent || empty($agent->hub_password_hash) || !Hash::check($hubPassword, $agent->hub_password_hash)) {
            return false;
        }

        $derivedKey = $this->deriveKey($hubPassword, $agent->hub_vault_salt);
        try {
            $vaultKey = $this->unwrapVaultKey($agent->hub_vault_key_encrypted, $derivedKey);
        } catch (\Throwable $e) {
            // Hash matched but decrypt failed — should never happen unless
            // the row was hand-edited. Treat as wrong password rather than
            // a 500 error.
            return false;
        }

        Session::put(self::SESSION_PREFIX . $agentId, base64_encode($vaultKey));
        return true;
    }

    public function lock(string $agentId): void
    {
        Session::forget(self::SESSION_PREFIX . $agentId);
    }

    /** Agent knows their CURRENT Hub password and wants a new one — same vault key, re-wrapped. Nothing already saved is lost. */
    public function changePassword(string $agentId, string $currentPassword, string $newPassword): bool
    {
        if (!$this->unlock($agentId, $currentPassword)) {
            return false;
        }

        $vaultKey = base64_decode(Session::get(self::SESSION_PREFIX . $agentId));
        $salt = bin2hex(random_bytes(16));
        $derivedKey = $this->deriveKey($newPassword, $salt);

        DB::table('agents')->where('agent_id', $agentId)->update([
            'hub_password_hash' => Hash::make($newPassword),
            'hub_vault_salt' => $salt,
            'hub_vault_key_encrypted' => $this->wrapVaultKey($vaultKey, $derivedKey),
        ]);

        return true;
    }

    /**
     * Self-service "forgot my Hub password" reset. The OLD vault key is
     * mathematically unrecoverable at this point (that's the whole
     * point of real per-user encryption) — this generates a brand new
     * one under a brand new password. Caller MUST also wipe every row
     * in agent_integrations for this agent, since those are encrypted
     * under the now-gone old key and permanently unreadable garbage
     * from this moment on. See IntegrationHubController::resetVault().
     */
    public function resetVault(string $agentId, string $newPassword): void
    {
        $this->setup($agentId, $newPassword);
    }

    public function encryptSecret(string $agentId, string $plaintext): string
    {
        $vaultKey = $this->currentVaultKey($agentId);
        return (new Encrypter($vaultKey, self::CIPHER))->encrypt($plaintext);
    }

    public function decryptSecret(string $agentId, string $ciphertext): string
    {
        $vaultKey = $this->currentVaultKey($agentId);
        return (new Encrypter($vaultKey, self::CIPHER))->decrypt($ciphertext);
    }

    private function currentVaultKey(string $agentId): string
    {
        $stored = Session::get(self::SESSION_PREFIX . $agentId);
        if (!$stored) {
            throw new RuntimeException('Integration Hub is locked — unlock it with your Hub password first.');
        }
        return base64_decode($stored);
    }

    private function deriveKey(string $password, string $salt): string
    {
        return hash_pbkdf2('sha256', $password, $salt, self::PBKDF2_ITERATIONS, 32, true);
    }

    private function wrapVaultKey(string $vaultKey, string $derivedKey): string
    {
        return (new Encrypter($derivedKey, self::CIPHER))->encrypt(base64_encode($vaultKey));
    }

    private function unwrapVaultKey(string $wrapped, string $derivedKey): string
    {
        return base64_decode((new Encrypter($derivedKey, self::CIPHER))->decrypt($wrapped));
    }
}
