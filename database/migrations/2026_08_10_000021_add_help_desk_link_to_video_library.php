<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 10 Aug 2026 — per Chris: "if is raise by vendor any affiliate
// agents send though help desk" — vendors have no Help Desk access
// (confirmed: only agents can send Help Desk messages), but an
// affiliate agent (GL/TL/Introducer) genuinely CAN send a video to
// Admin that way, and Help Desk threads ARE real, structured,
// linkable records in this app (unlike vendor email/WhatsApp, which
// live outside GeneralLink entirely). This column lets Admin pick the
// ACTUAL thread when logging a Help Desk-sourced video, so the "View
// Original Message" link on the video card opens the real
// conversation instead of just a free-text note.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->uuid('source_help_desk_thread_id')->nullable()->after('source_note');
            $table->foreign('source_help_desk_thread_id')->references('thread_id')->on('help_desk_threads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('video_library', function (Blueprint $table) {
            $table->dropForeign(['source_help_desk_thread_id']);
            $table->dropColumn('source_help_desk_thread_id');
        });
    }
};
