<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center, Phase 1 (task #210).
// Every agent already has a personal referral link/QR
// (agents.qr_code_token + /register?ref=TOKEN, wired via
// AffiliateController::lookupByToken) — that part was already built in
// an earlier session but never had a screen to view/share it from, and
// had no analytics. This table is the ONLY new thing needed: one row
// per visit to a referral link, so the Referral Link Center can show
// "X people clicked your link, Y of them registered" instead of just a
// bare QR code with no feedback loop.
//
// No new counter columns added to `agents` — click/signup counts are
// always computed live from this table (COUNT / COUNT WHERE
// converted_agent_id IS NOT NULL), so there's nothing that can ever
// drift out of sync with the real data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_clicks', function (Blueprint $table) {
            $table->uuid('click_id')->primary();
            $table->uuid('agent_id'); // whose referral link was visited
            $table->string('ip_hash', 64)->nullable(); // privacy-safe — hashed, never the raw IP
            $table->timestamp('clicked_at');
            $table->uuid('converted_agent_id')->nullable(); // set once this visit results in a completed registration
            $table->timestamps();

            $table->index(['agent_id', 'clicked_at']);

            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('converted_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_clicks');
    }
};
