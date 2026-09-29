<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// -------------------------------------------------------
// NEW 3 Aug 2026 (data-only) — one-time migration of Chris's existing,
// already-working ElevenLabs key + Jessica voice (previously a single
// GLOBAL default in .env / storage/app/carolyn-voice.json, used for
// every guest and every agent) onto his own Admin agent record
// specifically, now that voice settings are per-agent. From this point
// on it is used only when Chris himself is logged in — never spent on
// an anonymous guest or any other agent, per his explicit instruction.
// Safe to run more than once (idempotent — does nothing if the key
// isn't configured, the agent isn't found, or a value is already set).
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        $apiKey = config('services.elevenlabs.key');
        if (empty($apiKey)) {
            Log::info('[AiVoiceMigration] skipped — no ELEVENLABS_API_KEY configured in .env.');
            return;
        }

        $admin = DB::table('agents')
            ->where('role', 'ADMIN')
            ->where('email', 'chrisyapywj88@gmail.com')
            ->first();

        if (!$admin) {
            Log::info('[AiVoiceMigration] skipped — no ADMIN agent found with the expected email.');
            return;
        }

        if (!empty($admin->voice_provider)) {
            Log::info('[AiVoiceMigration] skipped — this agent already has a voice provider configured.');
            return;
        }

        $persistedVoiceId = 'cgSgspJ2msm6clMCkdW9'; // Jessica — already confirmed working via storage/app/carolyn-voice.json
        $voiceFile = storage_path('app/carolyn-voice.json');
        if (file_exists($voiceFile)) {
            $data = json_decode(file_get_contents($voiceFile), true);
            if (!empty($data['voice_id'])) {
                $persistedVoiceId = $data['voice_id'];
            }
        }

        DB::table('agents')->where('agent_id', $admin->agent_id)->update([
            'voice_provider' => 'ELEVENLABS',
            'voice_provider_api_key_encrypted' => Crypt::encryptString($apiKey),
            'voice_id' => $persistedVoiceId,
            'updated_at' => now(),
        ]);

        Log::info('[AiVoiceMigration] Chris\'s ElevenLabs key + voice attached to his Admin agent record.', ['agent_id' => $admin->agent_id]);
    }

    public function down(): void
    {
        // Intentionally a no-op — rolling back should not silently strip
        // a real, working credential an agent may have since edited.
    }
};
