<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 17 Sep 2026 — per Chris: "as a member of the temple/entity i like
// to manage add/edit/delete the temple noticeboard what are the event
// planned and announce by the temple." The platform-wide `notices`
// table (Admin\NoticeBoardController) broadcasts to literally every
// agent on GeneralLink — wrong audience for a single temple's own
// announcements, so this is a separate, cbe_node_id-scoped table.
// Every member of that node can read; only the node's officers
// (Director/Finance/Membership — cbe_node_officers) or its current
// Secretary (cbe_committee_positions) can post/edit/delete — see
// CbeCommitteeAuthService.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_temple_notices')) {
            Schema::create('cbe_temple_notices', function (Blueprint $table) {
                $table->uuid('notice_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('title', 150);
                $table->text('body');
                $table->enum('category', ['ANNOUNCEMENT', 'EVENT', 'GENERAL'])->default('GENERAL');
                $table->string('attachment_file_name')->nullable();
                $table->string('attachment_file_path')->nullable();
                $table->date('expires_at')->nullable();
                $table->uuid('posted_by_agent_id');
                $table->boolean('is_deleted')->default(false);
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('posted_by_agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['cbe_node_id', 'is_deleted', 'expires_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_temple_notices');
    }
};
