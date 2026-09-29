<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {

            // Set once at first registration, records which GL's group this agent
            // ORIGINALLY joined. NEVER overwritten by any later promotion/demotion/
            // breakaway — permanent traceability only. (Section 59)
            $table->uuid('origin_group_id')->nullable()->after('group_id');

            // Decision 6 (03 Jul 2026): when a GL is demoted, her subordinate TLs
            // (and their downlines) are reassigned to the nearest active GL above her,
            // but marked here as "displaced, not permanent". If she is promoted to GL
            // again later, PromoteAgentService checks this field to auto-reunite them
            // — unless they became a GL themselves in the meantime (existing "what
            // moves" exception, Section 59).
            $table->uuid('displaced_from_agent_id')->nullable()->after('parent_id');

            // Named successor who inherits a GL's role/responsibilities if that GL
            // becomes inactive — needed for findNearestActiveGL() fallback. (Section 59)
            $table->uuid('beneficiary_agent_id')->nullable()->after('displaced_from_agent_id');

            // Decision (03 Jul 2026, code generation review): safe, race-condition-free
            // running counter for this agent's next direct recruit's code segment.
            // Incremented atomically (SELECT ... FOR UPDATE) instead of using
            // "COUNT(children) + 1", which is unsafe under concurrent registrations
            // at scale (100k+ groups). Starts at 0; first child gets seq 1.
            $table->unsignedBigInteger('next_child_seq')->default(0)->after('recruitment_blocked');

            // Indexes for the new lookup fields
            $table->index('origin_group_id');
            $table->index('displaced_from_agent_id');
        });

        // Foreign keys — nullOnDelete so these stay safe even if a referenced
        // agent is ever removed (shouldn't happen per Decision 1, but this keeps
        // the schema defensive regardless).
        Schema::table('agents', function (Blueprint $table) {
            $table->foreign('displaced_from_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('beneficiary_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['displaced_from_agent_id']);
            $table->dropForeign(['beneficiary_agent_id']);
            $table->dropColumn([
                'origin_group_id',
                'displaced_from_agent_id',
                'beneficiary_agent_id',
                'next_child_seq',
            ]);
        });
    }
};
