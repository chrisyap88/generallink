<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Sep 2026 — per Chris, after being asked to advise on the AGM/
// EGM process and an AI knowledge-base assistant:
//   1) Meeting Notice + Agenda + Quorum — a SCHEDULED meeting can now
//      have a quorum_required number set, members RSVP (cbe_meeting_
//      rsvps, same shape as cbe_event_rsvps), and notice_sent_at tracks
//      whether the notice+agenda blast has gone out yet.
//   2) A named, reusable "AI Assistant" (Chris's "Gemini Gem" concept):
//      a Secretary/officer names an assistant, gives it instructions,
//      and attaches a small set of this entity's own Document
//      Repository documents (bylaws, ROS garis panduan, etc). Any
//      member can then ask it questions; answers come ONLY from the
//      attached documents, sent directly to the AI at question time —
//      no separate text-extraction/embedding step, so nothing goes
//      stale if a document is replaced with a new version.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_meeting_minutes', 'quorum_required')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->unsignedInteger('quorum_required')->nullable()->after('status');
            });
        }
        if (! Schema::hasColumn('cbe_meeting_minutes', 'notice_sent_at')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->timestamp('notice_sent_at')->nullable()->after('quorum_required');
            });
        }

        if (! Schema::hasTable('cbe_meeting_rsvps')) {
            Schema::create('cbe_meeting_rsvps', function (Blueprint $table) {
                $table->uuid('rsvp_id')->primary();
                $table->uuid('minute_id');
                $table->uuid('agent_id');
                $table->enum('response', ['GOING', 'NOT_GOING', 'MAYBE']);
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->foreign('minute_id')->references('minute_id')->on('cbe_meeting_minutes')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->unique(['minute_id', 'agent_id']);
            });
        }

        if (! Schema::hasTable('cbe_ai_assistants')) {
            Schema::create('cbe_ai_assistants', function (Blueprint $table) {
                $table->uuid('assistant_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('name', 150);
                $table->text('instructions')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id']);
            });
        }

        if (! Schema::hasTable('cbe_ai_assistant_documents')) {
            Schema::create('cbe_ai_assistant_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('assistant_id');
                $table->uuid('document_id');
                $table->timestamps();

                $table->foreign('assistant_id')->references('assistant_id')->on('cbe_ai_assistants')->onDelete('cascade');
                $table->foreign('document_id')->references('document_id')->on('cbe_documents')->onDelete('cascade');
                $table->unique(['assistant_id', 'document_id']);
            });
        }

        if (! Schema::hasTable('cbe_ai_assistant_chats')) {
            Schema::create('cbe_ai_assistant_chats', function (Blueprint $table) {
                $table->uuid('chat_id')->primary();
                $table->uuid('assistant_id');
                $table->uuid('agent_id');
                $table->text('question');
                $table->text('answer');
                $table->timestamps();

                $table->foreign('assistant_id')->references('assistant_id')->on('cbe_ai_assistants')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['assistant_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ai_assistant_chats');
        Schema::dropIfExists('cbe_ai_assistant_documents');
        Schema::dropIfExists('cbe_ai_assistants');
        Schema::dropIfExists('cbe_meeting_rsvps');

        if (Schema::hasColumn('cbe_meeting_minutes', 'notice_sent_at')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->dropColumn('notice_sent_at');
            });
        }
        if (Schema::hasColumn('cbe_meeting_minutes', 'quorum_required')) {
            Schema::table('cbe_meeting_minutes', function (Blueprint $table) {
                $table->dropColumn('quorum_required');
            });
        }
    }
};
