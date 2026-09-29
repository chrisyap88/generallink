<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Survey Management, real Google Forms integration
// (task #227 follow-up). Chris chose to switch from the in-app Question
// Builder/public response form to real Google Forms — GeneralLink now
// only creates the survey "record" (objective, category, targeting,
// distribution message) and pushes the actual questionnaire to Google
// Forms via the Forms API, then reads responses back the same way.
//
// google_connections — the OAuth token for the one Google account the
// business connects (Chris's own Google account, connected once from
// the Survey Management screen). Kept as a small table (not a single
// config row) so a future reconnect/disconnect has real history and so
// multiple rows never silently collide — only the newest is_active row
// is ever used (see GoogleFormsService::activeConnection()).
//
// Tokens are stored via Crypt::encryptString() at write time (same
// convention as every other sensitive field in this app) — see
// GoogleFormsService, never written to in plain text here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_connections', function (Blueprint $table) {
            $table->uuid('connection_id')->primary();
            $table->uuid('connected_by_agent_id')->nullable();
            $table->string('google_email', 200)->nullable();
            $table->text('access_token');   // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('connected_by_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
        });

        Schema::table('surveys', function (Blueprint $table) {
            // Present once this survey's questionnaire has been pushed
            // to Google Forms. Null = still only a GeneralLink record
            // (objective/targeting/distribution message only, no live
            // form yet).
            $table->string('google_form_id', 100)->nullable()->after('public_token');
            $table->string('google_form_edit_url', 500)->nullable()->after('google_form_id');
            $table->string('google_form_response_url', 500)->nullable()->after('google_form_edit_url');
            $table->timestamp('google_synced_at')->nullable()->after('google_form_response_url');
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['google_form_id', 'google_form_edit_url', 'google_form_response_url', 'google_synced_at']);
        });
        Schema::dropIfExists('google_connections');
    }
};
