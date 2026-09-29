<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Jul 2026 — caches the Claude translation of one Help Desk
// message into one target language. Per Chris: the agent is charged
// (with a Yes/No confirm first) only the FIRST time a message is
// translated into a given language — viewing it again after that is
// free, since the result is already sitting here. Unique on
// [message_id, target_language] enforces that at the database level,
// not just in application logic.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_message_translations', function (Blueprint $table) {
            $table->uuid('translation_id')->primary();
            $table->uuid('message_id');
            $table->enum('target_language', ['EN', 'ZH', 'MS']);
            $table->text('translated_body');
            $table->timestamps();

            $table->foreign('message_id')->references('message_id')->on('help_desk_messages')->onDelete('cascade');
            $table->unique(['message_id', 'target_language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_message_translations');
    }
};
