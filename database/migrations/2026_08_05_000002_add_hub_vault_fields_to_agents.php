<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 5 Aug 2026 — Integration Hub, real per-agent encryption. Per
// Chris: "only the user who encrypt can decrypt" for their connected
// integration keys — not even Admin should be able to read them.
//
// Design (envelope encryption, same idea password managers use):
// - hub_vault_key_encrypted holds a random 32-byte "vault key", but
//   encrypted — never stored in readable form anywhere.
// - It's encrypted using a key DERIVED from the agent's own Hub
//   password (a 2nd password, separate from their login password) via
//   PBKDF2 + hub_vault_salt. That derived key exists only transiently
//   in server memory/session when the agent has unlocked the Hub —
//   never stored.
// - hub_password_hash is a normal bcrypt hash of the Hub password
//   itself, used only to give a fast, friendly "wrong password" check
//   before attempting the real decrypt.
// - Every individual provider secret (agent_integrations.*_encrypted)
//   is then encrypted with the decrypted VAULT KEY, not this table
//   directly — so changing the Hub password only needs to re-wrap this
//   one small vault key, never re-encrypt every saved provider secret.
//
// If an agent forgets their Hub password, the vault key is
// mathematically unrecoverable — see HubVaultService::resetVault().
// That is intentional, not a bug to fix later.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('hub_password_hash', 255)->nullable()->after('text_chat_api_key_encrypted');
            $table->string('hub_vault_salt', 64)->nullable()->after('hub_password_hash');
            $table->text('hub_vault_key_encrypted')->nullable()->after('hub_vault_salt');
            $table->timestamp('hub_vault_created_at')->nullable()->after('hub_vault_key_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['hub_password_hash', 'hub_vault_salt', 'hub_vault_key_encrypted', 'hub_vault_created_at']);
        });
    }
};
