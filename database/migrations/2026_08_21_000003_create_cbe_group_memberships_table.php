<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 21 Aug 2026 — per Chris's example: Chris Yap is Secretary at Tao
// Klang Association, President at Klang Charity, and MD at My Broadband
// Solution — three separate registered CBE groups, ONE person. Every
// other group type in this app assumes one agent belongs to exactly one
// group_label_id (agents.group_label_id), which is correct for DSG/ORG
// and stays untouched — but that assumption breaks for CBE the moment
// one real person can belong to more than one community.
//
// Fix: agents.group_label_id remains the agent's PRIMARY/default
// community (whichever Ecosystem Home they land on right after login,
// and what all existing group-scoped code keeps reading), while this
// table holds the FULL list of every community they belong to,
// including that same primary one. Chris Yap gets three rows here — one
// per community — all pointing at the same single agents.agent_id.
// Nothing about his name, email, or profile is ever duplicated; only
// this membership record repeats, once per community, by design.
//
// is_primary marks which row matches agents.group_label_id, so the
// Ecosystem Home switcher (letting someone flip between "which
// community am I viewing right now") knows which one to default to.
// cbe_node_id is that person's home node WITHIN this specific
// community (e.g. which Temple, for Tao Klang Association) — nullable,
// since not every community uses the temple-style tree, and not every
// member has one to pick (see agents.cbe_node_id's own migration for
// why that's allowed to be empty).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_group_memberships')) {
            Schema::create('cbe_group_memberships', function (Blueprint $table) {
                $table->uuid('membership_id')->primary();
                $table->uuid('agent_id');
                $table->uuid('group_label_id');
                $table->uuid('cbe_node_id')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
                $table->timestamp('joined_at')->nullable();
                $table->timestamps();

                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('set null');
                $table->unique(['agent_id', 'group_label_id']);
                $table->index(['group_label_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_group_memberships');
    }
};
