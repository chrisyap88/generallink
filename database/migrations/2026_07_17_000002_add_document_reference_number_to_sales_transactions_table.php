<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// NEW 17 Jul 2026 — "Policy Number" only makes sense for insurance. A
// workshop invoice, a restaurant receipt, or any future vendor's document
// has its own name for this same concept (invoice number, receipt number,
// reference number). Per Chris's instruction, this becomes one generic
// "Document Reference Number" field used across every industry.
//
// Non-destructive on purpose: policy_number is NOT renamed or dropped —
// a new column is added alongside it and backfilled, so nothing that
// already reads policy_number (CommissionEngine, dashboards, reports)
// breaks. The application is updated to write both going forward;
// policy_number can be retired later once everything reads the new
// column exclusively.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('document_reference_number', 100)->nullable()->after('policy_number');
        });

        DB::table('sales_transactions')->whereNull('document_reference_number')->update([
            'document_reference_number' => DB::raw('policy_number'),
        ]);

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->index('document_reference_number');
        });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropColumn('document_reference_number');
        });
    }
};
