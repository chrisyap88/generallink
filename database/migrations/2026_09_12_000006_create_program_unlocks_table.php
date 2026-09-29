<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 12 Sep 2026 (Task #416) — per Chris: "you must have the unlock
// program if the user willing to subscription the paid version." Since
// real payment collection isn't built anywhere in the app yet (confirmed
// with Chris — he chose the manual-Admin-unlock option, not a payment
// gateway), this is a manual Admin on/off switch per (community, paid
// program) pair, same two-person-visible pattern as
// group_label_glade_tiers: one row per community+program, with who
// unlocked it and when. A community with no row here for a given paid
// program is simply locked (shows the crown, cannot access it).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('program_unlocks')) {
            Schema::create('program_unlocks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('group_label_id');
                $table->uuid('program_id');
                $table->uuid('unlocked_by')->nullable();
                $table->timestamp('unlocked_at')->nullable();
                $table->timestamps();

                // A community can only be unlocked once per program — a
                // second unlock action just updates the existing row.
                $table->unique(['group_label_id', 'program_id']);

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('program_id')->references('id')->on('program_catalog')->onDelete('cascade');
                $table->foreign('unlocked_by')->references('agent_id')->on('agents')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_unlocks');
    }
};
