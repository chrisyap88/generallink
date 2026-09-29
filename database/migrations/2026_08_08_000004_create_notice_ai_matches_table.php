<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 3 (Task #90). Stores
// a one-sentence, AI-generated "why this might matter to you" blurb per
// notice per agent — generated ONCE (see AiMatchService) and reused on
// every later view, so agents never re-trigger a paid AI call just by
// revisiting the Notice Board. Only ever populated for PROMOTION-category
// notices (the "business opportunity" ones the GLADE requirement doc
// actually asked to be AI-matched); other categories never get a row.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notice_ai_matches', function (Blueprint $table) {
            $table->uuid('notice_id');
            $table->uuid('agent_id');
            $table->string('blurb', 255)->nullable(); // null = AI call failed, tried once, won't retry
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['notice_id', 'agent_id']);
            $table->foreign('notice_id')->references('notice_id')->on('notices')->onDelete('cascade');
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_ai_matches');
    }
};
