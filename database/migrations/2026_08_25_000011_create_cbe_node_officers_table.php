<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Aug 2026 — per Chris: every node in a CBE tree (HQ, State,
// Branch, Temple, or a single-unit organization with no levels at all)
// gets its own 3 "officer" logins — Director/President, Finance/
// Treasurer, Membership/Sales — separate from GeneralLink's 3 platform
// Admin accounts (which oversee the WHOLE platform across DSG/ORG/CBE,
// not one CBE organization). One agent (a real login in the `agents`
// table) holds a role at a specific node via a row here. Kept as its
// own table rather than reusing cbe_group_memberships because holding
// an officer position is a different thing from being a rank-and-file
// member — a Temple's own Director isn't just "a member", they're the
// person with elevated dashboard access for that node.
//
// Deliberately NOT provisioned for every node at once — per Chris,
// real organizations roll out in phases (e.g. Klang's temples first,
// other branches/states/HQ later). is_active lets a position change
// hands over time without losing history (old officer rows stay,
// marked inactive, instead of being deleted).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_node_officers')) {
            Schema::create('cbe_node_officers', function (Blueprint $table) {
                $table->uuid('officer_id')->primary();
                $table->uuid('node_id');
                $table->uuid('group_label_id');
                $table->enum('role', ['DIRECTOR', 'FINANCE', 'MEMBERSHIP']);
                $table->uuid('agent_id');
                $table->boolean('is_active')->default(true);
                $table->date('appointed_at')->nullable();
                $table->timestamps();

                $table->foreign('node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->index(['node_id', 'role', 'is_active'], 'cbe_node_officers_node_role_active_idx');
                $table->index(['agent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_node_officers');
    }
};
