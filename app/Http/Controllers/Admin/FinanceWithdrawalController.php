<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class FinanceWithdrawalController extends Controller
{
    private function guardFinance()
    {
        $me = Auth::guard('agent')->user();
        if ($me->role !== 'ADMIN' || !in_array($me->department, ['FINANCE', 'DIRECTOR'])) {
            abort(403, 'Only Admin Finance or Admin Director can access withdrawal processing.');
        }
    }

    public function show(string $requestId)
    {
        $this->guardFinance();

        $withdrawal = DB::table('withdrawal_requests')->where('request_id', $requestId)->firstOrFail();
        $agent = Agent::find($withdrawal->agent_id);

        // Masked only — the real number is NEVER shown by default, only
        // via the explicit "Decrypt" action below (audit logged).
        $maskedAccount = '•••• •••• ' . substr($this->decryptAccountRaw($withdrawal), -4);

        return view('finance.withdrawal-detail', compact('withdrawal', 'agent', 'maskedAccount'));
    }

    private function decryptAccountRaw($withdrawal): string
    {
        try {
            return Crypt::decryptString($withdrawal->encrypted_bank_account);
        } catch (\Exception $e) {
            return '(unable to decrypt)';
        }
    }

    // Explicit decrypt action — separate from just viewing the page, so
    // the masked view alone never requires logging a "viewed the real
    // number" audit entry. Only THIS action does.
    public function decryptAccount(string $requestId)
    {
        $this->guardFinance();

        $withdrawal = DB::table('withdrawal_requests')->where('request_id', $requestId)->firstOrFail();
        $realAccount = $this->decryptAccountRaw($withdrawal);

        AuditService::logChange('withdrawal_requests', $requestId, 'BANK_ACCOUNT_VIEWED', null, [
            'viewed_by' => Auth::guard('agent')->user()->full_name,
        ]);

        return response()->json(['account_number' => $realAccount]);
    }

    // Finance manually transfers the money via their own banking FIRST
    // (outside this system), then comes back here to record it —
    // this action just marks it done, it does not move any money itself.
    public function markAsPaid(Request $request, string $requestId)
    {
        $this->guardFinance();

        $request->validate([
            'payment_reference' => ['required', 'string', 'max:100'],
            'remarks'            => ['nullable', 'string', 'max:1000'],
        ]);

        $withdrawal = DB::table('withdrawal_requests')->where('request_id', $requestId)->firstOrFail();

        if ($withdrawal->status !== 'APPROVED') {
            return back()->withErrors(['payment_reference' => 'This request must be fully approved (both stages) before it can be marked as paid.']);
        }

        DB::table('withdrawal_requests')->where('request_id', $requestId)->update([
            'status'             => 'PAID',
            'paid_date'          => now(),
            'payment_reference'  => $request->payment_reference,
            'remarks'            => $request->remarks,
            'updated_at'         => now(),
        ]);

        // Deduct from wallet balance only NOW — money has genuinely left.
        DB::table('earning_wallets')->where('agent_id', $withdrawal->agent_id)->update([
            'wallet_balance' => DB::raw("wallet_balance - {$withdrawal->withdrawal_amount}"),
            'paid_commission' => DB::raw("paid_commission + {$withdrawal->withdrawal_amount}"),
            'last_updated'   => now(),
            'updated_at'     => now(),
        ]);

        AuditService::logChange('withdrawal_requests', $requestId, 'PAYMENT_COMPLETED', ['status' => 'APPROVED'], ['status' => 'PAID', 'payment_reference' => $request->payment_reference]);

        $agent = Agent::find($withdrawal->agent_id);
        if ($agent) {
            $paidAt = now()->format('d M Y, h:i A');
            app(NotificationService::class)->notify(
                [$agent],
                'WITHDRAWAL_PAID',
                'Payment Completed',
                "Dear {$agent->full_name},\n\nWe are happy to inform you that your withdrawal payment has been completed and transferred to your bank account.\n\nReference Number: {$withdrawal->reference_number}\nAmount: RM " . number_format($withdrawal->withdrawal_amount, 2) . "\nPayment Reference: {$request->payment_reference}\nDate/Time Paid: {$paidAt}\n\nThank you for using GeneralLink. Should you have any questions regarding this payment, please contact our Finance team and quote the reference number above.\n\nKind Regards,\nGeneralLink Admin",
                $agent->agent_id
            );
        }

        return redirect()->route('admin.approvals.index')->with('success', 'Withdrawal marked as paid and member notified.');
    }
}
