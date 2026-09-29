<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Sep 2026 — per Chris: "i like to know the temple calendar what
// events their planning." The existing `calendar_events` table
// (Admin\CalendarController) is platform-wide (company holidays etc,
// Admin-only) — this is a separate, cbe_node_id-scoped calendar for one
// temple's own planned events (festivals, ceremonies, meetings), same
// read-everyone / write-officers-or-secretary access as
// cbe_temple_notices above.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_temple_calendar_events')) {
            Schema::create('cbe_temple_calendar_events', function (Blueprint $table) {
                $table->uuid('event_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('title', 150);
                $table->date('event_date');
                $table->time('event_time')->nullable();
                $table->enum('event_type', ['FESTIVAL', 'MEETING', 'CEREMONY', 'OTHER'])->default('OTHER');
                $table->text('description')->nullable();
                $table->uuid('created_by_agent_id');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('created_by_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['cbe_node_id', 'is_active', 'event_date'], 'cbe_temple_cal_node_active_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_temple_calendar_events');
    }
};
