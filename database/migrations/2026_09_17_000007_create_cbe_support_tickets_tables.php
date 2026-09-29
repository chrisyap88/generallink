<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 17 Sep 2026 — per Chris: "same with support tickets, member
// complaint, request for preparation of the events, request to
// purchase etc." and his own answers when asked: Secretary triages
// EVERY ticket first (never auto-assigned to a specific officer), and
// ticket categories are an Admin-editable catalog (never hardcoded) —
// same pattern as Faith Types/Practitioner Types/Notice Styles.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ticket_categories')) {
            Schema::create('cbe_ticket_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 40)->unique();
                $table->string('label', 100);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('cbe_ticket_categories')->insert([
                ['id' => (string) Str::uuid(), 'code' => 'MEMBER_COMPLAINT', 'label' => 'Member Complaint', 'is_system' => true, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'EVENT_PREP_REQUEST', 'label' => 'Event Preparation Request', 'is_system' => true, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'PURCHASE_REQUEST', 'label' => 'Purchase Request', 'is_system' => true, 'is_active' => true, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
                ['id' => (string) Str::uuid(), 'code' => 'GENERAL', 'label' => 'General', 'is_system' => true, 'is_active' => true, 'sort_order' => 99, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasTable('cbe_tickets')) {
            Schema::create('cbe_tickets', function (Blueprint $table) {
                $table->uuid('ticket_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('category_id')->nullable();
                $table->uuid('raised_by_agent_id');
                $table->string('subject', 150);
                $table->text('body');
                // OPEN = just raised, awaiting Secretary triage (per Chris:
                // "Secretary triaging everything first"). TRIAGED = Secretary
                // has reviewed/categorised/assigned it. IN_PROGRESS = being
                // worked on. RESOLVED/CLOSED = done.
                $table->enum('status', ['OPEN', 'TRIAGED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'])->default('OPEN');
                $table->uuid('triaged_by_agent_id')->nullable();
                $table->timestamp('triaged_at')->nullable();
                $table->uuid('assigned_to_agent_id')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('category_id')->references('id')->on('cbe_ticket_categories')->onDelete('set null');
                $table->foreign('raised_by_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('triaged_by_agent_id')->references('agent_id')->on('agents')->onDelete('set null');
                $table->foreign('assigned_to_agent_id')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index(['cbe_node_id', 'status', 'is_deleted'], 'cbe_tickets_node_status_idx');
                $table->index(['raised_by_agent_id'], 'cbe_tickets_raised_by_idx');
            });
        }

        if (! Schema::hasTable('cbe_ticket_replies')) {
            Schema::create('cbe_ticket_replies', function (Blueprint $table) {
                $table->uuid('reply_id')->primary();
                $table->uuid('ticket_id');
                $table->uuid('sender_agent_id');
                $table->text('body');
                $table->timestamps();

                $table->foreign('ticket_id')->references('ticket_id')->on('cbe_tickets')->onDelete('cascade');
                $table->foreign('sender_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['ticket_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ticket_replies');
        Schema::dropIfExists('cbe_tickets');
        Schema::dropIfExists('cbe_ticket_categories');
    }
};
