<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84). Lets
// each agent choose WHICH notice categories they want pushed to them
// (beyond just browsing the Notice Board) and THROUGH WHICH channels —
// per Chris: multiple choice, and the channel list shows every intended
// channel (Portal/Email/WhatsApp/Telegram/LINE/WeChat/SMS) even though
// only Portal/Email/WhatsApp can actually deliver today — so an agent
// never has to redo this once the rest are built (Task #85).
// No row for an agent = default (all categories, Portal only) — never
// blocks an agent from seeing the Notice Board itself, which is
// unaffected by this table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_notification_preferences', function (Blueprint $table) {
            $table->uuid('agent_id')->primary();
            $table->json('categories')->nullable(); // null = all categories
            $table->json('channels')->default(json_encode(['PORTAL']));
            $table->unsignedTinyInteger('frequency_cap_per_week')->nullable(); // null = no cap
            $table->timestamps();

            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_notification_preferences');
    }
};
