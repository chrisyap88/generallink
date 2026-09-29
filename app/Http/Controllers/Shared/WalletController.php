<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    // Shared across all 4 roles — everyone has an Earning Income
    // Wallet, regardless of GL/TL/Introducer/Admin.
    // Shared across GL/TL/Introducer only — Admin NEVER has a personal
    // wallet and can NEVER submit a withdrawal request, for themselves
    // or on anyone else's behalf. Doing so would be an illegal
    // withdrawal and create real company liability for lost funds
    // (confirmed business rule, 06 Jul 2026). Admin's only involvement
    // with wallets is processing/approving requests via the Finance
    // dashboard — a completely separate screen.
    private function blockAdmin()
    {
        if (Auth::guard('agent')->user()->role === 'ADMIN') {
            abort(403, 'Admin accounts do not have a personal Earning Income Wallet. Admin can only process and approve withdrawal requests via the Finance dashboard.');
        }
    }

    public function show()
    {
        $this->blockAdmin();
        $agent = Auth::guard('agent')->user();
        $wallet = $this->getOrCreateWallet($agent->agent_id);

        $myRequests = DB::table('withdrawal_requests')
            ->where('agent_id', $agent->agent_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('wallet.show', compact('wallet', 'myRequests'));
    }

    private function getOrCreateWallet(string $agentId)
    {
        $wallet = DB::table('earning_wallets')->where('agent_id', $agentId)->first();
        if (!$wallet) {
            DB::table('earning_wallets')->insert([
                'wallet_id'           => (string) Str::uuid(),
                'agent_id'            => $agentId,
                'wallet_balance'      => 0,
                'pending_commission'  => 0,
                'approved_commission' => 0,
                'paid_commission'     => 0,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
            $wallet = DB::table('earning_wallets')->where('agent_id', $agentId)->first();
        }
        return $wallet;
    }

    // Step 1 of withdrawal — collects everything, sends an email
    // confirmation code. The actual request is NOT yet real/visible to
    // Finance until the code is confirmed (see confirmCode() below).
    public function submitRequest(Request $request)
    {
        $this->blockAdmin();
        $agent = Auth::guard('agent')->user();
        $wallet = $this->getOrCreateWallet($agent->agent_id);

        $request->validate([
            'withdrawal_amount'      => ['required', 'numeric', 'min:1'],
            'bank_name'              => ['required', 'string', 'max:100'],
            'account_holder_name'    => ['required', 'string', 'max:200'],
            'bank_account'           => ['required', 'string', 'regex:/^\d{5,20}$/'],
            'bank_account_confirm'   => ['required', 'same:bank_account'],
            'declaration'            => ['required', 'accepted'],
        ], [
            'bank_account_confirm.same' => 'Account number confirmation does not match.',
            'declaration.accepted'      => 'You must confirm the declaration to proceed.',
        ]);

        if ($request->withdrawal_amount > $wallet->wallet_balance) {
            return back()->withErrors(['withdrawal_amount' => 'Withdrawal amount exceeds your available wallet balance.'])->withInput();
        }

        $code = (string) random_int(100000, 999999);
        $codeExpiresAt = now()->addHours(24);
        $referenceNumber = 'WD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));

        $requestId = (string) Str::uuid();
        DB::table('withdrawal_requests')->insert([
            'request_id'              => $requestId,
            'reference_number'        => $referenceNumber,
            'agent_id'                => $agent->agent_id,
            'withdrawal_amount'       => $request->withdrawal_amount,
            'bank_name'               => $request->bank_name,
            'account_holder_name'     => $request->account_holder_name,
            'encrypted_bank_account'  => Crypt::encryptString($request->bank_account),
            'status'                  => 'PENDING',
            'submitted_date'          => now(),
            'email_confirmation_code' => $code,
            'email_code_expires_at'   => $codeExpiresAt,
            'email_confirmed'         => false,
            'created_at'              => now(),
            'updated_at'              => now(),
        ]);

        $submittedAt = now()->format('d M Y, h:i A');
        $expiresAt = $codeExpiresAt->format('d M Y, h:i A');

        Mail::raw(
            "Dear {$agent->full_name},\n\n"
            . "Thank you for submitting your withdrawal request. We have received it as follows:\n\n"
            . "Reference Number: {$referenceNumber}\n"
            . "Amount: RM " . number_format($request->withdrawal_amount, 2) . "\n"
            . "Date/Time Submitted: {$submittedAt}\n\n"
            . "To proceed, please confirm this request using the code below:\n\n"
            . "Confirmation Code: {$code}\n\n"
            . "Please note this code will expire on {$expiresAt} (24 hours from now). If it expires before you confirm, your request will be automatically cancelled and you will need to submit a new one — please don't worry, this is simply a safety measure to protect your account.\n\n"
            . "If you did not make this request, please ignore this email or contact us.\n\n"
            . "Thank you,\nGeneralLink Admin",
            function ($mail) use ($agent) {
                $mail->to($agent->email)->subject('GeneralLink — Please Confirm Your Withdrawal Request');
            }
        );

        return redirect()->route('wallet.confirm-code', $requestId)->with('success', 'A confirmation code was sent to your email.');
    }

    public function showConfirmCode(string $requestId)
    {
        $this->blockAdmin();
        $agent = Auth::guard('agent')->user();
        $withdrawal = DB::table('withdrawal_requests')
            ->where('request_id', $requestId)
            ->where('agent_id', $agent->agent_id)
            ->firstOrFail();

        if ($withdrawal->email_confirmed) {
            return redirect()->route('wallet.show')->with('success', 'This request is already confirmed.');
        }

        return view('wallet.confirm-code', compact('withdrawal'));
    }

    // Step 2 — only NOW does the request become real and visible to
    // Finance for approval.
    public function confirmCode(Request $request, string $requestId)
    {
        $this->blockAdmin();
        $agent = Auth::guard('agent')->user();
        $request->validate(['code' => ['required', 'string']]);

        $withdrawal = DB::table('withdrawal_requests')
            ->where('request_id', $requestId)
            ->where('agent_id', $agent->agent_id)
            ->first();

        if (!$withdrawal) {
            return back()->withErrors(['code' => 'Request not found.']);
        }

        // 24-hour expiry check — auto-cancel if the code has expired,
        // per confirmed decision (must give people realistic time,
        // e.g. submitting late at night and confirming next morning).
        if ($withdrawal->email_code_expires_at && now()->greaterThan($withdrawal->email_code_expires_at)) {
            DB::table('withdrawal_requests')->where('request_id', $requestId)->update([
                'status'     => 'CANCELLED',
                'remarks'    => 'Automatically cancelled — confirmation code expired without being confirmed.',
                'updated_at' => now(),
            ]);
            return back()->withErrors(['code' => 'This confirmation code has expired (24-hour limit). Your request has been automatically cancelled — please submit a new withdrawal request.']);
        }

        if ($withdrawal->email_confirmation_code !== $request->code) {
            return back()->withErrors(['code' => 'Incorrect confirmation code.']);
        }

        DB::table('withdrawal_requests')->where('request_id', $requestId)->update([
            'email_confirmed'    => true,
            'email_confirmed_at' => now(),
            'updated_at'         => now(),
        ]);

        \App\Services\AuditService::logChange('withdrawal_requests', $requestId, 'WITHDRAWAL_SUBMITTED', null, [
            'amount' => $withdrawal->withdrawal_amount,
            'agent'  => $agent->full_name,
        ]);

        // Warm, professional confirmation — thanking them, confirming
        // it's now being processed, with reference number and
        // timestamp, per confirmed requirement.
        $confirmedAt = now()->format('d M Y, h:i A');
        app(\App\Services\NotificationService::class)->notify(
            [$agent],
            'WITHDRAWAL_SUBMITTED',
            'Withdrawal Request Received',
            "Dear {$agent->full_name}, thank you for confirming your withdrawal request. We have received it and it is now being processed.\n\nReference Number: {$withdrawal->reference_number}\nAmount: RM " . number_format($withdrawal->withdrawal_amount, 2) . "\nConfirmed: {$confirmedAt}\n\nWe will notify you once it has been reviewed. Thank you for your patience.\n\nKind Regards,\nGeneralLink Admin"
        );

        // Notify Finance admins that a real, confirmed request is
        // waiting — via the SAME 4-eye approval engine, routed to
        // Finance department automatically.
        app(\App\Services\ApprovalService::class)->requestApproval(
            'WITHDRAWAL_APPROVAL',
            $agent->agent_id,
            ['withdrawal_request_id' => $requestId, 'amount' => $withdrawal->withdrawal_amount],
            $agent->agent_id,
            null,
            "Withdrawal request {$withdrawal->reference_number} of RM " . number_format($withdrawal->withdrawal_amount, 2) . " from {$agent->full_name}, submitted {$confirmedAt}."
        );

        return redirect()->route('wallet.show')->with('success', 'Withdrawal request confirmed and submitted for Finance approval.');
    }
}
