<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REDESIGNED 21 Jul 2026 — one row per message in a Help Desk thread
// (the opening message plus every reply from either side). Attachments
// are optional, stored the same way as Document Credit's bank slip.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_messages', function (Blueprint $table) {
            $table->uuid('message_id')->primary();
            $table->uuid('thread_id');
            $table->uuid('sender_agent_id')->nullable();
            $table->text('body');
            $table->string('attachment_file_name')->nullable();
            $table->string('attachment_file_path')->nullable();
            $table->timestamps();

            $table->foreign('thread_id')->references('thread_id')->on('help_desk_threads')->onDelete('cascade');
            $table->foreign('sender_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_messages');
    }
};
