<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Sep 2026 — per Chris: "in CBE it also need to have internal
// messaging, president message finance, finance reply, secretarial
// message the member/vendor ... only different is the logic dont have
// upline down line rule but messaging is a must among all cbe
// community." Same idea as the existing DSG/ORG Help Desk (Shared\
// HelpDeskController), but the addressing rule is different: per
// Chris's own answer, ANYONE may message ANYONE ELSE within the SAME
// CBE entity — no upline/downline restriction (CBE has no such
// hierarchy to begin with). A brand-new table rather than reusing
// help_desk_threads because that table's rows are governed by the
// upline/downline CC-scoping rule (DataScopeService::helpDeskCcOptionsFor)
// which does not apply here, and mixing the two would risk a CBE member
// leaking into a DSG/ORG agent's upline/downline messaging by accident.
//
// Vendor messaging is deliberately OUT of scope for this table — per
// Chris's own answer, cbe_vendors has no login of its own yet, so a
// vendor can't read/reply in-app. Officers/Secretary can still see a
// vendor's contact info (already on cbe_vendors) to follow up by phone/
// email; that's handled in the vendor screens themselves, not here.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_message_threads')) {
            Schema::create('cbe_message_threads', function (Blueprint $table) {
                $table->uuid('thread_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('subject', 150);
                $table->uuid('initiator_agent_id');
                $table->uuid('recipient_agent_id');
                $table->timestamp('initiator_read_at')->nullable();
                $table->timestamp('recipient_read_at')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('initiator_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('recipient_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['cbe_node_id', 'initiator_agent_id'], 'cbe_msg_threads_node_initiator_idx');
                $table->index(['cbe_node_id', 'recipient_agent_id'], 'cbe_msg_threads_node_recipient_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_message_threads');
    }
};
