<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 5 Aug 2026 — Carolyn's cross-session memory. Until now every chat
// was stateless (history only round-tripped within one browser session —
// see AiAssistantController::chat()); this table lets her remember real
// facts an agent shares (their preferences, life events, recurring
// context) across every future conversation, so she can build a genuine
// ongoing relationship instead of meeting someone fresh every time.
//
// Deliberately encrypted the same way NRIC/bank details already are
// elsewhere in this app (Crypt::encryptString) — this is personal
// information about the agent (and sometimes their family), so it gets
// the same at-rest protection. Agents can see everything stored about
// them and wipe it entirely at any time — see AiMemoryController.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_ai_memory', function (Blueprint $table) {
            $table->uuid('memory_id')->primary();
            $table->uuid('agent_id');
            // 'family', 'preference', 'life_event', 'work_context',
            // 'health' — used only to group the display nicely; never
            // used to filter/restrict what Carolyn can see.
            $table->string('category', 30);
            $table->text('note_encrypted');
            $table->timestamps();

            $table->index('agent_id');
            $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_ai_memory');
    }
};
