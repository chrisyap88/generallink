<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — Configurable Rank System, Phase 4 (automatic rank
// promotion). Per Chris: rank assignment must NOT depend on Excel or a
// manual screen once the system is live — it has to work exactly like
// role promotion already does (Introducer -> Team Leader -> Group
// Leader): Admin sets criteria ONCE per rank, then the system evaluates
// every agent automatically and keeps their rank current on its own.
//
// Scoped by rank_id directly (not a separate role/group column) — a
// rank_id already fully identifies its own role AND group via
// role_ranks.role / role_ranks.group_id, so a rule naturally inherits
// that same scope with nothing extra to configure.
//
// Same 3 criteria types already used by promotion_demotion_rules
// (RECRUIT_COUNT / SALES_VOLUME / TENURE_MONTHS), PLUS a 4th new type
// for Chris's "HQ" example — TOP_N_BY_METRIC — where threshold_value
// holds N (e.g. 1 = the single highest) instead of a minimum bar, and
// whoever ranks in the top N by the chosen metric across everyone
// sharing that rank's role+group automatically holds it, shifting
// automatically if someone else overtakes them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rank_promotion_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();

            $table->uuid('rank_id'); // the rank this rule grants

            $table->enum('criteria_type', ['RECRUIT_COUNT', 'SALES_VOLUME', 'TENURE_MONTHS', 'TOP_N_BY_METRIC']);

            // RECRUIT_COUNT/TENURE_MONTHS: a minimum bar.
            // SALES_VOLUME: a minimum RM bar.
            // TOP_N_BY_METRIC: N (e.g. 1 = single highest, 3 = top 3).
            $table->decimal('threshold_value', 15, 4);

            // Used by SALES_VOLUME and TOP_N_BY_METRIC only.
            $table->enum('sales_metric', ['PREMIUM', 'EARNING_INCOME'])->nullable();
            $table->integer('sales_period_months')->nullable();

            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['rank_id', 'is_active']);

            $table->foreign('rank_id')->references('rank_id')->on('role_ranks')->cascadeOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        Schema::create('rank_promotion_rule_logic', function (Blueprint $table) {
            $table->uuid('logic_id')->primary();
            $table->uuid('rank_id')->unique();
            $table->enum('combine_logic', ['AND', 'OR'])->default('AND');
            $table->timestamps();

            $table->foreign('rank_id')->references('rank_id')->on('role_ranks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_promotion_rule_logic');
        Schema::dropIfExists('rank_promotion_rules');
    }
};
