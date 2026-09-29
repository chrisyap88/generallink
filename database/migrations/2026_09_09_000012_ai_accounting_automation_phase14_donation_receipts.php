<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 9 Sep 2026 (Task #397 follow-up) — AI-Powered Accounting
// Automation Management Module, Phase 14: Donation Dual Posting (GL +
// Official Receipt). Per Chris: "printing receipts is a MUST. ALL Ngo
// practice and all donor need a receipts" — a DONATION line already
// posts its own GL journal (Phase 4, unchanged), but until now nothing
// issued the actual Official Receipt a donor is legally/customarily
// given. This column records the OR-numbered receipt issued for that
// line, purely for display/audit — the receipt itself lives in the
// existing cbe_receipts table, same one every other collection point
// in the app already uses.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'issued_receipt_no')) {
                $table->string('issued_receipt_no', 60)->nullable()->after('ap_ar_document_created');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cbe_ai_extracted_transactions', 'issued_receipt_no')) {
                $table->dropColumn('issued_receipt_no');
            }
        });
    }
};
