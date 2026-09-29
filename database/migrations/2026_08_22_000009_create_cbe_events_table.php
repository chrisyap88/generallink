<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: Event, Donation, Sponsorship & Financial
// Management Module. cbe_events is the anchor record for a fundraising/
// celebration event (temple anniversary, deity procession, charity
// dinner, etc.) that needs full financial tracking — donors, pledges,
// receipts, expenses — not just a photo + description like
// cbe_activities. activity_id links back to the lightweight activity
// log entry when one exists, but is optional: not every Activity needs
// full financial tracking, and not every financially-tracked Event
// needs a matching Activity row.
//
// status drives the "close accounts" workflow Chris asked for:
// PLANNING (before) -> IN_PROGRESS (during) -> CLOSED (after — once
// closed, reports are final and a summary posts into the temple's own
// Income & Expenditure ledger, see cbe_transactions.event_id).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_events')) {
            Schema::create('cbe_events', function (Blueprint $table) {
                $table->uuid('event_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('activity_id')->nullable();
                $table->string('event_name', 255);
                $table->string('event_name_zh', 255)->nullable();
                $table->date('event_start_date');
                $table->date('event_end_date')->nullable();
                $table->enum('status', ['PLANNING', 'IN_PROGRESS', 'CLOSED'])->default('PLANNING');
                $table->text('description')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->uuid('closed_by')->nullable();
                $table->uuid('created_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('activity_id')->references('activity_id')->on('cbe_activities')->onDelete('set null');
                $table->foreign('closed_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->foreign('created_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_events');
    }
};
