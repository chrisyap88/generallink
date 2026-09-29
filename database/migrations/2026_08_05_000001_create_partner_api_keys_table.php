<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 5 Aug 2026 — Outbound Partner API. Chris asked whether GeneralLink
// can hand out ITS OWN API keys so outside systems (an insurance
// vendor's policy system, a hotel checkout/folio system, a customer's
// ERP/POS) can call INTO GeneralLink directly — the reverse direction
// from the Integration Hub (which is agents connecting OUT to other
// services with their own keys). This table is the key store for that.
//
// The raw key is shown to the Admin exactly once at creation time and
// never again — only a bcrypt hash of it is stored (same principle as
// a password, not the reversible Crypt::encryptString() pattern used
// for BYOK provider keys, since GeneralLink never needs to read this
// key back out, only verify a presented key matches it). key_prefix is
// a short, non-secret visible fragment (e.g. "gl_live_a1b2c3d4") kept
// in the clear so Admin can identify a key in the list without ever
// re-exposing the secret part.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_api_keys', function (Blueprint $table) {
            $table->id('key_id');
            $table->string('key_name', 150); // e.g. "ABC Insurance — policy sync"
            $table->string('key_prefix', 20)->unique(); // visible fragment, e.g. gl_live_a1b2c3d4
            $table->string('api_key_hash', 255); // bcrypt hash of the full key — never reversible
            $table->json('scopes'); // e.g. ["policy.read"] — which endpoints this key may call
            $table->string('status', 20)->default('ACTIVE'); // ACTIVE | REVOKED
            $table->uuid('created_by');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('agent_id')->on('agents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_api_keys');
    }
};
