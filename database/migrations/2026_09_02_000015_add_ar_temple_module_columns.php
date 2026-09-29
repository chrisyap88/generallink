<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NEW 2 Sep 2026 (Task #357) — links the 3 new Master File tables into
// the existing AR tables, plus the columns Payment Allocation, Receipt
// Number Setup, and the CANCELLED-invoice fix (Task #361) need.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cbe_customers', 'category_id')) {
            Schema::table('cbe_customers', function (Blueprint $table) {
                $table->uuid('category_id')->nullable()->after('customer_name');
                $table->uuid('payment_terms_id')->nullable()->after('category_id');
                $table->foreign('category_id')->references('category_id')->on('cbe_customer_categories')->onDelete('set null');
                $table->foreign('payment_terms_id')->references('term_id')->on('cbe_payment_terms')->onDelete('set null');
            });
        }

        // CHANGED 2 Sep 2026 — each column guarded individually (not as
        // one all-or-nothing block): bank_account_id already exists on
        // this table from the earlier Bank Accounts Master migration
        // (2026_09_01_000002), so only payment_method_id and receipt_no
        // are actually new here.
        if (! Schema::hasColumn('cbe_invoice_payments', 'payment_method_id')) {
            Schema::table('cbe_invoice_payments', function (Blueprint $table) {
                $table->uuid('payment_method_id')->nullable()->after('payment_method');
                $table->foreign('payment_method_id')->references('method_id')->on('cbe_payment_methods')->onDelete('set null');
            });
        }
        if (! Schema::hasColumn('cbe_invoice_payments', 'bank_account_id')) {
            Schema::table('cbe_invoice_payments', function (Blueprint $table) {
                $table->uuid('bank_account_id')->nullable()->after('payment_method_id');
                $table->foreign('bank_account_id')->references('bank_account_id')->on('cbe_bank_accounts')->onDelete('set null');
            });
        }
        if (! Schema::hasColumn('cbe_invoice_payments', 'receipt_no')) {
            Schema::table('cbe_invoice_payments', function (Blueprint $table) {
                $table->string('receipt_no', 60)->nullable()->after('reference_no');
            });
        }

        // CHANGED 2 Sep 2026 (Task #361) — Cancel/Reverse gap fix: voiding
        // an invoice or payment journal previously never touched the AR
        // sub-ledger (cbe_invoices.status/paid_amount stayed frozen at
        // whatever they were, even after the GL reversal posted). These
        // columns let the void action record which invoice/payment it
        // reversed, so CbeAccountingService can roll the sub-ledger back
        // in the same action instead of only reversing the GL.
        if (! Schema::hasColumn('cbe_invoices', 'is_opening_balance')) {
            Schema::table('cbe_invoices', function (Blueprint $table) {
                $table->boolean('is_opening_balance')->default(false)->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cbe_invoices', 'is_opening_balance')) {
            Schema::table('cbe_invoices', function (Blueprint $table) {
                $table->dropColumn('is_opening_balance');
            });
        }
        // CHANGED 2 Sep 2026 — bank_account_id is NOT dropped here: it
        // belongs to the earlier Bank Accounts Master migration
        // (2026_09_01_000002), this migration only added a foreign key
        // reference reuse guard around it, never the column itself.
        if (Schema::hasColumn('cbe_invoice_payments', 'payment_method_id')) {
            Schema::table('cbe_invoice_payments', function (Blueprint $table) {
                $table->dropForeign(['payment_method_id']);
                $table->dropColumn('payment_method_id');
            });
        }
        if (Schema::hasColumn('cbe_invoice_payments', 'receipt_no')) {
            Schema::table('cbe_invoice_payments', function (Blueprint $table) {
                $table->dropColumn('receipt_no');
            });
        }
        if (Schema::hasColumn('cbe_customers', 'category_id')) {
            Schema::table('cbe_customers', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropForeign(['payment_terms_id']);
                $table->dropColumn(['category_id', 'payment_terms_id']);
            });
        }
    }
};
