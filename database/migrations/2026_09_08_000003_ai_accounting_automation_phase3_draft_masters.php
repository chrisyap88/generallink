<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 3: Draft Supplier/Customer/Donor Master
// Creation.
//
// Scope confirmed with Chris earlier in this build: no live web lookups
// — when a classified line (SUPPLIER/CUSTOMER/DONATION) doesn't match an
// existing master by name, a DRAFT record is created from statement
// data alone (just a name) and flagged incomplete for Chris to finish
// later, rather than inventing address/registration/contact details
// that were never actually on the bank statement.
//
// cbe_donors already exists (Task earlier in this engagement) — no new
// master table needed for donations. is_draft (boolean, default false)
// is added to all three existing master tables (cbe_suppliers,
// cbe_customers, cbe_donors), mirroring how cbe_suppliers.is_active was
// retrofitted earlier — same pattern, new flag. created_from_extraction_id
// is informational lineage only (no FK, same convention already used on
// cbe_ai_classification_rules) so the audit trail can show which bank
// line a draft master came from.
//
// cbe_ai_extracted_transactions gains a suggested_party_name (a
// best-effort name extracted from the line's own description text —
// never fabricated, often genuinely blank for bank statements that only
// show "Payment via Cheque" with no payee) plus matched_supplier_id /
// matched_customer_id / matched_donor_id, populated only once Chris
// commits the line (exact-name match preferred over creating a
// duplicate draft; a new draft only when no existing master matches).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cbe_suppliers', 'cbe_customers', 'cbe_donors'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'is_draft')) {
                    $t->boolean('is_draft')->default(false)->after('created_by');
                }
                if (! Schema::hasColumn($table, 'created_from_extraction_id')) {
                    $t->uuid('created_from_extraction_id')->nullable()->after('is_draft');
                }
            });
        }

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'suggested_party_name')) {
                $table->string('suggested_party_name', 150)->nullable()->after('flag_note');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_supplier_id')) {
                $table->uuid('matched_supplier_id')->nullable()->after('suggested_party_name');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_customer_id')) {
                $table->uuid('matched_customer_id')->nullable()->after('matched_supplier_id');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_donor_id')) {
                $table->uuid('matched_donor_id')->nullable()->after('matched_customer_id');
            }
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->foreign('matched_supplier_id', 'cbe_ai_extract_matched_supplier_fk')->references('supplier_id')->on('cbe_suppliers')->onDelete('set null');
            $table->foreign('matched_customer_id', 'cbe_ai_extract_matched_customer_fk')->references('customer_id')->on('cbe_customers')->onDelete('set null');
            $table->foreign('matched_donor_id', 'cbe_ai_extract_matched_donor_fk')->references('donor_id')->on('cbe_donors')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropForeign('cbe_ai_extract_matched_supplier_fk');
            $table->dropForeign('cbe_ai_extract_matched_customer_fk');
            $table->dropForeign('cbe_ai_extract_matched_donor_fk');
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropColumn(['suggested_party_name', 'matched_supplier_id', 'matched_customer_id', 'matched_donor_id']);
        });

        foreach (['cbe_suppliers', 'cbe_customers', 'cbe_donors'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'created_from_extraction_id')) {
                    $t->dropColumn('created_from_extraction_id');
                }
                if (Schema::hasColumn($table, 'is_draft')) {
                    $t->dropColumn('is_draft');
                }
            });
        }
    }
};
