<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 21 Jul 2026 — Document Credit Wallet. Every AI document-read
// (ClaudeDocumentExtractionService, used by both the Sales Transaction
// "Read Document" button and the email-in submission pipeline) costs
// real money via the Anthropic API. Per Chris: the amount deducted per
// use is a flat, Admin-set figure (system_settings key
// 'document_credit_deduction_amount', NOT the exact metered API cost —
// simpler to explain to agents and gives Admin a built-in buffer),
// deducted only when a read actually succeeds. Extraction is blocked
// outright if the agent's balance can't cover that amount, so usage
// can never go negative and Chris never has to chase anyone for money.
class DocumentCreditService
{
    private const DEFAULT_DEDUCTION = 0.50; // RM — used only if Admin hasn't set one yet
    private const DEFAULT_TOPUP_FEE = 1.00; // RM — flat processing fee per approved top-up, used only if Admin hasn't set one yet
    private const DEFAULT_TRANSLATION_FEE = 0.30; // RM — per Help Desk message translated (first time only; cached after)
    private const DEFAULT_REPHRASE_FEE = 0.30; // RM — per "Fix Wording" suggestion generated

    public function deductionAmount(): float
    {
        $value = DB::table('system_settings')->where('setting_key', 'document_credit_deduction_amount')->value('setting_value');
        return $value !== null ? (float) $value : self::DEFAULT_DEDUCTION;
    }

    /**
     * NEW 21 Jul 2026 — per Chris: every approved bank-slip top-up has a
     * flat processing fee deducted before crediting the agent's balance
     * (e.g. request RM150 -> agent receives RM149 net, if the fee is
     * RM1). Admin-set, same convention as deductionAmount() above.
     */
    public function topupProcessingFee(): float
    {
        $value = DB::table('system_settings')->where('setting_key', 'document_credit_topup_processing_fee')->value('setting_value');
        return $value !== null ? (float) $value : self::DEFAULT_TOPUP_FEE;
    }

    /**
     * NEW 22 Jul 2026 — per Chris: fee for translating one Help Desk
     * message (charged once per message per target language; free on
     * repeat views since the result is cached). Admin-configurable,
     * same convention as topupProcessingFee().
     */
    public function translationFee(): float
    {
        $value = DB::table('system_settings')->where('setting_key', 'document_credit_translation_fee')->value('setting_value');
        return $value !== null ? (float) $value : self::DEFAULT_TRANSLATION_FEE;
    }

    /**
     * NEW 22 Jul 2026 — per Chris: fee for one "Fix Wording" rephrase
     * suggestion. Admin-configurable, same convention as above.
     */
    public function rephraseFee(): float
    {
        $value = DB::table('system_settings')->where('setting_key', 'document_credit_rephrase_fee')->value('setting_value');
        return $value !== null ? (float) $value : self::DEFAULT_REPHRASE_FEE;
    }

    public function balance(string $agentId): float
    {
        return (float) (DB::table('agents')->where('agent_id', $agentId)->value('document_credit_balance') ?? 0);
    }

    public function hasSufficientBalance(string $agentId): bool
    {
        return $this->balance($agentId) >= $this->deductionAmount();
    }

    /**
     * NEW 21 Jul 2026 — per Chris: each agent can set their own
     * personal low-balance reminder threshold (whole RM, minimum 1).
     * Checked after every deduction; fires at most once per dip below
     * the threshold (reminder_sent_at guards this), and resets
     * automatically once the balance rises back above the threshold
     * via creditTopup() below, so it can fire again next time.
     */
    private function checkLowBalanceReminder(string $agentId): void
    {
        $agent = DB::table('agents')->where('agent_id', $agentId)->first();
        if (!$agent || !$agent->document_credit_reminder_threshold) {
            return; // agent hasn't set a threshold — nothing to check
        }

        if ((float) $agent->document_credit_balance < (float) $agent->document_credit_reminder_threshold
            && $agent->document_credit_reminder_sent_at === null) {
            DB::table('agents')->where('agent_id', $agentId)->update(['document_credit_reminder_sent_at' => now()]);

            app(\App\Services\NotificationService::class)->notify(
                [$agent],
                'DOCUMENT_CREDIT_LOW_BALANCE',
                'Document Credit Balance Low',
                "Dear {$agent->full_name}, your Document Credit balance (RM " . number_format($agent->document_credit_balance, 2) . ") has dropped below your own reminder threshold of RM " . number_format($agent->document_credit_reminder_threshold, 0) . ". Please top up (My Account > Document Credit) to keep using Read Document without interruption."
            );
        }
    }

