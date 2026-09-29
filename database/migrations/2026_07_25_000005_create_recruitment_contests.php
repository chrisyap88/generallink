<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 25 Jul 2026 — Growth & Outreach Center (task #211). Time-boxed
// recruitment contests, per Chris's original growth proposal: "recruit
// 3 active Introducers this month, earn X reward points". Scoped per
// Special Privilege Group (group_labels.group_label_id, NULL = System
// Default / company-wide) — same pattern as Promotion Rules and
// Breakaway Bonus. Deliberately kept in its own tables, evaluated by its
// own command, and NEVER auto-credits a wallet — same "voucher only,
// Admin marks it awarded by hand" pattern as Breakaway Bonus, per
// Chris's explicit "i dont wants bugs" priority. Never touches
// commission_transactions or CommissionEngine.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_contests', function (Blueprint $table) {
            $table->uuid('contest_id')->primary();
            $table->uuid('group_label_id')->nullable(); // NULL = company-wide
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('metric', ['RECRUIT_COUNT', 'SALES_VOLUME', 'EARNING_INCOME']);
            $table->decimal('target_value', 15, 2);
            $table->enum('reward_type', ['POINTS', 'CASH', 'DOCUMENT_CREDIT']);
            $table->decimal('reward_value', 15, 2);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['group_label_id', 'is_active']);
            $table->index(['start_date', 'end_date']);

            $table->foreign('group_label_id')->references('group_label_id')->on('group_labels')->cascadeOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        // One row per participant who actually hits a contest's target —
        // the "you won this" record. status stays PENDING until Admin
        // manually marks it awarded (crediting reward points/document
        // credit/cash happens through the existing dedicated screens for
        // those, outside this feature, same as Breakaway Bonus claims).
        Schema::create('recruitment_contest_awards', function (Blueprint $table) {
            $table->uuid('award_id')->primary();
            $table->uuid('contest_id');
            $table->uuid('agent_id');
            $table->decimal('achieved_value', 15, 2);
            $table->enum('reward_type', ['POINTS', 'CASH', 'DOCUMENT_CREDIT']);
            $table->decimal('reward_value', 15, 2);
            $table->enum('status', ['PENDING', 'AWARDED'])->default('PENDING');
            $table->timestamp('awarded_at')->nullable();
            $table->uuid('awarded_by')->nullable();
            $table->timestamps();

            $table->unique(['contest_id', 'agent_id'], 'rca_contest_agent_unique');
            $table->index(['agent_id', 'status']);

            $table->foreign('contest_id')->references('contest_id')->on('recruitment_contests')->cascadeOnDelete();
            $table->foreign('agent_id')->references('agent_id')->on('agents')->cascadeOnDelete();
            $table->foreign('awarded_by')->references('agent_id')->on('agents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_contest_awards');
        Schema::dropIfExists('recruitment_contests');
    }
};
