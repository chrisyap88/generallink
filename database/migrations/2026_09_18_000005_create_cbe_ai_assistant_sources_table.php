<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 18 Sep 2026 — per outstanding item from Chris's "continue to
// build all as per your sequence": the AI FAQ & Answers assistant
// (cbe_ai_assistants, built 17 Sep) could only learn from a member's
// existing Document Repository files. This adds two more knowledge
// source types an officer can attach alongside documents — a plain
// web link/article (URL) or a YouTube video (its caption transcript,
// fetched best-effort). Fetched content is cached once at attach time
// (see CbeAiSourceFetchService) rather than re-fetched on every
// question — same reasoning as why the app never lets an officer edit
// an assistant after creation: keep this feature small and predictable.
// Purely additive — cbe_ai_assistant_documents/cbe_ai_assistants are
// completely untouched.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ai_assistant_sources')) {
            Schema::create('cbe_ai_assistant_sources', function (Blueprint $table) {
                $table->uuid('source_id')->primary();
                $table->uuid('assistant_id');
                $table->enum('type', ['URL', 'YOUTUBE']);
                $table->string('url', 1000);
                $table->string('title', 150);
                $table->longText('cached_content')->nullable();
                $table->enum('fetch_status', ['OK', 'FAILED'])->default('FAILED');
                $table->string('fetch_error', 255)->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();

                $table->foreign('assistant_id')->references('assistant_id')->on('cbe_ai_assistants')->onDelete('cascade');
                $table->index(['assistant_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ai_assistant_sources');
    }
};
