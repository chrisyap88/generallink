<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// -------------------------------------------------------
// NEW 3 Aug 2026 — per-agent "bring your own key" for Carolyn's TEXT
// chat brain (Claude/Anthropic), same idea as the voice_provider fields
// added just before this. IMPORTANT DIFFERENCE from voice: there is no
// free client-side fallback for text generation the way there is for
// speech (the browser has a built-in voice; it has no built-in AI brain).
// So unlike voice, guests and agents who haven't connected their own key
// still need SOME real key to get a reply at all — the app falls back to
// Chris's own shared key for them (a small, genuinely cheap cost per
// message), while any agent who connects their own key takes their own
// usage off his account entirely. See AiAssistantService::chat().
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->text('text_chat_api_key_encrypted')->nullable()->after('voice_id');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('text_chat_api_key_encrypted');
        });
    }
};
