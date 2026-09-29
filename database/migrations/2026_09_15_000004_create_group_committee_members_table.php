<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 15 Sep 2026 — per Chris: committee/management positions must be
// filled by picking an existing Agent/Member ("not hardcoded contact
// hp, email — information from agent/member files"), never retyped —
// this table just links a group's chosen position to that agent; phone
// and email are always read live from the agents table, never copied
// in here. A position can have more than one agent against it (e.g. a
// community with two Deputy Presidents), so this is a plain list, not
// a one-per-position lock.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_committee_members')) {
            Schema::create('group_committee_members', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('group_label_id');
                $table->uuid('position_type_id');
                $table->uuid('agent_id');
                $table->timestamps();

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('position_type_id')->references('id')->on('cbe_committee_position_types')->onDelete('cascade');
                $table->foreign('agent_id')->references('agent_id')->on('agents')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_committee_members');
    }
};
