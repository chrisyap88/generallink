<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 12 Aug 2026 — per Chris: "it is confusing for support tick,
// carolyn ticket and help desk...no need to split into 3." Folds
// Carolyn AI Tickets into Help Desk. From today, Carolyn logs every
// ticket straight into help_desk_threads/help_desk_messages instead
// of the old ai_assistant_tickets table — Admin sees it in the same
// screen as everything else, with "Carolyn AI Agent" shown as the
// creator instead of a person's name.
//
// customer_support_tickets (the EspoCRM-mirrored, customer_id-linked
// Support Tickets screen under Customer Relationship) is DELIBERATELY
// left untouched — it needs a real customer, an EspoCRM Case, and its
// own SLA due-date tracking, none of which fits Help Desk's model.
//
// initiator_agent_id and recipient_agent_id both become nullable:
// a Carolyn-logged ticket can come from a visitor who isn't logged in
// yet (no initiator), and has no single human recipient — it's an
// Admin-queue item, not a 1-to-1 message (any current Admin can see
// and reply to it, same override principle already used for the
// video-submission Help Desk drilldown). category becomes free text
// (same reasoning already approved for customer_support_tickets.
// ticket_type) so Carolyn's own vocabulary (COMPLAINT/QUESTION/
// BUG_REPORT/OTHER) never collides with the human vocabulary
// (GENERAL/CLAIM_UPDATE/TOPUP_PAYMENT/DATA_CORRECTION).
//
// ai_assistant_tickets itself is left in place, untouched, as a
// historical record — nothing here drops it.
// -------------------------------------------------------
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_desk_threads', function (Blueprint $table) {
            $table->uuid('initiator_agent_id')->nullable()->change();
            $table->uuid('recipient_agent_id')->nullable()->change();
            $table->string('category', 30)->change();
        });

        Schema::table('help_desk_threads', function (Blueprint $table) {
            $table->string('guest_name', 200)->nullable()->after('recipient_agent_id');
            $table->string('guest_email', 200)->nullable()->after('guest_name');
            // AGENT (normal human-composed thread) or CAROLYN_AI (logged
            // automatically by the AI Assistant on someone's behalf).
            $table->string('created_by_type', 15)->default('AGENT')->after('guest_email');
            // e.g. AIT-00001 — Carolyn-created threads only, carried over
            // from the ticket code she already speaks aloud to the user.
            $table->string('thread_code', 20)->nullable()->after('created_by_type');
            // LOW/MEDIUM/HIGH — Carolyn-created threads only; blank for
            // ordinary agent-to-agent messages.
            $table->string('priority', 10)->nullable()->after('thread_code');
            $table->uuid('handled_by')->nullable()->after('priority');
            $table->timestamp('resolved_at')->nullable()->after('handled_by');
            $table->text('resolution_notes')->nullable()->after('resolved_at');

            $table->foreign('handled_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index('created_by_type');
        });

        // ---- Bring every existing Carolyn ticket across so nothing already logged is lost ----
        if (Schema::hasTable('ai_assistant_tickets')) {
            $tickets = DB::table('ai_assistant_tickets')->orderBy('created_at')->get();

            foreach ($tickets as $t) {
                $threadId = (string) Str::uuid();

                DB::table('help_desk_threads')->insert([
                    'thread_id'                   => $threadId,
                    'initiator_agent_id'          => $t->agent_id,
                    'recipient_agent_id'          => null,
                    'category'                    => $t->category,
                    'subject'                     => Str::limit($t->summary ?? 'AI Assistant ticket', 150, ''),
                    'status'                       => $t->status === 'RESOLVED' ? 'CLOSED' : 'OPEN',
                    'last_message_at'             => $t->updated_at ?? $t->created_at,
                    'last_viewed_by_initiator_at' => null,
                    'last_viewed_by_recipient_at' => null,
                    'flagged_by_initiator'        => false,
                    'flagged_by_recipient'        => false,
                    'has_attachment'              => false,
                    'guest_name'                  => $t->guest_name,
                    'guest_email'                 => $t->guest_email,
                    'created_by_type'             => 'CAROLYN_AI',
                    'thread_code'                 => $t->ticket_code,
                    'priority'                    => $t->priority,
                    'handled_by'                  => $t->handled_by,
                    'resolved_at'                 => $t->resolved_at,
                    'resolution_notes'            => $t->resolution_notes,
                    'created_at'                  => $t->created_at,
                    'updated_at'                  => $t->updated_at,
                ]);

                $body = 'Logged by Carolyn AI Agent'
                    . ($t->page_context ? " on the \"{$t->page_context}\" screen" : '')
                    . ".\n\n" . ($t->summary ?? '');

                DB::table('help_desk_messages')->insert([
                    'message_id'           => (string) Str::uuid(),
                    'thread_id'            => $threadId,
                    'sender_agent_id'      => null, // null = Carolyn AI Agent, rendered specially in the view
                    'body'                 => $body,
                    'attachment_file_name' => null,
                    'attachment_file_path' => null,
                    'created_at'           => $t->created_at,
                    'updated_at'           => $t->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Cascade via help_desk_messages.thread_id FK removes the
        // matching messages automatically.
        DB::table('help_desk_threads')->where('created_by_type', 'CAROLYN_AI')->delete();

        Schema::table('help_desk_threads', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
            $table->dropIndex(['help_desk_threads_created_by_type_index']);
            $table->dropColumn(['guest_name', 'guest_email', 'created_by_type', 'thread_code', 'priority', 'handled_by', 'resolved_at', 'resolution_notes']);
        });

        Schema::table('help_desk_threads', function (Blueprint $table) {
            $table->uuid('initiator_agent_id')->nullable(false)->change();
        });
    }
};
