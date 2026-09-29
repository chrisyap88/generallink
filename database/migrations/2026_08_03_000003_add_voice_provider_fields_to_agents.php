<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 3 Aug 2026 — per-agent Voice Assistant settings. Each agent may
// optionally connect their OWN text-to-speech provider account (see
// Profile "Voice Assistant" card) so Carolyn speaks using THEIR key and
// THEIR credits — Chris's explicit instruction was that no agent's voice
// usage should ever fall back to his own account. Guests (pre-login
// pages) have no agent row at all, so they always get the free built-in
// browser voice — there is nothing to configure for them.
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('voice_provider', 20)->nullable()->after('preferred_language'); // ELEVENLABS | OPENAI | GOOGLE | null = none configured
            $table->text('voice_provider_api_key_encrypted')->nullable()->after('voice_provider'); // Crypt::encryptString(), same pattern as encrypted bank account fields
            $table->string('voice_id', 150)->nullable()->after('voice_provider_api_key_encrypted'); // provider-specific voice id/name; null = that provider's default voice
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['voice_provider', 'voice_provider_api_key_encrypted', 'voice_id']);
        });
    }
};
