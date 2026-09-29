<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// NEW 17 Jul 2026 — a single Vendor + Product can produce more than
// one document layout (a Policy Schedule looks nothing like an
// Invoice, even for the same product). Matching now needs to be
// Vendor + Product + Document Type, not just Vendor + Product, using
// the same RECEIPT/SALES_INVOICE/POLICY_DOCUMENT/OTHER values the
// Sales Transaction upload form already asks the submitter to pick.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->enum('document_type', ['RECEIPT', 'SALES_INVOICE', 'POLICY_DOCUMENT', 'OTHER'])
                ->nullable()
                ->after('product_id');
        });

        // Existing templates created before this column existed default
        // to POLICY_DOCUMENT since every template calibrated so far has
        // been against insurance policy schedules.
        DB::table('document_templates')->whereNull('document_type')->update([
            'document_type' => 'POLICY_DOCUMENT',
        ]);

        Schema::table('document_templates', function (Blueprint $table) {
            $table->index(['vendor_id', 'product_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
