<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// NEW 24 Jul 2026 — Configurable Promotion & Demotion Rules. Per Chris:
// wants the promotion/demotion criteria to support MULTIPLE types
// (recruit count, sales volume, tenure), not just the hardcoded "3
// direct active recruits" rule, while explicitly keeping that existing
// rule working exactly as before. Rules are GLOBAL/shared — every
// normal group uses the same criteria; Special Privilege Groups still
// skip promotion/demotion entirely via group_labels.promotion_
// demotion_enabled, unchanged (Chris: "special group no promotion and
// demotion... only apply is promotion demotion flag is yes").
//
// One row per (to_role, criteria_type) — Admin edits thresholds and
// flips is_active on/off per criterion, never adds/removes rows.
// to_role identifies the transition: TEAM_LEADER = Introducer->Team
// Leader rules, GROUP_LEADER = Team Leader->Group Leader rules.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_demotion_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->enum('from_role', ['INTRODUCER', 'TEAM_LEADER']);
            $table->enum('to_role', ['TEAM_LEADER', 'GROUP_LEADER']);
            $table->enum('criteria_type', ['RECRUIT_COUNT', 'SALES_VOLUME', 'TENURE_MONTHS']);
            $table->decimal('threshold_value', 12, 2)->default(0);
            // Only used when criteria_type = SALES_VOLUME.
            $table->enum('sales_metric', ['PREMIUM', 'EARNING_INCOME'])->nullable();
            $table->unsignedInteger('sales_period_months')->nullable();
            $table->boolean('is_active')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['to_role', 'criteria_type'], 'pdr_to_role_criteria_unique');

            $table->foreign('created_by')
                  ->references('agent_id')
                  ->on('agents')
                  ->nullOnDelete();
        });

        // Seed all 6 rows. Only the 2 RECRUIT_COUNT rows start active
        // (threshold 3) so existing behaviour is preserved exactly —
        // nothing changes for Chris until he opens the new screen and
        // deliberately turns on Sales Volume or Tenure.
        $now = now();
        DB::table('promotion_demotion_rules')->insert([
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'INTRODUCER', 'to_role' => 'TEAM_LEADER',
                'criteria_type' => 'RECRUIT_COUNT', 'threshold_value' => 3, 'sales_metric' => null,
                'sales_period_months' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'INTRODUCER', 'to_role' => 'TEAM_LEADER',
                'criteria_type' => 'SALES_VOLUME', 'threshold_value' => 0, 'sales_metric' => 'EARNING_INCOME',
                'sales_period_months' => 12, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'INTRODUCER', 'to_role' => 'TEAM_LEADER',
                'criteria_type' => 'TENURE_MONTHS', 'threshold_value' => 0, 'sales_metric' => null,
                'sales_period_months' => null, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'TEAM_LEADER', 'to_role' => 'GROUP_LEADER',
                'criteria_type' => 'RECRUIT_COUNT', 'threshold_value' => 3, 'sales_metric' => null,
                'sales_period_months' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'TEAM_LEADER', 'to_role' => 'GROUP_LEADER',
                'criteria_type' => 'SALES_VOLUME', 'threshold_value' => 0, 'sales_metric' => 'EARNING_INCOME',
                'sales_period_months' => 12, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'rule_id' => (string) Str::uuid(), 'from_role' => 'TEAM_LEADER', 'to_role' => 'GROUP_LEADER',
                'criteria_type' => 'TENURE_MONTHS', 'threshold_value' => 0, 'sales_metric' => null,
                'sales_period_months' => null, 'is_active' => false, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_demotion_rules');
    }
};
