<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Services\DocumentCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// NEW 21 Jul 2026 — Document Credit Wallet, agent-facing side. Shows
// the agent their own balance and transaction history, and lets them
// request a top-up by attaching a bank-in slip — Admin reviews and
// approves it separately (see Admin\DocumentCreditController). Mirrors
// WalletController's shape, but this is a completely separate balance
// from the Earning Income Wallet, on purpose (see migration comment).
class DocumentCreditController extends Controller
{
    public function show(DocumentCreditService $credit)
    {
        $agent = Auth::guard('agent')->user();

        $history = DB::table('document_credit_transactions')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $myTopupRequests = DB::table('document_credit_topup_requests')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('requested_at')
            ->limit(20)
            ->get();

        $balance = $credit->balance($agent->agent_id);
        $deductionAmount = $credit->deductionAmount();
        $topupFee = $credit->topupProcessingFee();
        $reminderThreshold = DB::table('agents')->where('agent_id', $agent->agent_id)->value('document_credit_reminder_threshold');

        return view('document-credit.show', compact('balance', 'deductionAmount', 'history', 'myTopupRequests', 'reminderThreshold', 'topupFee'));
    }

    /**
     * NEW 21 Jul 2026 — per Chris: each agent sets their OWN low-balance
     * reminder threshold, whole RM number, minimum RM1 (no decimals —
     * enforced here with a friendly message rather than a raw DB
     * constraint error).
     */
    public function updateReminderThreshold(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'reminder_threshold' => ['required', 'integer', 'min:1'],
        ], [
            'reminder_threshold.integer' => 'Please enter a whole number (RM1, RM2, RM3...) — decimals are not allowed for this reminder amount.',
            'reminder_threshold.min'     => 'The reminder amount cannot be less than RM1.',
        ]);

        DB::table('agents')->where('agent_id', $agent->agent_id)->update([
            'document_credit_reminder_threshold' => $request->input('reminder_threshold'),
            // Changing the threshold restarts the reminder guard — a
            // fresh threshold deserves a fresh check, not one silently
            // suppressed by an old dip.
            'document_credit_reminder_sent_at' => null,
        ]);

        return redirect()->route('document-credit.show')->with('success', 'Low-balance reminder set: you\'ll be notified if your balance drops below RM ' . number_format($request->input('reminder_threshold'), 0) . '.');
    }

    public function submitTopup(Request $request)
    {
        $agent = Auth::guard('agent')->user();

        $request->validate([
            'amount_requested' => ['required', 'numeric', 'min:1'],
            'bank_slip'        => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ]);

        $file = $request->file('bank_slip');
        $filePath = $file->store('document-credit-topups', 'local');

        // NEW 22 Jul 2026 — per Chris's scoped-down fraud detection
        // build: same duplicate-file-hash check Sales Transaction
        // already had, now added here too (this upload had none before).
        $fraud = app(\App\Services\FraudDetectionService::class);
        $fileHash = $fraud->hashFile($file->getRealPath());
        $anomalies = [];

        if ($fileHash && $fraud->isDuplicateHash('document_credit_topup_requests', 'file_hash', $fileHash)) {
            $anomalies[] = ['code' => 'DUPLICATE_FILE_HASH', 'label' => 'Identical bank-in slip already on record', 'detail' => 'This exact bank-in slip (matched by file hash) has already been submitted before, by this or another agent.'];
        }

        $recentSameAmount = DB::table('document_credit_topup_requests')
            ->where('agent_id', $agent->agent_id)
            ->where('amount_requested', $request->input('amount_requested'))
            ->where('created_at', '>=', now()->subDay())
            ->exists();
        if ($recentSameAmount) {
            $anomalies[] = ['code' => 'SOFT_DUPLICATE', 'label' => 'Same amount requested very recently', 'detail' => 'This agent already submitted a top-up request for the same amount within the last 24 hours.'];
        }

        $requestId = (string) Str::uuid();

        DB::table('document_credit_topup_requests')->insert([
            'request_id'          => $requestId,
            'agent_id'            => $agent->agent_id,
            'amount_requested'    => $request->input('amount_requested'),
            'bank_slip_file_name' => $file->getClientOriginalName(),
            'bank_slip_file_path' => $filePath,
            'file_hash'           => $fileHash,
            'status'              => 'PENDING',
            'requested_at'        => now(),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $fraud->flag('DOCUMENT_CREDIT_TOPUP', $requestId, $agent->agent_id, $anomalies);

        return redirect()->route('document-credit.show')->with('success', 'Your top-up request has been submitted with your bank-in slip. Admin will review and credit your balance shortly.');
    }
}
