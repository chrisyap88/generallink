<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — per Chris: "if i chose one matrix is one matrix if
// i click 2 matric you should ask me and / or". One rule for a
// transition is unambiguous. Two or more needs an explicit choice:
// must ALL of them pass (AND), or is ANY ONE of them enough (OR). This
// stores that one choice per (group, transition) — defaults to AND,
// which is what was already live (and is the only sane default when
// there's just 1 rule, since AND/OR make no difference with only one).
// Applies to demotion automatically too, since demotion re-checks the
// exact same rule set (see HierarchyService::meetsCriteria()).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_rule_logic', function (Blueprint $table) {
            $table->uuid('logic_id')->primary();
            $table->uuid('group_label_id')->nullable(); // NULL = System Default
            $table->enum('to_role', ['TEAM_LEADER', 'GROUP_LEADER']);
            $table->enum('combine_logic', ['AND', 'OR'])->default('AND');
            $table->timestamps();

            $table->index(['group_label_id', 'to_role']);

            $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_rule_logic');
    }
};
