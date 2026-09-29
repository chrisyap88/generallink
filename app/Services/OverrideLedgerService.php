<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// -------------------------------------------------------
// NEW 2 Aug 2026 — per Chris: a real accounting-style Debit/Credit
// ledger per Override Member (override_ledger_entries), separate from
// the calculation/workflow record (override_commission_claims). Debit
// posted when a claim is submitted, Credit posted when it's paid (or
// as a reversal when a submitted claim is rejected). Shared by
// OverrideClaimController (submit/markPaid) and ApprovalService
// (rejection reversal) so both post through the exact same voucher-
// numbering logic — no drift between the two.
// -------------------------------------------------------
class OverrideLedgerService
{
    public function post(string $memberId, ?string $claimId, string $type, string $description, float $amount, ?float $salesBasis, ?string $createdBy): string
    {
        $entryId = (string) Str::uuid();
        $voucherNumber = $this->nextVoucherNumber($type);

        DB::table('override_ledger_entries')->insert([
            'entry_id'            => $entryId,
            'override_member_id'  => $memberId,
            'claim_id'            => $claimId,
            'entry_type'          => $type,
            'voucher_number'      => $voucherNumber,
            'entry_date'          => now()->toDateString(),
            'description'         => $description,
            'sales_basis_amount'  => $salesBasis,
            'amount'              => $amount,
            'created_by'          => $createdBy,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return $voucherNumber;
    }

    // Sequential per entry_type — OVD-00001, OVD-00002... for Debits,
    // OVC-00001... for Credits (same convention as override_member_
    // code's OVM-00001 elsewhere in this feature).
    public function nextVoucherNumber(string $type): string
    {
        $prefix = $type === 'DEBIT' ? 'OVD' : 'OVC';
        $last = DB::table('override_ledger_entries')->where('entry_type', $type)->orderByDesc('created_at')->value('voucher_number');
        $nextNum = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $nextNum = ((int) $m[1]) + 1;
        }
        $code = $prefix . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        while (DB::table('override_ledger_entries')->where('voucher_number', $code)->exists()) {
            $nextNum++;
            $code = $prefix . '-' . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);
        }
        return $code;
    }
}
