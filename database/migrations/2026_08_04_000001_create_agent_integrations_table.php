<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Aug 2026 — Enterprise Integration Hub, Phase 1 (task from Chris:
// "please design and implement an Enterprise Integration Hub... every
// customer must have an isolated Integration Hub... no credentials
// shared between tenants"). Clarified with Chris: this is NOT a
// multi-tenant SaaS pivot — GeneralLink stays one company. "Customer"
// here means every logged-in agent (Admin/GL/TL/Introducer), and the
// requirement is that each agent connects and pays for THEIR OWN
// account for any integration they use, never Chris's — the exact same
// principle already applied to Voice Assistant and Text Chat this week
// (see agents.voice_provider_api_key_encrypted / text_chat_api_key_encrypted).
//
// Why a new table instead of more columns on `agents`: Chris's own spec
// calls for 16 categories and roughly 80 named providers, each with a
// different credential shape (API key / Client ID+Secret / OAuth tokens
// / Bot Token / Merchant ID / etc). Adding a column per provider to
// `agents` does not scale and breaks the "add new integrations without
// modifying the core platform" requirement. Instead, one row here =
// one agent's connection to one provider. Adding a new provider later
// is a registry entry (see IntegrationConnectorResolver), not a
// migration.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_integrations', function (Blueprint $table) {
            $table->id('integration_id');
            $table->uuid('agent_id');
            $table->string('category', 60);   // e.g. 'ai_services', 'communication', 'payments'
            $table->string('provider', 60);   // e.g. 'openai', 'whatsapp', 'stripe'
            $table->string('mode', 20)->default('BYOK'); // BYOK (customer managed) | PLATFORM (shared default, rare)
            $table->string('credential_type', 30)->default('API_KEY'); // API_KEY | OAUTH2 | CLIENT_CREDENTIALS | BOT_TOKEN | MERCHANT_ID | USERNAME_PASSWORD
            $table->text('api_key_encrypted')->nullable();
            $table->text('client_id_encrypted')->nullable();
            $table->text('client_secret_encrypted')->nullable();
            $table->text('access_token_encrypted')->nullable();
            $table->text('refresh_token_encrypted')->nullable();
            $table->text('webhook_secret_encrypted')->nullable();
            $table->json('extra_config')->nullable(); // free-form: selected model, account id, region, etc — never secrets
            $table->string('status', 20)->default('DISCONNECTED'); // DISCONNECTED | CONNECTED | ERROR
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result', 255)->nullable();
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->unique(['agent_id', 'category', 'provider']);
            $table->index(['category', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_integrations');
    }
};
