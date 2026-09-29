<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 22 Aug 2026 — per Chris: activities (events, festivals, outreach,
// etc.) held by a CBE node, kept as attachments (photos, posters, event
// reports). Same shape as cbe_meeting_minutes on purpose — the Annual
// Report's Secretary Activity Report section combines both, sorted by
// date, into one Excel export.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_activities')) {
            Schema::create('cbe_activities', function (Blueprint $table) {
                $table->uuid('activity_id')->primary();
                $table->uuid('cbe_node_id');
                $table->date('activity_date');
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('attachment_path', 500)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->uuid('uploaded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('uploaded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'activity_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_activities');
    }
};
