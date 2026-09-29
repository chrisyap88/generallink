<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 23 Jul 2026 — per Chris: an option to rename the 3 system role
// labels (Group Leader / Team Leader / Introducer) for this specific
// deployment — e.g. an insurance company might prefer "Agency
// Management" / "Unit Manager" / "Agents". This does NOT touch the
// underlying `role` column on agents (GROUP_LEADER/TEAM_LEADER/
// INTRODUCER) or any permission/commission logic — it is purely a
// display-label override, resolved by App\Services\RoleLabelService.
//
// Exactly 3 rows, seeded here, never added to or deleted from — the
// Admin screen (Organization Category Maintenance) only ever EDITS
// the `label` column on these 3 fixed rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_label_overrides', function (Blueprint $table) {
            $table->string('role', 30)->primary(); // GROUP_LEADER / TEAM_LEADER / INTRODUCER
            // Capped at 20 chars — the tightest real screen (Renewal
            // Forecast filter boxes, ~95-110px wide) starts truncating
            // past roughly 14-16 characters for the label alone; 20
            // leaves headroom for the sidebar/badges/most screens while
            // still catching anything wildly long at the database
            // level as a last-resort guard (the form itself enforces
            // this too, with a live character counter).
            $table->string('label', 20);
            $table->timestamps();
        });

        DB::table('role_label_overrides')->insert([
            ['role' => 'GROUP_LEADER', 'label' => 'Group Leader', 'created_at' => now(), 'updated_at' => now()],
            ['role' => 'TEAM_LEADER',  'label' => 'Team Leader',  'created_at' => now(), 'updated_at' => now()],
            ['role' => 'INTRODUCER',   'label' => 'Introducer',   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_label_overrides');
    }
};
