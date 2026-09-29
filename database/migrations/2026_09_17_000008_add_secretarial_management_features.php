<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris's own answer when asked what Secretarial
// Management still leaves out: 7 features approved in one go ("yes
// build all this for me") — Document Repository, Announcement Blast
// (Email + in-app; real WhatsApp send is out of scope until a
// system-wide WhatsApp Business connection exists — see
// SecretarialBlastController header), Committee term-expiry alerts,
// AGM/Resolution tracking, Correspondence Register, Statutory
// compliance reminders, and Event RSVP/attendance. One migration file
// for the whole batch — every table guarded by hasTable/hasColumn so
// it's safe to run more than once.
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------
        // 1) Document Repository — Admin-editable category catalog
        //    (never hardcoded, same pattern as Ticket Categories) +
        //    the documents themselves, scoped per entity. Re-uploading
        //    under the same title+category bumps "version" and points
        //    superseded_document_id at the row it replaces, so old
        //    versions stay on file instead of being overwritten.
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_document_categories')) {
            Schema::create('cbe_document_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('label', 100);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('cbe_document_categories')->insert([
                ['id' => (string) Str::uuid(), 'code' => 'REGISTRATION_CERT', 'label' => 'Registration Certificate', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'CONSTITUTION_BYLAWS', 'label' => 'Constitution / By-Laws', 'is_system' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'FINANCIAL_STATEMENT', 'label' => 'Financial Statement', 'is_system' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'AGM_DOCUMENT', 'label' => 'AGM Document', 'is_system' => true, 'is_active' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'CORRESPONDENCE', 'label' => 'Correspondence', 'is_system' => true, 'is_active' => true, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'OTHER', 'label' => 'Other', 'is_system' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasTable('cbe_documents')) {
            Schema::create('cbe_documents', function (Blueprint $table) {
                $table->uuid('document_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('category_id')->nullable();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('file_path', 500);
                $table->string('file_original_name', 255);
                $table->unsignedInteger('version')->default(1);
                $table->uuid('superseded_document_id')->nullable();
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('category_id')->references('id')->on('cbe_document_categories')->onDelete('set null');
                $table->foreign('superseded_document_id')->references('document_id')->on('cbe_documents')->onDelete('set null');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'category_id']);
            });
        }

        // ---------------------------------------------------------
        // 2) Announcement Blast — history log of every Email+in-app
        //    broadcast the Secretary/officers sent to this entity's
        //    active members. recipient_count is a snapshot at send
        //    time (member roster changes later, count must not).
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_secretarial_blasts')) {
            Schema::create('cbe_secretarial_blasts', function (Blueprint $table) {
                $table->uuid('blast_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('subject', 200);
                $table->text('message');
                $table->unsignedInteger('recipient_count')->default(0);
                $table->uuid('sent_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('sent_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id']);
            });
        }

        // ---------------------------------------------------------
        // 3) Committee term-expiry alerts — dedup guard column only;
        //    the scheduled command reads existing cbe_committee_
        //    positions.term_end_date, no new table needed.
        // ---------------------------------------------------------
        if (! Schema::hasColumn('cbe_committee_positions', 'term_expiry_notified_at')) {
            Schema::table('cbe_committee_positions', function (Blueprint $table) {
                $table->timestamp('term_expiry_notified_at')->nullable()->after('term_end_date');
            });
        }

        // ---------------------------------------------------------
        // 4) AGM / Resolution tracking — one row per resolution put
        //    to the floor at a meeting (any meeting type, not only
        //    AGM — an EGM or Committee Meeting can pass one too).
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_meeting_resolutions')) {
            Schema::create('cbe_meeting_resolutions', function (Blueprint $table) {
                $table->uuid('resolution_id')->primary();
                $table->uuid('minute_id');
                $table->text('resolution_text');
                $table->unsignedInteger('votes_for')->default(0);
                $table->unsignedInteger('votes_against')->default(0);
                $table->unsignedInteger('votes_abstain')->default(0);
                $table->enum('outcome', ['PASSED', 'REJECTED', 'DEFERRED'])->default('PASSED');
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('minute_id')->references('minute_id')->on('cbe_meeting_minutes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['minute_id']);
            });
        }

        // ---------------------------------------------------------
        // 5) Correspondence Register — incoming/outgoing letters and
        //    official communication log, per entity.
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_correspondences')) {
            Schema::create('cbe_correspondences', function (Blueprint $table) {
                $table->uuid('correspondence_id')->primary();
                $table->uuid('cbe_node_id');
                $table->enum('direction', ['IN', 'OUT']);
                $table->string('correspondent_name', 200);
                $table->string('subject', 255);
                $table->text('summary')->nullable();
                $table->date('correspondence_date');
                $table->string('attachment_path', 500)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'correspondence_date']);
            });
        }

        // ---------------------------------------------------------
        // 6) Statutory compliance reminders — recurring per-entity
        //    obligations (annual return, AGM by X date, etc). Recurs
        //    every year on due_month/due_day; last_notified_year
        //    guards against re-notifying twice in the same year.
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_compliance_items')) {
            Schema::create('cbe_compliance_items', function (Blueprint $table) {
                $table->uuid('compliance_item_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('label', 200);
                $table->unsignedTinyInteger('due_month');
                $table->unsignedTinyInteger('due_day');
                $table->unsignedInteger('reminder_lead_days')->default(14);
                $table->unsignedInteger('last_notified_year')->nullable();
                $table->boolean('is_active')->default(true);
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'is_active']);
            });
        }

        // ---------------------------------------------------------
        // 7) Event RSVP / attendance — lightweight per-member response,
        //    distinct from cbe_event_participants (paid ticket
        //    purchases) and cbe_meeting_minute_attendees (past-meeting
        //    attendance roll). unique(event_id, agent_id) — one
        //    response per member per event, changing it just updates
        //    the same row.
        // ---------------------------------------------------------
        if (! Schema::hasTable('cbe_event_rsvps')) {
            Schema::create('cbe_event_rsvps', function (Blueprint $table) {
                $table->uuid('rsvp_id')->primary();
                $table->uuid('event_id');
                $table->uuid('agent_id');
                $table->enum('response', ['GOING', 'NOT_GOING', 'MAYBE']);
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->foreign('event_id')->references('event_id')->on('cbe_events')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->unique(['event_id', 'agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_event_rsvps');
        Schema::dropIfExists('cbe_compliance_items');
        Schema::dropIfExists('cbe_correspondences');
        Schema::dropIfExists('cbe_meeting_resolutions');

        if (Schema::hasColumn('cbe_committee_positions', 'term_expiry_notified_at')) {
            Schema::table('cbe_committee_positions', function (Blueprint $table) {
                $table->dropColumn('term_expiry_notified_at');
            });
        }

        Schema::dropIfExists('cbe_secretarial_blasts');
        Schema::dropIfExists('cbe_documents');
        Schema::dropIfExists('cbe_document_categories');
    }
};
