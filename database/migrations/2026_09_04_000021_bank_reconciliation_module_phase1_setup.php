<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade to
// Chris's 23-section written spec, Phase 1 (Master File / Setup).
//
// 1.1 Bank Account Master — cbe_bank_accounts already exists and is
// GL-linked (Task #333); this only adds the 2 fields the new spec asks
// for that were missing: Branch and Description.
//
// 1.3 Bank Transaction Type — a new admin-configurable master
// (cbe_bank_transaction_types), same group_label_id-scoped pattern as
// cbe_asset_categories: never hardcoded business data. The 3 types that
// need to post to GL on their own (Bank Charge, Bank Interest, Other
// Bank Adjustment) carry a default_gl_account_id mapping (spec section
// 13); the rest (Deposit, Withdrawal, Cheque, etc.) are classification
// only — they describe what already-posted transactions were, they
// don't post anything themselves.
//
// 1.4 Reconciliation Rules — a new single-row-per-node settings table
// (cbe_bank_reconciliation_rules), mirroring the existing
// cbe_approval_settings single-row-per-node pattern exactly. Replaces
// today's hardcoded ±1 cent / ±5 day auto-match tolerance with an
// admin-configurable one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_bank_accounts', 'branch')) {
                $table->string('branch', 150)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('cbe_bank_accounts', 'description')) {
                $table->string('description', 255)->nullable()->after('signatories');
            }
        });

        if (! Schema::hasTable('cbe_bank_transaction_types')) {
            Schema::create('cbe_bank_transaction_types', function (Blueprint $table) {
                $table->uuid('type_id')->primary();
                $table->uuid('group_label_id')->nullable();
                $table->string('type_name', 60);
                $table->string('type_name_zh', 60)->nullable();
                $table->uuid('default_gl_account_id')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('display_order')->default(0);
                $table->timestamps();

                $table->foreign('default_gl_account_id')->references('account_id')->on('cbe_chart_of_accounts')->onDelete('set null');
                $table->index(['group_label_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('cbe_bank_reconciliation_rules')) {
            Schema::create('cbe_bank_reconciliation_rules', function (Blueprint $table) {
                $table->uuid('rule_id')->primary();
                $table->uuid('cbe_node_id');
                $table->decimal('amount_tolerance', 10, 2)->default(0.01);
                $table->unsignedSmallInteger('date_tolerance_days')->default(5);
                $table->boolean('match_on_reference')->default(true);
                $table->boolean('match_on_cheque_no')->default(true);
                $table->boolean('match_on_description')->default(false);
                $table->uuid('updated_by')->nullable();
                $table->timestamps();

                $table->foreign('cbe_node_id')->references('node_id')->on('cbe_hierarchy_nodes')->onDelete('cascade');
                $table->unique('cbe_node_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cbe_bank_reconciliation_rules');
        Schema::dropIfExists('cbe_bank_transaction_types');
        Schema::table('cbe_bank_accounts', function (Blueprint $table) {
            foreach (['branch', 'description'] as $col) {
                if (Schema::hasColumn('cbe_bank_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
