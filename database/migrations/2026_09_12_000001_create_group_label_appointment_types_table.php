<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 12 Sep 2026 — per Chris: "why this is hardcoded? i can add others
// like Advisor, Councilor, Consultant, in house Legal Advisor, Volunteer
// Lawyer, Medical Advisor... NOT just religion related... CBE is also
// meant for other NGO like Cooperative, Alumni, Membership Club, SME,
// Charity NGO etc and it should have multiple choice because one CBE may
// have few other position appointed in house where the member can make
// appointment to meet them."
//
// group_labels.faith_practice_type (added 2026_08_27) only let a
// community pick ONE wording set from the cbe_faith_practice_types
// catalog. That catalog was already Admin-editable (never hardcoded),
// but the SINGLE-CHOICE design was wrong for real use: a cooperative may
// want to offer BOTH a "Legal Advisor" position and a "Medical Advisor"
// position for members to book appointments with, at the same time.
//
// This table replaces the single faith_practice_type column with a
// proper many-to-many: one row per (community, position) pair. The old
// group_labels.faith_practice_type column is left in place (not
// dropped) purely as a historical/rollback safety net — same pattern as
// the group_label_glade_tiers migration on 11 Sep 2026. Every community
// that already had a position set under the old single column is
// copied forward below, so nothing already configured is lost.
return new class extends Migration
{
    public function up(): void
    {
        // FIXED 12 Sep 2026 — per Chris's error report: MySQL rejects
        // identifier names over 64 characters, and Laravel's
        // auto-generated name for this unique constraint
        // ("group_label_appointment_types_group_label_id_practice_type_id_unique")
        // is 70 — the CREATE failed partway through (table left behind
        // without its unique constraint, migration not marked as run).
        // Given an explicit short name below and, since nothing could
        // have been saved into this brand-new table by that failed
        // attempt, dropping any half-created leftover first guarantees a
        // clean retry instead of silently skipping past the missing
        // constraint and the backfill below.
        if (Schema::hasTable('group_label_appointment_types')) {
            Schema::drop('group_label_appointment_types');
        }

        Schema::create('group_label_appointment_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('group_label_id');
            $table->uuid('practice_type_id');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // One row per (community, position) — the same position
            // can never be linked twice to the same community. Named
            // explicitly (short) — MySQL's 64-char identifier limit.
            $table->unique(['group_label_id', 'practice_type_id'], 'gl_appointment_types_unique');

            $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
            $table->foreign('practice_type_id')->references('id')->on('cbe_faith_practice_types')->onDelete('restrict');
        });

        if (Schema::hasColumn('group_labels', 'faith_practice_type')) {
            $now = now();
            $existing = DB::table('group_labels')
                ->whereNotNull('faith_practice_type')
                ->where('faith_practice_type', '!=', 'NONE')
                ->get(['group_label_id', 'faith_practice_type']);

            foreach ($existing as $g) {
                $typeId = DB::table('cbe_faith_practice_types')->where('code', $g->faith_practice_type)->value('id');
                if (! $typeId) {
                    continue;
                }
                DB::table('group_label_appointment_types')->insert([
                    'id' => (string) Str::uuid(),
                    'group_label_id' => $g->group_label_id,
                    'practice_type_id' => $typeId,
                    'is_active' => true,
                    'sort_order' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_label_appointment_types');
    }
};
