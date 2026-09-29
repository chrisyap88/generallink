<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

// NEW 8 Sep 2026 (Task #397) — AI-Powered Accounting Automation
// Management Module, Phase 7: Multi-Year Sequential Processing.
//
// Scoped with Chris via AskUserQuestion before building: this is
// deliberately the narrow, AI-specific slice — checking that
// sequential statement uploads (FY2024 -> FY2025 -> FY2026) are
// actually continuous — not a general accruals/prepayments/suspense-
// account year-end checklist, which he asked to defer as a separate
// request since it's a general-ledger concern unrelated to bank-
// statement ingestion, and this app already has a working Year-End
// Closing screen for that.
//
// Both checks below are advisory only — they write a note Chris can
// see, but never block review or commit. Nothing here invents a
// number: the "expected" balance is computed purely from what's
// already actually posted to this bank account's own GL account plus
// its configured opening balance, exactly the books' own arithmetic.
class StatementContinuityService
{
    /**
     * Runs both continuity checks for one just-parsed document and
     * records the result on cbe_ai_statement_documents.
     */
    public function checkContinuity(string $documentId, string $nodeId, ?string $groupLabelId, ?string $bankAccountId): void
    {
        $document = DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->first();
        if (! $document || ! $document->statement_period_from || $document->detected_opening_balance === null) {
            return; // nothing to check without a real detected period + opening balance
        }
        if (! $bankAccountId) {
            return; // Phase 12: no confidently-identified bank account yet — nothing to compare against
        }

        $notes = [];

        $expected = $this->expectedBalanceAsOf($bankAccountId, $nodeId, $groupLabelId, $document->statement_period_from);
        $variance = round((float) $document->detected_opening_balance - $expected, 2);
        // FIXED 17 Sep 2026 — per Chris: this warning was showing up on
        // the very first statement ever uploaded for a bank account,
        // saying the opening balance was "RM X higher than what the
        // books show (expected RM 0.00)" — but RM 0.00 wasn't a real
        // expectation, it's just an empty, never-configured account.
        // Comparing against a books balance only makes sense once
        // there's an actual books balance to compare against — a
        // configured opening balance on the Bank Account, or at least
        // one journal entry already posted before this period. Without
        // either, this check is skipped entirely rather than showing a
        // misleading "mismatch" against nothing.
        if (abs($variance) > 1.00 && $this->hasComparableBaseline($bankAccountId, $nodeId, $groupLabelId, $document->statement_period_from)) {
            $notes[] = __('cbe_ai.continuity_opening_mismatch', [
                'expected' => number_format($expected, 2),
                'variance' => number_format(abs($variance), 2),
                'direction' => $variance > 0 ? __('cbe_ai.continuity_higher') : __('cbe_ai.continuity_lower'),
            ]);
        }

        $priorPeriodTo = DB::table('cbe_ai_statement_documents as d')
            ->where('d.bank_account_id', $bankAccountId)
            ->where('d.document_id', '!=', $documentId)
            ->whereNotNull('d.statement_period_to')
            ->where('d.statement_period_to', '<', $document->statement_period_from)
            ->orderByDesc('d.statement_period_to')
            ->value('d.statement_period_to');

        if ($priorPeriodTo) {
            $gapStart = \Carbon\Carbon::parse($priorPeriodTo)->addDay();
            $gapEnd = \Carbon\Carbon::parse($document->statement_period_from)->subDay();
            if ($gapStart->lte($gapEnd) && $gapStart->diffInDays($gapEnd) >= 3) {
                $notes[] = __('cbe_ai.continuity_gap_detected', [
                    'from' => $gapStart->format('d M Y'),
                    'to' => $gapEnd->format('d M Y'),
                ]);
            }
        }

        DB::table('cbe_ai_statement_documents')->where('document_id', $documentId)->update([
            'opening_balance_expected' => $expected,
            'opening_balance_variance' => $variance,
            'continuity_note' => $notes ? implode(' ', $notes) : null,
            'updated_at' => now(),
        ]);
    }

    /**
     * The bank account's own configured opening balance, plus every
     * journal line actually posted to that account's GL account between
     * that opening date and the day before $asOfDate — i.e. exactly
     * what the books themselves say the balance should be right before
     * this statement's period starts. Never a guess.
     */
    private function expectedBalanceAsOf(string $bankAccountId, string $nodeId, ?string $groupLabelId, string $asOfDate): float
    {
        $bankAccount = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->first();
        $glAccountId = CbeAccountingService::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);

        $openingBalance = (float) ($bankAccount->opening_balance ?? 0);
        $openingDate = $bankAccount->opening_balance_date ?? null;

        $sums = DB::table('cbe_journal_lines as jl')
            ->join('cbe_journal_entries as je', 'je.journal_id', '=', 'jl.journal_id')
            ->where('jl.account_id', $glAccountId)
            ->when($openingDate, fn ($q) => $q->where('je.entry_date', '>=', $openingDate))
            ->where('je.entry_date', '<', $asOfDate)
            ->selectRaw('COALESCE(SUM(jl.debit),0) as total_debit, COALESCE(SUM(jl.credit),0) as total_credit')
            ->first();

        return round($openingBalance + (float) $sums->total_debit - (float) $sums->total_credit, 2);
    }

    /**
     * True only when there is something real to compare the statement's
     * opening balance against: a configured opening balance/date on the
     * Bank Account itself, or at least one journal entry already posted
     * to its GL account before this period. Neither existing means the
     * books are simply empty for this account so far — not that RM 0.00
     * is the "correct" figure — so the mismatch check has nothing
     * meaningful to compare against and must not fire.
     */
    private function hasComparableBaseline(string $bankAccountId, string $nodeId, ?string $groupLabelId, string $asOfDate): bool
    {
        $bankAccount = DB::table('cbe_bank_accounts')->where('bank_account_id', $bankAccountId)->first();
        if ($bankAccount && $bankAccount->opening_balance_date) {
            return true;
        }

        $glAccountId = CbeAccountingService::bankAccountGlAccountId($bankAccountId, $nodeId, $groupLabelId);
        if (! $glAccountId) {
            return false;
        }

        return DB::table('cbe_journal_lines as jl')
            ->join('cbe_journal_entries as je', 'je.journal_id', '=', 'jl.journal_id')
            ->where('jl.account_id', $glAccountId)
            ->where('je.entry_date', '<', $asOfDate)
            ->exists();
    }
}
