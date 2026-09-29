<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 31 Jul 2026 — pure audit-trail column. When a commission_transactions
// row was paid because of a personal Override Recipient Profile rule
// (agent_commission_overrides), this records exactly which rule paid it —
// so a report can clearly tell "this money went to this Regional Director
// because of Override Rule X", instead of it looking identical to an
// ordinary role/rank payout row. Additive only — every existing row gets
// NULL, nothing else changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->uuid('override_id')->nullable()->after('rank_id');
            $table->foreign('override_id')->references('override_id')->on('agent_commission_overrides')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_transactions', function (Blueprint $table) {
            $table->dropForeign(['override_id']);
            $table->dropColumn('override_id');
        });
    }
};