    /**
     * Deducts the current flat deduction amount from the agent's
     * balance and writes a ledger row. Only call this AFTER a document
     * read has actually succeeded — an agent should never be charged
     * for a failed/errored extraction attempt.
     */
    public function deduct(string $agentId, ?string $referenceDocumentId = null, ?string $note = null): void
    {
        $amount = $this->deductionAmount();

        DB::transaction(function () use ($agentId, $amount, $referenceDocumentId, $note) {
            DB::table('agents')->where('agent_id', $agentId)->decrement('document_credit_balance', $amount);
            $balanceAfter = $this->balance($agentId);

            DB::table('document_credit_transactions')->insert([
                'transaction_id'         => (string) Str::uuid(),
                'agent_id'               => $agentId,
                'type'                   => 'DEDUCTION',
                'amount'                 => $amount,
                'balance_after'          => $balanceAfter,
                'reference_document_id'  => $referenceDocumentId,
                'topup_request_id'       => null,
                'note'                   => $note ?? 'Document extraction (AI read)',
                'created_by'             => 'SYSTEM',
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        });

        $this->checkLowBalanceReminder($agentId);
    }

    /**
     * NEW 22 Jul 2026 — per Chris: shared charge path for Help Desk
     * Translation and Rephrase — both are agent-confirmed ("this will
     * cost RM X, proceed?") one-off AI calls, same wallet as document
     * extraction but their own ledger type so the history stays
     * self-explanatory. Returns false (charges nothing) if the balance
     * can't cover it — caller must check this BEFORE calling the
     * actual Claude API, so an agent is never charged for a feature
     * that then fails to run, and never runs a paid feature they can't
     * afford.
     */
    public function chargeForAiFeature(string $agentId, string $type, float $amount, string $note): bool
    {
        if ($this->balance($agentId) < $amount) {
            return false;
        }

        DB::transaction(function () use ($agentId, $type, $amount, $note) {
            DB::table('agents')->where('agent_id', $agentId)->decrement('document_credit_balance', $amount);
            $balanceAfter = $this->balance($agentId);

            DB::table('document_credit_transactions')->insert([
                'transaction_id'         => (string) Str::uuid(),
                'agent_id'               => $agentId,
                'type'                   => $type,
                'amount'                 => $amount,
                'balance_after'          => $balanceAfter,
                'reference_document_id'  => null,
                'topup_request_id'       => null,
                'note'                   => $note,
                'created_by'             => 'SYSTEM',
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        });

        $this->checkLowBalanceReminder($agentId);
        return true;
    }

    /**
     * Approves a pending top-up request: credits the agent's balance
     * and writes the matching ledger row. Caller is responsible for
     * updating the request's own status/reviewed_by/reviewed_at.
     *
     * FIXED 21 Jul 2026 — per Chris: a flat, Admin-set processing fee
     * is deducted from every approved top-up before crediting (e.g.
     * request RM150, fee RM1 -> agent's balance goes up by RM149, not
     * RM150). $amount here is always the GROSS amount requested/paid
     * by the agent (matches amount_requested on the topup request row)
     * — the net-of-fee math happens inside this method so callers
     * don't need to know about the fee at all.
     */
    public function creditTopup(string $agentId, float $amount, string $topupRequestId, string $approvedByAgentId): void
    {
        $fee = $this->topupProcessingFee();
        $netAmount = max(0, round($amount - $fee, 2));

        DB::transaction(function () use ($agentId, $amount, $fee, $netAmount, $topupRequestId, $approvedByAgentId) {
            DB::table('agents')->where('agent_id', $agentId)->increment('document_credit_balance', $netAmount);
            $balanceAfter = $this->balance($agentId);

            DB::table('document_credit_transactions')->insert([
                'transaction_id'         => (string) Str::uuid(),
                'agent_id'               => $agentId,
                'type'                   => 'TOPUP',
                'amount'                 => $netAmount,
                'balance_after'          => $balanceAfter,
                'reference_document_id'  => null,
                'topup_request_id'       => $topupRequestId,
                'note'                   => 'Top-up approved: RM ' . number_format($amount, 2) . ' received, RM ' . number_format($fee, 2) . ' processing fee, RM ' . number_format($netAmount, 2) . ' credited',
                'created_by'             => $approvedByAgentId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // Reset the low-balance reminder guard once the balance is
            // back above the agent's own threshold, so it can fire
            // again the next time it dips — mirrors
            // earning_wallets.unclaimed_reminder_sent_at's reset logic.
            $agent = DB::table('agents')->where('agent_id', $agentId)->first();
            if ($agent && $agent->document_credit_reminder_threshold && $balanceAfter >= $agent->document_credit_reminder_threshold) {
                DB::table('agents')->where('agent_id', $agentId)->update(['document_credit_reminder_sent_at' => null]);
            }
        });
    }

    /**
     * NEW 21 Jul 2026 — per Chris: a GL/TL/Introducer can transfer part
     * of their OWN Document Credit balance to one specific downline
     * agent, their own choice of who and how much. No processing fee
     * here — the fee already applied when this money was originally
     * purchased via a bank-slip top-up; moving already-owned balance
     * between two agents isn't a new "purchase". Caller (the
     * controller) is responsible for verifying $toAgentId is actually
     * within $fromAgentId's own downline BEFORE calling this — this
     * method only checks the balance is sufficient.
     */
    public function transfer(string $fromAgentId, string $toAgentId, float $amount, ?string $note = null): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Transfer amount must be greater than zero.');
        }
        if ($this->balance($fromAgentId) < $amount) {
            throw new \RuntimeException('Insufficient balance to complete this transfer.');
        }

        $fromAgent = DB::table('agents')->where('agent_id', $fromAgentId)->first();
        $toAgent   = DB::table('agents')->where('agent_id', $toAgentId)->first();

        DB::transaction(function () use ($fromAgentId, $toAgentId, $amount, $note, $fromAgent, $toAgent) {
            DB::table('agents')->where('agent_id', $fromAgentId)->decrement('document_credit_balance', $amount);
            $fromBalanceAfter = $this->balance($fromAgentId);

            DB::table('agents')->where('agent_id', $toAgentId)->increment('document_credit_balance', $amount);
            $toBalanceAfter = $this->balance($toAgentId);

            DB::table('document_credit_transactions')->insert([
                'transaction_id'         => (string) Str::uuid(),
                'agent_id'               => $fromAgentId,
                'related_agent_id'       => $toAgentId,
                'type'                   => 'TRANSFER_OUT',
                'amount'                 => $amount,
                'balance_after'          => $fromBalanceAfter,
                'reference_document_id'  => null,
                'topup_request_id'       => null,
                'note'                   => 'Transferred to ' . ($toAgent->full_name ?? 'agent') . ($note ? ' — ' . $note : ''),
                'created_by'             => $fromAgentId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            DB::table('document_credit_transactions')->insert([
                'transaction_id'         => (string) Str::uuid(),
                'agent_id'               => $toAgentId,
                'related_agent_id'       => $fromAgentId,
                'type'                   => 'TRANSFER_IN',
                'amount'                 => $amount,
                'balance_after'          => $toBalanceAfter,
                'reference_document_id'  => null,
                'topup_request_id'       => null,
                'note'                   => 'Received from ' . ($fromAgent->full_name ?? 'agent') . ($note ? ' — ' . $note : ''),
                'created_by'             => $fromAgentId,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            // Reset the receiver's low-balance reminder guard if their
            // new balance is back above their own threshold.
            if ($toAgent && $toAgent->document_credit_reminder_threshold && $toBalanceAfter >= $toAgent->document_credit_reminder_threshold) {
                DB::table('agents')->where('agent_id', $toAgentId)->update(['document_credit_reminder_sent_at' => null]);
            }
        });

        // Check AFTER the transaction closes, same pattern as deduct() —
        // the sender's own balance just dropped, so their own reminder
        // threshold may now be crossed.
        $this->checkLowBalanceReminder($fromAgentId);

        if ($toAgent) {
            app(\App\Services\NotificationService::class)->notify(
                [$toAgent],
                'DOCUMENT_CREDIT_TRANSFER_RECEIVED',
                'Document Credit Received',
                "Dear {$toAgent->full_name}, you received RM " . number_format($amount, 2) . " Document Credit from {$fromAgent->full_name}. Your new balance is RM " . number_format($this->balance($toAgentId), 2) . "."
            );
        }
    }
}
