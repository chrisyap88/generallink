<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Correspondence tab (Secretarial
// Overview) — scanned invitation cards, emails in/out, press releases,
// bulletins, event flyers/banners. Chris confirmed folder-tab layout
// with a SEPARATE screen per type, so `type` drives which folder-tab a
// row appears under. `direction` only matters for EMAIL (IN/OUT);
// nullable for the other types.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_correspondence')) {
            Schema::create('cbe_correspondence', function (Blueprint $table) {
                $table->uuid('correspondence_id')->primary();
                $table->uuid('cbe_node_id');
                $table->enum('type', ['INVITATION', 'EMAIL', 'PRESS_RELEASE', 'BULLETIN', 'FLYER_BANNER']);
                $table->enum('direction', ['IN', 'OUT'])->nullable(); // only meaningful for EMAIL
                $table->string('subject', 255);
                $table->text('description')->nullable();
                $table->date('correspondence_date');
                $table->string('attachment_path', 500)->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->uuid('recorded_by');
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('restrict');
                $table->index(['cbe_node_id', 'type', 'correspondence_date'], 'cbe_correspondence_node_type_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_correspondence');
    }
};
