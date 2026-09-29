<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
// Automation Management Module, Phase 13: AP/AR Direct Document
// Creation (no-matching redesign).
//
// Per Chris: committing a SUPPLIER/CUSTOMER bank line must always
// result in a real Bill/Invoice as the original source document — no
// more leaving it as an unmatched, unposted exception. This flag
// records when that Bill/Invoice was a brand-new one the AI created
// (as opposed to an existing one it found and allocated the payment
// against), so the Exception Management Centre can still surface it —
// not because anything failed, but because a freshly auto-created
// document (posted to the generic Uncategorised Expense/Income account,
// possibly against the generic "Cash Purchase"/"Cash Sales" placeholder
// party) is exactly the kind of thing Chris should glance at and
// re-categorise, never a silent, unreviewable action.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'ap_ar_document_created')) {
                $table->boolean('ap_ar_document_created')->default(false)->after('matched_asset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_ai_extracted_transactions', 'ap_ar_document_created')) {
                $table->dropColumn('ap_ar_document_created');
            }
        });
    }
};
