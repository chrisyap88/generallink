<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 11 Sep 2026 — per Chris: a CBE community must be able to offer its
// OWN members a CHOICE of several GLADE Public Model tiers (e.g. both
// Individual and Family), not be locked onto exactly one. Replaces the
// single glade_tier_id/glade_tier_status/glade_tier_requested_by/
// glade_tier_requested_at/glade_tier_approved_by/glade_tier_approved_at
// columns on group_labels (added 2026_08_27_000014) with a proper
// many-to-many table — one row per (community, tier) pair, each with
// its OWN PENDING_APPROVAL/ACTIVE status and who requested/approved it,
// so adding a 4th or 5th tier to an already-approved community still
// goes through the same two-person approval gate, per tier, without
// disturbing the tiers already active.
//
// The old group_labels columns are left in place (NOT dropped) — purely
// a historical/rollback safety net. From this migration onward the app
// no longer reads or writes them; every community that already had a
// tier proposed or approved under the old single-value columns is
// copied forward into this table below, so nothing is lost.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('group_label_glade_tiers')) {
            Schema::create('group_label_glade_tiers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('group_label_id');
                $table->uuid('tier_id');
                $table->enum('status', ['PENDING_APPROVAL', 'ACTIVE'])->default('PENDING_APPROVAL');
                $table->uuid('requested_by')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->uuid('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();

                // One row per (community, tier) — the same tier can never
                // be linked twice to the same community.
                $table->unique(['group_label_id', 'tier_id']);

                $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->onDelete('cascade');
                $table->foreign('tier_id')->references('tier_id')->on('cbe_glade_membership_tiers')->onDelete('restrict');
                $table->foreign('requested_by')->references('agent_id')->on('agents')->nullOnDelete();
                $table->foreign('approved_by')->references('agent_id')->on('agents')->nullOnDelete();
            });

            if (Schema::hasColumn('group_labels', 'glade_tier_id')) {
                $now = now();
                $existing = DB::table('group_labels')
                    ->whereNotNull('glade_tier_id')
                    ->whereIn('glade_tier_status', ['PENDING_APPROVAL', 'ACTIVE'])
                    ->get([
                        'group_label_id', 'glade_tier_id', 'glade_tier_status',
                        'glade_tier_requested_by', 'glade_tier_requested_at',
                        'glade_tier_approved_by', 'glade_tier_approved_at',
                    ]);

                foreach ($existing as $g) {
                    DB::table('group_label_glade_tiers')->insert([
                        'id' => (string) Str::uuid(),
                        'group_label_id' => $g->group_label_id,
                        'tier_id' => $g->glade_tier_id,
                        'status' => $g->glade_tier_status,
                        'requested_by' => $g->glade_tier_requested_by,
                        'requested_at' => $g->glade_tier_requested_at,
                        'approved_by' => $g->glade_tier_approved_by,
                        'approved_at' => $g->glade_tier_approved_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('group_label_glade_tiers');
    }
};
