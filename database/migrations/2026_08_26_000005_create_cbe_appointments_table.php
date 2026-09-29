<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 26 Aug 2026, 20th pass — per Chris: "any appointment with temple
// for prayer and advise from sensei (temple resident advisor for
// counseling purpose etc), all this is important to the temple/club."
// One row per appointment. Per Chris's answers: the advisor ("sensei")
// must be an existing agent account (not a separate free-text name
// register) — in practice, one of this temple's own registered
// members (cbe_group_memberships) — and this is a LOG of appointments
// already held/arranged, not a forward booking calendar with time
// slots. Either agent_id OR customer_id is set (never both), matching
// the same convention as cbe_event_participants — the person seen can
// be a Member (agent) or a Customer.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_appointments')) {
            Schema::create('cbe_appointments', function (Blueprint $table) {
                $table->uuid('appointment_id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('advisor_id'); // agents.agent_id — the sensei/advisor seeing this person
                $table->uuid('agent_id')->nullable();
                $table->uuid('customer_id')->nullable();
                $table->enum('appointment_type', ['PRAYER', 'COUNSELING', 'BLESSING', 'OTHER']);
                $table->date('appointment_date');
                $table->text('notes')->nullable();
                $table->uuid('recorded_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('advisor_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('customer_id')->references('customer_id')->on('customers')->onDelete('cascade');
                $table->foreign('recorded_by')->references('agent_id')->on('agents')->onDelete('set null');
                $table->index(['cbe_node_id', 'appointment_date']);
                $table->index('advisor_id');
                $table->index('agent_id');
                $table->index('customer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_appointments');
    }
};
