<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 26 Aug 2026 — per Chris: "one of the function of accounting is to
// issue receipt upon a collection made to the temple/branch/state/HQ...
// i cannot be double standard one is upload one is key in." This service
// is the single place every collection point (donations, event sales,
// sensei appointment fees) goes through to issue an official receipt —
// no more manual receipt-number typing or photo upload for money the
// system itself collected.
//
// DONATION receipts do NOT post a new journal entry — donation income
// already flows into the formal books in one aggregate line when the
// Event closes (see EventController::close() and the CbeAccountingService
// header for why). Posting it again here would double-count that income.
// EVENT_SALE and APPOINTMENT income had no existing posting path at all
// before this, so those DO post an individual cbe_transactions row (and
// therefore a journal entry) immediately, via the exact same
// CbeAccountingService::postTransaction() every other entry uses — so
// General Ledger / Trial Balance / Balance Sheet / P&L pick it up the
// same way as anything a treasurer types in by hand.
class CbeReceiptService
{
    // $sourceType: 'DONATION' | 'EVENT_SALE' | 'APPOINTMENT'
    // Returns the new receipt row (stdClass) or null if $amount <= 0
    // (nothing was actually collected, so nothing to receipt).
    public static function issue(
        string $cbeNodeId,
        string $sourceType,
        ?string $sourceId,
        string $payerName,
        string $description,
        float $amount,
        ?string $issuedBy
    ): ?object {
        if ($amount <= 0) {
            return null;
        }

        $transactionId = null;
        if ($sourceType !== 'DONATION') {
            $transactionId = self::postCollectionIncome($cbeNodeId, $description, $amount, $issuedBy);
        }

        $receiptId = (string) Str::uuid();
        DB::table('cbe_receipts')->insert([
            'receipt_id'     => $receiptId,
            'cbe_node_id'    => $cbeNodeId,
            'receipt_no'     => self::nextReceiptNumber($cbeNodeId),
            'source_type'    => $sourceType,
            'source_id'      => $sourceId,
            'payer_name'     => $payerName,
            'description'    => $description,
            'amount'         => $amount,
            'transaction_id' => $transactionId,
            'issued_by'      => $issuedBy,
            'issued_at'      => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return DB::table('cbe_receipts')->where('receipt_id', $receiptId)->first();
    }

    // UPDATED 2 Sep 2026 (Task #337) — per Chris: a temple's official
    // receipt book needs a proper gap-free sequential number for ROS/AGM
    // audit purposes, not a per-day count that resets to 0001 every
    // morning (two receipts on different days could otherwise both read
    // "0001", which doesn't read as a real receipt book to an auditor).
    // Reuses the SAME shared, lock-protected counter every other
    // GeneralLink-assigned document number already uses (Bills, Invoices,
    // Journal Vouchers, Purchase Requests) — one continuous OR-2026-0001,
    // OR-2026-0002... series per node per calendar year, never reused,
    // never skipped even under concurrent officers issuing receipts at
    // the same moment.
    // FIXED 4 Sep 2026 (Task #391) — month is no longer just cosmetic on
    // the printed number: since the underlying counter is now keyed by
    // (node, doc_type, YEAR, MONTH), forgetting to pass it here would
    // have silently filed every receipt of the year into January's band.
    private static function nextReceiptNumber(string $cbeNodeId): string
    {
        return CbeAccountingService::nextDocumentNumber($cbeNodeId, 'RCPT', (int) now()->year, 'OR', (int) now()->month);
    }

    // Falls back to the node's own first active INCOME category — same
    // rule EventController::close() already uses for event income, so
    // behaviour stays consistent everywhere money enters the books. If a
    // temple hasn't set up any INCOME category yet (Finance > Categories),
    // the receipt still issues, it just won't post a journal line until
    // one exists — nothing is lost, since cbe_receipts keeps the full
    // record either way.
    private static function postCollectionIncome(string $cbeNodeId, string $description, float $amount, ?string $enteredBy): ?string
    {
        if (! $enteredBy) {
            return null;
        }

        $node = DB::table('cbe_hierarchy_nodes')->where('node_id', $cbeNodeId)->first();
        if (! $node) {
            return null;
        }

        $incomeCategoryId = DB::table('cbe_transaction_categories')
            ->where(function ($q) use ($node) {
                $q->whereNull('group_label_id')->orWhere('group_label_id', $node->group_label_id);
            })
            ->where('type', 'INCOME')->where('is_active', true)
            ->value('category_id');

        if (! $incomeCategoryId) {
            return null;
        }

        $transactionId = (string) Str::uuid();
        DB::table('cbe_transactions')->insert([
            'transaction_id'     => $transactionId,
            'cbe_node_id'        => $cbeNodeId,
            'bank_statement_id'  => null,
            'category_id'        => $incomeCategoryId,
            'transaction_date'   => now()->toDateString(),
            'description'        => $description,
            'amount'             => $amount,
            'entered_by'         => $enteredBy,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        CbeAccountingService::postTransaction($transactionId);

        return $transactionId;
    }
}
