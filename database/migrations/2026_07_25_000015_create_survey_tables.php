<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// REPLACED 25 Jul 2026 — Survey Management Module, Phase 1 (task #227,
// #231). Chris supplied a full enterprise Survey Management spec
// (dashboard, question builder with 16 question types, conditional
// logic, multi-channel distribution, analytics, AI analysis, templates,
// permissions, system settings) and explicitly chose "scrap it, build
// toward the full spec" over keeping the earlier 4-question-type MVP.
// That earlier version's migration was never run by Chris (no
// artisan migrate yet), so this file replaces it outright rather than
// layering a second migration on top of dead tables.
//
// This is Phase 1 of a 5-phase build (see task list #231-#235):
// Phase 1 = this schema + Create Survey/Question Builder/status
// workflow. Phase 2 = distribution + response collection. Phase 3 =
// analytics/comparison/reports. Phase 4 = AI analysis/templates/
// notifications. Phase 5 = permissions/system settings.
//
// Design notes (business judgment calls, not asked of Chris per his own
// "you're the businessman, decide the details" standing instruction):
//   - No separate "respondents" master table for Phase 1 — respondent
//     identity (name/email/phone/type) is captured directly on
//     survey_responses, same convention as every other public-facing
//     form in this app (e.g. Customer Refer-a-Friend). A best-effort
//     match to an existing Customer record is still attempted by phone.
//   - survey_templates stores a JSON snapshot of the question structure
//     (not a live link to a source survey) so a template keeps working
//     even if the original survey is later edited or deleted.
//   - survey_question_logic supports single-condition skip/show rules
//     per question (documented as a Phase 1 simplification — complex
//     multi-condition AND/OR chains are deferred without being asked,
//     since Promotion Rules already has an AND/OR engine elsewhere in
//     this app and duplicating that complexity here isn't justified
//     until real usage shows it's needed).
//   - Target respondent type (Existing Customer / New Customer /
//     Prospect) is stored as a simple comma-separated string on
//     surveys (which types this survey is aimed at) and a single value
//     per survey_responses row (what the respondent said about
//     themselves) — deliberately not a new master table, since it's a
//     fixed, small, stable set of 3 values.
return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------
        // Survey Categories — simple admin-managed lookup, mirrors the
        // Customer Category / Occupation Group pattern already used
        // elsewhere (task #127).
        // -----------------------------------------------------
        Schema::create('survey_categories', function (Blueprint $table) {
            $table->uuid('category_id')->primary();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Same self-seeding convention as customer_categories/
        // occupation_groups (2026_07_19_000005) — sensible starting
        // defaults, freely editable/removable, nothing hardcoded
        // elsewhere depends on any specific one of these.
        $now = now();
        $defaultCategories = [
            'Customer Satisfaction Survey', 'Customer Experience Survey', 'Product Feedback Survey',
            'Service Feedback Survey', 'New Customer Feedback Survey', 'Prospect Feedback Survey',
            'Customer Loyalty Survey', 'Market Research', 'Other',
        ];
        foreach ($defaultCategories as $name) {
            DB::table('survey_categories')->insert([
                'category_id' => (string) Str::uuid(),
                'name'        => $name,
                'description' => null,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // -----------------------------------------------------
        // Surveys — the questionnaire shell + workflow status +
        // targeting + scheduling.
        // -----------------------------------------------------
        Schema::create('surveys', function (Blueprint $table) {
            $table->uuid('survey_id')->primary();
            $table->string('name', 200);        // internal/admin-facing name
            $table->string('title', 200);        // respondent-facing title
            $table->text('description')->nullable();
            $table->text('objective')->nullable(); // "what this survey is meant to achieve"
            $table->uuid('category_id')->nullable();
            $table->uuid('owner_agent_id')->nullable(); // who this survey is run by/for
            $table->string('language', 10)->default('en');

            // Draft, Published, Active, Paused, Closed, Archived
            $table->string('status', 20)->default('DRAFT');

            // Existing Customer / New Customer / Prospect — comma list,
            // e.g. "EXISTING_CUSTOMER,PROSPECT". Empty = all types.
            $table->string('target_respondent_types', 100)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->boolean('allow_anonymous')->default(false);
            $table->boolean('require_respondent_contact')->default(true); // ask name+phone/email

            $table->string('public_token', 64)->unique(); // never expose the raw uuid

            $table->uuid('created_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('survey_categories')->nullOnDelete();
            $table->foreign('owner_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index('status');
        });

        // -----------------------------------------------------
        // Survey Questions — supports all 16 spec question types.
        // -----------------------------------------------------
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->uuid('question_id')->primary();
            $table->uuid('survey_id');
            $table->text('question_text');
            $table->text('question_description')->nullable();
            $table->text('help_text')->nullable();

            // SINGLE_CHOICE, MULTIPLE_CHOICE, DROPDOWN, YES_NO,
            // RATING_SCALE, STAR_RATING, NUMERIC_RATING, LIKERT_SCALE,
            // SHORT_TEXT, LONG_TEXT, EMAIL, PHONE, DATE, TIME, NUMBER,
            // MATRIX
            $table->string('question_type', 30);

            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            // Validation rules (only the ones relevant to the question's
            // type are actually enforced — see PublicSurveyController).
            $table->unsignedInteger('min_length')->nullable();
            $table->unsignedInteger('max_length')->nullable();
            $table->decimal('min_value', 15, 2)->nullable();
            $table->decimal('max_value', 15, 2)->nullable();

            // RATING_SCALE / STAR_RATING / NUMERIC_RATING / LIKERT_SCALE
            $table->unsignedTinyInteger('scale_max')->nullable(); // e.g. 5 or 10

            // MATRIX only — {"rows":["...","..."],"columns":["...","..."]}
            $table->json('matrix_config')->nullable();

            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->cascadeOnDelete();
            $table->index(['survey_id', 'sort_order']);
        });

        // -----------------------------------------------------
        // Options — for SINGLE_CHOICE / MULTIPLE_CHOICE / DROPDOWN /
        // LIKERT_SCALE questions.
        // -----------------------------------------------------
        Schema::create('survey_question_options', function (Blueprint $table) {
            $table->uuid('option_id')->primary();
            $table->uuid('question_id');
            $table->string('option_text', 300);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('question_id')->references('question_id')->on('survey_questions')->cascadeOnDelete();
            $table->index(['question_id', 'sort_order']);
        });

        // -----------------------------------------------------
        // Conditional/Skip Logic — Phase 1: single-condition rules.
        // "If {source_question}'s answer {operator} {value/option},
        // then {SHOW|SKIP} {target_question}."
        // -----------------------------------------------------
        Schema::create('survey_question_logic', function (Blueprint $table) {
            $table->uuid('logic_id')->primary();
            $table->uuid('survey_id');
            $table->uuid('source_question_id'); // the question being answered
            $table->uuid('condition_option_id')->nullable(); // for choice-type conditions
            $table->string('condition_operator', 20)->nullable(); // EQUALS, NOT_EQUALS, GREATER_THAN, LESS_THAN (numeric/rating)
            $table->string('condition_value', 300)->nullable();   // numeric/text comparison value
            $table->uuid('target_question_id'); // the question affected
            $table->string('action', 10)->default('SHOW'); // SHOW or SKIP
            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->cascadeOnDelete();
            $table->foreign('source_question_id')->references('question_id')->on('survey_questions')->cascadeOnDelete();
            $table->foreign('condition_option_id')->references('option_id')->on('survey_question_options')->nullOnDelete();
            $table->foreign('target_question_id')->references('question_id')->on('survey_questions')->cascadeOnDelete();
            $table->index('survey_id');
        });

        // -----------------------------------------------------
        // Survey Templates — a portable JSON snapshot of a question
        // set, so templates keep working even if the source survey is
        // later changed or deleted.
        // -----------------------------------------------------
        Schema::create('survey_templates', function (Blueprint $table) {
            $table->uuid('template_id')->primary();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable();
            $table->json('structure'); // [{question_text, question_type, is_required, options:[...], ...}, ...]
            $table->uuid('created_by')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('category_id')->references('category_id')->on('survey_categories')->nullOnDelete();
            $table->foreign('created_by')->references('agent_id')->on('agents')->nullOnDelete();
        });

        // -----------------------------------------------------
        // Survey Invitations — one row per distribution attempt/channel
        // (Phase 2 will populate/consume this; created now so the
        // schema doesn't need a second migration when Phase 2 starts).
        // -----------------------------------------------------
        Schema::create('survey_invitations', function (Blueprint $table) {
            $table->uuid('invitation_id')->primary();
            $table->uuid('survey_id');
            $table->string('channel', 20); // LINK, QR, EMAIL, WHATSAPP, SMS, TELEGRAM, EMBED
            $table->string('recipient_identifier', 200)->nullable(); // email/phone; null for a generic link/QR
            $table->uuid('sent_by_agent_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('PENDING'); // PENDING, SENT, FAILED, OPENED, COMPLETED
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->cascadeOnDelete();
            $table->foreign('sent_by_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['survey_id', 'channel']);
        });

        // -----------------------------------------------------
        // Survey Responses.
        // -----------------------------------------------------
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->uuid('response_id')->primary();
            $table->uuid('survey_id');
            $table->uuid('invitation_id')->nullable();

            $table->boolean('is_anonymous')->default(false);
            $table->string('respondent_name', 200)->nullable();
            $table->string('respondent_email', 200)->nullable();
            $table->string('respondent_phone', 20)->nullable();
            $table->string('respondent_type', 20)->nullable(); // EXISTING_CUSTOMER, NEW_CUSTOMER, PROSPECT

            $table->uuid('customer_id')->nullable(); // best-effort match by phone
            $table->uuid('submitted_via_agent_id')->nullable(); // share-link attribution

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('completion_status', 20)->default('IN_PROGRESS'); // IN_PROGRESS, COMPLETED, ABANDONED
            $table->unsignedInteger('completion_seconds')->nullable();

            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->cascadeOnDelete();
            $table->foreign('invitation_id')->references('invitation_id')->on('survey_invitations')->nullOnDelete();
            $table->foreign('customer_id')->references('customer_id')->on('customers')->nullOnDelete();
            $table->foreign('submitted_via_agent_id')->references('agent_id')->on('agents')->nullOnDelete();
            $table->index(['survey_id', 'completion_status']);
        });

        // -----------------------------------------------------
        // Survey Answers — one row per question answered.
        // -----------------------------------------------------
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->uuid('answer_id')->primary();
            $table->uuid('response_id');
            $table->uuid('question_id');

            $table->text('answer_text')->nullable();          // SHORT/LONG_TEXT, EMAIL, PHONE
            $table->uuid('answer_option_id')->nullable();      // SINGLE_CHOICE/DROPDOWN/YES_NO
            $table->json('answer_option_ids')->nullable();     // MULTIPLE_CHOICE (array)
            $table->decimal('answer_number', 15, 2)->nullable(); // NUMBER, RATING_SCALE, STAR_RATING, NUMERIC_RATING, LIKERT_SCALE
            $table->date('answer_date')->nullable();
            $table->time('answer_time')->nullable();
            $table->json('answer_matrix')->nullable();         // MATRIX — {"row":"column", ...}

            $table->timestamps();

            $table->foreign('response_id')->references('response_id')->on('survey_responses')->cascadeOnDelete();
            $table->foreign('question_id')->references('question_id')->on('survey_questions')->cascadeOnDelete();
            $table->foreign('answer_option_id')->references('option_id')->on('survey_question_options')->nullOnDelete();
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_invitations');
        Schema::dropIfExists('survey_templates');
        Schema::dropIfExists('survey_question_logic');
        Schema::dropIfExists('survey_question_options');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
        Schema::dropIfExists('survey_categories');
    }
};
