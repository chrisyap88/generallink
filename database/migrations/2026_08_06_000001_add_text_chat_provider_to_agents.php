<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 6 Aug 2026 — per Chris: an agent should never have to paste the
// same API key twice (once in Integration Hub, once in Profile). Voice
// Assistant and Text Chat now both work the same way: the agent connects
// their key ONCE in Integration Hub, and Profile just lets them pick
// which connected provider to use — mirroring how "Document Reading"
// already worked (see DocumentExtractionPreferenceService). Voice already
// had a "which provider" column (`voice_provider`); Text Chat did not —
// this adds the equivalent for it. The old `text_chat_api_key_encrypted`
// column is left in place (harmless, unused going forward) rather than
// dropped, so nothing breaks if any code still references it.
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('text_chat_provider', 20)->nullable()->after('text_chat_api_key_encrypted'); // ANTHROPIC | null = use the shared default key
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('text_chat_provider');
        });
    }
};
