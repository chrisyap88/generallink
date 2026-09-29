<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 5: Automatic Posting to AP/AR.
//
// No new posting pipeline here either — CbeAccountingService::
// allocateAiPaymentToBillOrInvoice() (added alongside this migration)
// reuses postBillPayment()/postInvoicePayment() exactly as the existing
// "Pay Bill" / "Record Receipt" screens already do, including the same
// maker-checker approval gate on bill payments (a PENDING one lands in
// the same existing Pending Approvals screen Bank Reconciliation's own
// approvals already extended — no new approval queue).
//
// Deliberately conservative: a SUPPLIER/CUSTOMER line only auto-posts
// when it matches an EXISTING open (UNPAID/PARTIALLY_PAID) bill or
// invoice for that exact supplier/customer at that exact outstanding
// amount — a real document already in the system, never invented. If
// Chris doesn't use the Bills/Invoices workflow for a given payment (very
// common for direct treasurer-approved disbursements with no PO/bill
// trail), no match will be found and the line is left for manual
// entry — an honest, expected outcome, not a bug.
//
// matched_bill_id / matched_invoice_id record which document a line got
// allocated to, for the review screen and the audit trail.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_bill_id')) {
                $table->uuid('matched_bill_id')->nullable()->after('matched_donor_id');
            }
            if (! Schema::hasColumn('cbe_ai_extracted_transactions', 'matched_invoice_id')) {
                $table->uuid('matched_invoice_id')->nullable()->after('matched_bill_id');
            }
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->foreign('matched_bill_id', 'cbe_ai_extract_matched_bill_fk')->references('bill_id')->on('cbe_purchase_bills')->onDelete('set null');
            $table->foreign('matched_invoice_id', 'cbe_ai_extract_matched_invoice_fk')->references('invoice_id')->on('cbe_invoices')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropForeign('cbe_ai_extract_matched_bill_fk');
            $table->dropForeign('cbe_ai_extract_matched_invoice_fk');
        });

        Schema::table('cbe_ai_extracted_transactions', function (Blueprint $table) {
            $table->dropColumn(['matched_bill_id', 'matched_invoice_id']);
        });
    }
};
