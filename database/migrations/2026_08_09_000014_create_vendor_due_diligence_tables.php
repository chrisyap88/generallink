<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Aug 2026 — per Chris: AI-driven Due Diligence Assessment, run
// automatically once a vendor's registration (documents + initial
// validation) is submitted. Built HONEST: no invented sanctions/
// bankruptcy/court data source exists in GeneralLink today, so this only
// stores what can genuinely be checked (document name-match, the free
// public UN sanctions list, an AI negative-news scan) — every other
// angle is explicitly recorded as "unavailable / manual review required,"
// never a made-up number. Also adds vendor_review_referrals, for Admin's
// "forward to Director for further review" action on a pending vendor.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_due_diligence_assessments', function (Blueprint $table) {
            $table->uuid('assessment_id')->primary();
            $table->uuid('vendor_id');
            $table->string('status', 20)->default('PENDING'); // PENDING, COMPLETED, ERROR

            // Document identity cross-check: does the uploaded document's
            // own stated company name match what the vendor typed? A real
            // text-similarity score, not a guessed confidence number.
            $table->unsignedTinyInteger('identity_match_score')->nullable(); // 0-100
            $table->string('identity_match_note', 500)->nullable();

            // Free public UN Consolidated Sanctions List name screening.
            $table->string('sanctions_status', 20)->nullable(); // CLEAR, POTENTIAL_MATCH, UNAVAILABLE
            $table->string('sanctions_note', 500)->nullable();

            // AI negative-news scan (Claude web search tool, when available
            // on the connected API key/plan — otherwise honestly marked
            // unavailable, never guessed).
            $table->string('negative_news_status', 20)->nullable(); // CLEAR, CONCERNS_FOUND, UNAVAILABLE
            $table->text('negative_news_note')->nullable();

            $table->string('overall_recommendation', 30)->nullable(); // APPROVE, APPROVE_WITH_REVIEW, HIGH_RISK_ESCALATE, MANUAL_REVIEW_REQUIRED
            $table->text('error_message')->nullable();
            $table->timestamp('run_at')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->index(['vendor_id']);
        });

        Schema::create('vendor_review_referrals', function (Blueprint $table) {
            $table->uuid('referral_id')->primary();
            $table->uuid('vendor_id');
            $table->uuid('referred_by')->nullable();
            $table->string('director_email', 200);
            $table->string('subject', 255);
            $table->text('message');
            $table->string('status', 20)->default('SENT'); // SENT, FAILED
            $table->timestamps();

            $table->foreign('vendor_id')->references('vendor_id')->on('vendors')->cascadeOnDelete();
            $table->foreign('referred_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_review_referrals');
        Schema::dropIfExists('vendor_due_diligence_assessments');
    }
};
