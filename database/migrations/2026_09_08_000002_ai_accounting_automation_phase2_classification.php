<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 2: Rule-Based Transaction Understanding +
// Confidence.
//
// Scope confirmed with Chris earlier in this build: rule-based
// classification only (no external AI API). Two layers of rules:
//   1. Built-in generic keyword heuristics, live in code
//      (App\Services\TransactionClassificationService) — universal
//      patterns like "bank charge", "donation", "membership" — never
//      hardcoded as master DATA, same principle already used for the
//      PDF line-extraction heuristics in Phase 1.
//   2. cbe_ai_classification_rules — a per-node LEARNED rules table.
//      Every time Chris confirms or corrects a suggested classification,
//      a rule is created/reinforced here (times_confirmed / times_rejected)
//      so the same kind of transaction is recognised automatically next
//      time — this is task #60's "learned classification rules table per
//      node, so confirmed classifications apply automatically to future
//      similar transactions" requirement.
//
// cbe_ai_extracted_transactions gains: a suggested category + suggested
// transaction_type_id + confidence + source (for the audit trail — spec
// section 20 wants to see exactly why a suggestion was made), a
// confirmed_ai_category (kept separate from the suggestion so the audit
// trail can show "AI suggested X, Chris confirmed Y" rather than
// silently overwriting), and a paired_extraction_id + flag_note pair
// used by the restricted/designated-fund detector (a large credit
// matched by a same/near-same-amount debit within the same statement —
// the exact FTAM Klang RM50,000 donation-in/disbursement-out pattern
// Chris asked to be flagged automatically rather than blended into
// ordinary income/expense).
//
// Never fabricates a GL mapping: cbe_bank_transaction_types is an
// admin-configured master (Finance > Bank Transaction Types) with no
// seeded rows, so if no existing type matches the suggested category,
// suggested_transaction_type_id is simply left null rather than
// invented — the review screen shows the category only in that case.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ai_classification_rules')) {
            Schema::create('cbe_ai_classification_rules', function (Blueprint $table) {
                $table->uuid('rule_id')->primary();
                $table->uuid('cbe_node_id');
                $table->string('match_field', 20)->default('DESCRIPTION'); // DESCRIPTION / REFERENCE_NO
                $table->string('match_pattern', 255); // normalized keyword phrase learned from a confirmed line
                $table->string('direction', 10)->nullable(); // CREDIT / DEBIT / EITHER — avoids a pattern matching the wrong side
                $table->string('ai_category', 30); // SUPPLIER / CUSTOMER / DONATION / MEMBERSHIP / ASSET / TRANSFER / BANK_CHARGE / LOAN / ADJUSTMENT / OTHER
                $table->uuid('suggested_transaction_type_id')->nullable();
                $table->unsignedTinyInteger('confidence_base')->default(70);
                $table->unsignedInteger('times_confirmed')->default(1);
                $table->unsignedInteger('times_rejected')->default(0);
                $table->uuid('created_from_extraction_id')->nullable(); // informational lineage only, no FK — the source row it was first learned from
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('cbe_node_id', 'cbe_ai_rules_node_fk')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->foreign('suggested_transaction_type_id', 'cbe_ai_rules_type_fk')->references('type_id')->on('cbe_bank_transaction_types')->onDelete('set null');
                $table->unique(['cbe_node_id', 'match_field', 'match_pattern'], 'cbe_ai_rules_node_field_pattern_uniq');
                $table->index(['cbe_node_id', 'is_active'], 'cbe_ai_rules_node_active_idx');
            });
        }

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'suggested_ai_category')) {
                $table->string('suggested_ai_category', 30)->nullable()->after('extraction_confidence');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'confirmed_ai_category')) {
                $table->string('confirmed_ai_category', 30)->nullable()->after('suggested_ai_category');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'suggested_transaction_type_id')) {
                $table->uuid('suggested_transaction_type_id')->nullable()->after('confirmed_ai_category');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'classification_confidence')) {
                $table->unsignedTinyInteger('classification_confidence')->default(0)->after('suggested_transaction_type_id');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'classification_source')) {
                $table->string('classification_source', 20)->default('NONE')->after('classification_confidence'); // NONE / RULE / LEARNED / MANUAL
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_rule_id')) {
                $table->uuid('matched_rule_id')->nullable()->after('classification_source');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'paired_extraction_id')) {
                $table->uuid('paired_extraction_id')->nullable()->after('matched_rule_id'); // informal link only, no FK — the other leg of a possible restricted-fund pair
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'flag_note')) {
                $table->string('flag_note', 150)->nullable()->after('paired_extraction_id');
            }
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->foreign('suggested_transaction_type_id', 'cbe_ai_extract_sugg_type_fk')->references('type_id')->on('cbe_bank_transaction_types')->onDelete('set null');
            $table->foreign('matched_rule_id', 'cbe_ai_extract_matched_rule_fk')->references('rule_id')->on('cbe_ai_classification_rules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropForeign('cbe_ai_extract_matched_rule_fk');
            $table->dropForeign('cbe_ai_extract_sugg_type_fk');
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'suggested_ai_category',
                'confirmed_ai_category',
                'suggested_transaction_type_id',
                'classification_confidence',
                'classification_source',
                'matched_rule_id',
                'paired_extraction_id',
                'flag_note',
            ]);
        });

        Schema::dropIfExists('cbe_ai_classification_rules');
    }
};
