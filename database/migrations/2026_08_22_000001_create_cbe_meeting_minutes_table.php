<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: every CBE node (Temple, Branch, State, HQ)
// needs to keep meeting minutes as attachments, and later these get pulled
// together (with cbe_activities) into an Annual Report's Secretary
// Activity Report section. Scoped to cbe_node_id so a Temple secretary
// only ever sees their own Temple's minutes, not the whole tree's.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_meeting_minutes')) {
            Schema::create('cbe_meeting_minutes', function (Blueprint $table) {
                $table->uuid('minute_id')->primary();
                $table->uuid('cbe_node_id');
                $table->date('meeting_date');
                $table->string('title', 255);
                $table->text('summary')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'meeting_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_meeting_minutes');
    }
};
