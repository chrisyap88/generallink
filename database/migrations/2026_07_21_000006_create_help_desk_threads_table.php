<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// REDESIGNED 21 Jul 2026 — Help Desk: hierarchy-wide internal messaging,
// not just agent-to-Admin (that was the original "Enquiries" version of
// this table, never applied — safe to redesign in place). Per Chris:
// any agent can message their own upline AND downline, however deep
// (an Introducer to their TL/GL, a GL down to any TL/Introducer in
// their group, etc.) but NEVER sideways to a peer at the same level.
// Admin sits above every chain and can message anyone, one person at a
// time (Admin does whole-company broadcasts via Notice Board instead).
// initiator_agent_id = who started the thread; recipient_agent_id =
// who it's addressed to. Either party (and Admin, when Admin is one of
// the two) can reply — see help_desk_messages. CC'd agents (see
// help_desk_cc) get read-only visibility.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('help_desk_threads', function (Blueprint $table) {
            $table->uuid('thread_id')->primary();
            $table->uuid('initiator_agent_id');
            $table->uuid('recipient_agent_id');
            $table->enum('category', ['CLAIM_UPDATE', 'TOPUP_PAYMENT', 'DATA_CORRECTION', 'GENERAL'])->default('GENERAL');
            $table->string('subject', 150);
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->timestamp('last_message_at')->useCurrent();

            // Gmail-style inbox feel, per Chris: bold/unread rows and an
            // independent flag/star for each of the 2 primary parties.
            $table->timestamp('last_viewed_by_initiator_at')->nullable();
            $table->timestamp('last_viewed_by_recipient_at')->nullable();
            $table->boolean('flagged_by_initiator')->default(false);
            $table->boolean('flagged_by_recipient')->default(false);

            // Denormalized so the inbox list can show a paperclip icon
            // without an extra join per row.
            $table->boolean('has_attachment')->default(false);

            $table->timestamps();

            $table->foreign('initiator_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->foreign('recipient_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            $table->index(['status', 'last_message_at']);
            $table->index('recipient_agent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_desk_threads');
    }
};
