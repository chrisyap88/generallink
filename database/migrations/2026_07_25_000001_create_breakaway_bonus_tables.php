<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Breakaway Bonus. Per Chris's own explanation: when a
// Team Leader is promoted to Group Leader (e.g. Amy Tan, promoted from
// under Chris Yap), she breaks away and forms her own independent
// group. Chris Yap earns no bonus from her group until her ENTIRE group
// (her + her whole downline) hits a target amount (sales or earning
// income, whichever metric is configured) within a period — then Chris
// Yap becomes entitled to a bonus of X% of that achieved total, repeated
// every period (e.g. every year) she keeps hitting it. This is separate
// from every other Team-Leader-to-Group-Leader promotion under Chris
// Yap, each tracked on its own.
//
// Deliberately kept in its OWN tables, evaluated by its OWN command —
// never touches commission_transactions/CommissionEngine — per Chris:
// "i dont wants bugs or any error" in the earning income engine.
//
// Confirmed decisions: rules are scoped per Special Privilege Group
// (group_labels.group_label_id, NULL = System Default — same pattern as
// Promotion & Demotion Rules); this bonus is a VOUCHER + NOTICE only,
// never an automatic wallet credit — Chris files the actual claim with
// the vendor himself.
return new class extends Migration
{
    public function up(): void
    {
        // Who broke away from whom. One row per GL promotion — captures
        // the nearest active Group Leader up the OLD parent chain at the
        // moment of promotion (before parent_id was nulled out).
        Schema::create('breakaway_links', function (Blueprint $table) {
            $table->uuid('link_id')->primary();
            $table->uuid('promoted_gl_agent_id'); // e.g. Amy Tan
            $table->uuid('original_gl_agent_id'); // e.g. Chris Yap — receives the bonus
            $table->timestamp('promoted_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['original_gl_agent_id', 'is_active']);
            $table->index(['promoted_gl_agent_id', 'is_active']);

            $table->foreign('promoted_gl_agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('original_gl_agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
        });

        // Admin-configurable target/percentage, per group — same
        // add/remove-list pattern as promotion_demotion_rules, just one
        // rule set per group (not a list) since there's only one
        // breakaway target per group today.
        Schema::create('breakaway_bonus_rules', function (Blueprint $table) {
            $table->uuid('rule_id')->primary();
            $table->uuid('group_label_id')->nullable(); // NULL = System Default
            $table->enum('target_metric', ['PREMIUM', 'EARNING_INCOME']);
            $table->decimal('target_amount', 15, 2); // Y
            $table->decimal('bonus_pct', 6, 3);       // X%
            $table->unsignedInteger('period_months')->default(12);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['group_label_id', 'is_active']);

            $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->cascadeOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        // One row per period a target is actually hit — the "claim
        // voucher" record. status stays PENDING until Chris Yap marks it
        // claimed (his own bookkeeping — the real payment happens
        // outside GeneralLink, with the vendor).
        Schema::create('breakaway_bonus_claims', function (Blueprint $table) {
            $table->uuid('claim_id')->primary();
            $table->uuid('link_id');
            $table->uuid('promoted_gl_agent_id');
            $table->uuid('original_gl_agent_id');
            $table->uuid('rule_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('target_metric', ['PREMIUM', 'EARNING_INCOME']);
            $table->decimal('target_amount', 15, 2);
            $table->decimal('achieved_amount', 15, 2);
            $table->decimal('bonus_pct', 6, 3);
            $table->decimal('bonus_amount', 15, 2);
            $table->enum('status', ['PENDING', 'CLAIMED'])->default('PENDING');
            $table->timestamp('claimed_at')->nullable();
            $table->uuid('claimed_by')->nullable();
            $table->timestamps();

            $table->unique(['link_id', 'period_start'], 'bbc_link_period_unique');
            $table->index(['original_gl_agent_id', 'status']);

            $table->foreign('link_id')->references('link_id')->on('breakaway_links')->cascadeOnDelete();
            $table->foreign('promoted_gl_agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('original_gl_agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('rule_id')->references('rule_id')->on('breakaway_bonus_rules')->nullOnDelete();
            $table->foreign('claimed_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breakaway_bonus_claims');
        Schema::dropIfExists('breakaway_bonus_rules');
        Schema::dropIfExists('breakaway_links');
    }
};
