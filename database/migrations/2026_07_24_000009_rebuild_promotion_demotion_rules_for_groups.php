<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 24 Jul 2026 — Reworked Promotion & Demotion Rules per Chris's
// follow-up requests:
//   1. "prihatin2u is one... rela2u is another type" — rules must be
//      scoped per Special Privilege Group (group_labels.group_label_id
//      — the SAME identity that already gates promotion_demotion_
//      enabled), not one shared set. NULL group_label_id = System
//      Default, used by Public agents and by any group that hasn't
//      defined its own rules yet ("all future new group that enable
//      promotion/demotions flag is yes" inherits this automatically).
//   2. "only 3 [criteria], can you suggest flexible more than 3" — this
//      is now an unlimited, add/remove LIST of rules per (group,
//      transition) instead of 3 fixed checkboxes. Replaces the earlier
//      2026_07_24_000008 table entirely (that version never went live
//      with real customizations, so it's safe to drop and recreate
//      rather than migrate data forward).
//   3. "if introducer plus downline achieved x sales promote TL" — the
//      Sales/Earning Income Volume criterion now sums the agent's OWN
//      production PLUS their entire downline subtree, not personal-only
//      (see HierarchyService::subtreeAgentIds()/salesVolume()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('promotion_demotion_rules');

        Schema::create('promotion_demotion_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->uuid('group_label_id')->nullable();
            $table->enum('from_role', ['INTRODUCER', 'TEAM_LEADER']);
            $table->enum('to_role', ['TEAM_LEADER', 'GROUP_LEADER']);
            $table->enum('criteria_type', ['RECRUIT_COUNT', 'SALES_VOLUME', 'TENURE_MONTHS']);
            $table->decimal('threshold_value', 15, 2)->default(0);
            // Only used when criteria_type = SALES_VOLUME.
            $table->enum('sales_metric', ['PREMIUM', 'EARNING_INCOME'])->nullable();
            $table->unsignedInteger('sales_period_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['group_label_id', 'to_role', 'is_active'], 'pdr_group_to_active_idx');

            $table->foreign('group_label_id')
                  ->references('group_label_id')
                  ->on('group_labels')
                  ->cascadeOnDelete();

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // System Default rows (group_label_id NULL) — preserves exactly
        // what was already live: 3 direct active recruits, both steps.
        // Every group without its own rules inherits these automatically.
        $now = now();
        DB::table('promotion_demotion_rules')->insert([
            [
                'rule_id' => (string) Str::uuid(), 'group_label_id' => null,
                'from_role' => 'INTRODUCER', 'to_role' => 'TEAM_LEADER',
                'criteria_type' => 'RECRUIT_COUNT', 'threshold_value' => 3,
                'sales_metric' => null, 'sales_period_months' => null,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'group_label_id' => null,
                'from_role' => 'TEAM_LEADER', 'to_role' => 'GROUP_LEADER',
                'criteria_type' => 'RECRUIT_COUNT', 'threshold_value' => 3,
                'sales_metric' => null, 'sales_period_months' => null,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_demotion_rules');
    }
};
