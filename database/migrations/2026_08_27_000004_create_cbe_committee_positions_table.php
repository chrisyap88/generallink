<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 27 Aug 2026 — per Chris: Committee Structure tab (Secretarial
// Overview). NOT a separate registration — a committee position is
// assigned by picking an EXISTING Member/agent (name/photo/contact
// pulled from cbe_group_memberships/agents, not re-entered), with a
// mandatory term-of-service date range. "Current term" vs "previous
// terms" is simply a query by term_start_date/term_end_date, not two
// separate places to maintain. position_title is free text (President,
// Deputy President, Secretary, Treasurer, Committee Member, etc.) so it
// stays configurable per organization rather than a fixed enum.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_committee_positions')) {
            Schema::create('cbe_committee_positions', function (Blueprint $table) {
                $table->uuid('position_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('membership_id'); // cbe_group_memberships.membership_id — must already be an existing member
                $table->string('position_title', 100); // e.g. "President", "Secretary", "Committee Member"
                $table->unsignedInteger('sort_order')->default(0); // display order within a term (President first, etc.)
                $table->date('term_start_date');
                $table->date('term_end_date');
                $table->uuid('recorded_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('membership_id')->references('membership_id')->on('cbe_group_memberships')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index(['cbe_node_id', 'term_start_date', 'term_end_date'], 'cbe_committee_node_term_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_committee_positions');
    }
};
