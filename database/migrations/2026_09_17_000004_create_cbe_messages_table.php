<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Sep 2026 — see cbe_message_threads migration's comment for the
// full background. Each row is one message inside a thread; text only
// for this first version (no attachment) per the deliberately smaller
// v1 scope agreed with Chris — can be added later if needed.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_messages')) {
            Schema::create('cbe_messages', function (Blueprint $table) {
                $table->uuid('message_id')->primary();
                $table->uuid('thread_id');
                $table->uuid('sender_agent_id');
                $table->text('body');
                $table->timestamps();

                $table->foreign('thread_id')->references('thread_id')->on('cbe_message_threads')->onDelete('cascade');
                $table->foreign('sender_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index('thread_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_messages');
    }
};
