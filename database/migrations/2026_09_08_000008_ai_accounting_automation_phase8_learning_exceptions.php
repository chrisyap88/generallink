<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 8: AI Learning Engine + Exception
// Management Centre.
//
// The "knowledge base" itself (cbe_ai_classification_rules) already
// exists from Phase 2 — every confirmed/corrected classification
// reinforces it. What Phase 8 adds is visibility and control over it
// (a Learned Rules screen Chris can actually see and, if a rule turns
// out wrong, deactivate) plus an admin-configurable auto-post
// confidence threshold — a single-row-per-node settings table, same
// pattern as cbe_bank_reconciliation_rules, replacing the hardcoded
// "60" used throughout Phases 4-6.
//
// cbe_ai_automation_settings: one row per node, created lazily (get-
// or-default, same convention as cbe_approval_settings) rather than
// requiring an explicit setup step before the module works.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cbe_ai_automation_settings')) {
            Schema::create('cbe_ai_automation_settings', function (Blueprint $table) {
                $table->uuid('setting_id')->primary();
                $table->uuid('cbe_node_id');
                $table->unsignedTinyInteger('auto_post_confidence_threshold')->default(60);
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id', 'cbe_ai_settings_node_fk')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->unique('cbe_node_id', 'cbe_ai_settings_node_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_ai_automation_settings');
    }
};
