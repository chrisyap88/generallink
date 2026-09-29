@extends('layouts.dashboard')

@section('page-title', __('wallet.earning_income_wallet_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    {{-- Balance Cards --}}
    <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:10px;">
        <div style="background:#1565C0; border-radius:8px; padding:10px 12px; color:#fff;">
            <div style="font-size:9px; opacity:.85;">{{ __('wallet.available_balance_label') }}</div>
            <div style="font-size:16px; font-weight:700;">RM {{ number_format($wallet->wallet_balance, 2) }}</div>
        </div>
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">
            <div style="font-size:9px; color:#9ca3af;">{{ __('wallet.pending_earning_income_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:#92400e;">RM {{ number_format($wallet->pending_commission, 2) }}</div>
        </div>
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">
            <div style="font-size:9px; color:#9ca3af;">{{ __('wallet.approved_earning_income_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:#1b5e20;">RM {{ number_format($wallet->approved_commission, 2) }}</div>
        </div>
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">
            <div style="font-size:9px; color:#9ca3af;">{{ __('wallet.paid_to_date_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:#1565C0;">RM {{ number_format($wallet->paid_commission, 2) }}</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1.3fr; gap:10px;">

        {{-- Withdrawal Request Form --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('wallet.request_withdrawal_heading') }}</div>
            <form method="POST" action="{{ route('wallet.withdraw') }}">
                @csrf
                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('wallet.withdrawal_amount_label') }}</label>
                <input type="number" step="0.01" name="withdrawal_amount" required max="{{ $wallet->wallet_balance }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; box-sizing:border-box;">
                <div style="font-size:9px; color:#9ca3af; margin-bottom:8px; margin-top:-6px;">{{ __('wallet.max_rm_note', ['value' => number_format($wallet->wallet_balance, 2)]) }}</div>

                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('wallet.bank_name_label') }}</label>
                <select name="bank_name" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; background:#fff;">
                    <option value="">{{ __('wallet.select_bank_placeholder') }}</option>
                    @foreach(['Maybank','CIMB Bank','Public Bank','RHB Bank','Hong Leong Bank','AmBank','Bank Islam','Bank Rakyat','BSN','OCBC Bank','UOB Bank','Standard Chartered','HSBC Bank','Alliance Bank','Affin Bank'] as $bank)
                        <option value="{{ $bank }}">{{ $bank }}</option>
                    @endforeach
                </select>

                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('wallet.account_holder_name_label') }}</label>
                <input type="text" name="account_holder_name" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; box-sizing:border-box;">

                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('wallet.bank_account_number_label') }}</label>
                <input type="text" name="bank_account" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; box-sizing:border-box;">

                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('wallet.confirm_bank_account_number_label') }}</label>
                <input type="text" name="bank_account_confirm" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:10px; box-sizing:border-box;">

                <label style="display:flex; align-items:flex-start; gap:6px; font-size:9.5px; color:#4b5563; margin-bottom:10px; cursor:pointer;">
                    <input type="checkbox" name="declaration" required style="margin-top:2px;">
                    <span>{{ __('wallet.declaration_text') }}</span>
                </label>

                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('wallet.submit_withdrawal_request_button') }}</button>
            </form>
        </div>

        {{-- Past Requests --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('wallet.my_withdrawal_requests_heading') }}</div>
            <div style="max-height:400px; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#f0f9ff;">
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('wallet.th_date') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('wallet.th_amount') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('wallet.th_bank') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('wallet.th_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($myRequests as $r)
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">{{ \Carbon\Carbon::parse($r->created_at)->format('d M Y') }}</td>
                            <td style="padding:4px 6px;">RM {{ number_format($r->withdrawal_amount, 2) }}</td>
                            <td style="padding:4px 6px;">{{ $r->bank_name }}</td>
                            <td style="padding:4px 6px;">
                                @if(!$r->email_confirmed)
                                    <span style="padding:2px 7px; border-radius:20px; font-size:9px; font-weight:600; background:#f3f4f6; color:#6b7280;">{{ __('wallet.awaiting_email_confirmation_badge') }}</span>
                                    <a href="{{ route('wallet.confirm-code', $r->request_id) }}" style="color:#1B9AE4; font-size:9.5px; font-weight:600; text-decoration:none; margin-left:4px;">{{ __('wallet.confirm_now_link') }}</a>
                                @else
                                    <span style="padding:2px 7px; border-radius:20px; font-size:9px; font-weight:600;
                                        {{ $r->status === 'PAID' ? 'background:#e8f5e9;color:#1b5e20;' : ($r->status === 'REJECTED' ? 'background:#fde8e8;color:#b71c1c;' : ($r->status === 'APPROVED' ? 'background:#e0f7fa;color:#1565C0;' : 'background:#fff8e1;color:#92400e;')) }}">
                                        {{ __('wallet.status_'.strtolower($r->status)) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('wallet.no_withdrawal_requests_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
