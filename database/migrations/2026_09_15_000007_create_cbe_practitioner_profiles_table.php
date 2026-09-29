<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per Chris: a practitioner (Sensei/Consultant/Legal
// Advisor) is an EXISTING Agent/Member, never a separate profile — this
// table just links a specific agent, acting as a specific practitioner
// type, to a specific temple/branch/entity (cbe_node_id), same scoping
// as Member/Consultant/Donor Maintenance. Name/phone/email are never
// copied here — always read live from the agents table.
//
// Booking rules (slot length, how many slots/day, how far ahead the
// calendar opens, how many upcoming bookings one member may hold) are
// never hardcoded — they live here per practitioner, editable by Admin
// or the practitioner, so different practitioners can run different
// schedules without any code change.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_practitioner_profiles')) {
            Schema::create('cbe_practitioner_profiles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cbe_node_id');
                $table->uuid('agent_id');
                $table->uuid('practitioner_type_id');
                $table->unsignedInteger('slot_duration_minutes')->default(30);
                $table->unsignedInteger('max_slots_per_day')->default(16);
                $table->unsignedInteger('booking_window_days')->default(60);
                $table->unsignedInteger('max_upcoming_per_member')->default(1);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
                $table->foreign('practitioner_type_id')->references('id')->on('cbe_practitioner_types')->onDelete('cascade');
                $table->unique(['cbe_node_id', 'agent_id', 'practitioner_type_id'], 'uniq_practitioner_per_node_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_practitioner_profiles');
    }
};
