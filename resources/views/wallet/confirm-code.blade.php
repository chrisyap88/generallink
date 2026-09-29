@extends('layouts.dashboard')

@section('page-title', __('wallet.confirm_withdrawal_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box; display:flex; align-items:center; justify-content:center;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:24px; max-width:380px; width:100%;">
        <div style="text-align:center; margin-bottom:14px;">
            <div style="font-size:14px; font-weight:700; color:#1565C0; margin-bottom:4px;">{{ __('wallet.confirm_your_withdrawal_heading') }}</div>
            <div style="font-size:11px; color:#6b7280;">{{ __('wallet.code_sent_note') }}</div>
            <div style="font-size:10.5px; color:#92400e; background:#fff8e1; border-radius:6px; padding:6px 10px; margin-top:8px;">{!! __('wallet.code_valid_until_note', ['datetime' => \Carbon\Carbon::parse($withdrawal->email_code_expires_at)->format('d M Y, h:i A')]) !!}</div>
        </div>

        <div style="background:#f0f9ff; border-radius:8px; padding:10px 12px; margin-bottom:14px; font-size:11px; color:#374151;">
            <div><strong>{{ __('wallet.reference_number_label') }}</strong> {{ $withdrawal->reference_number }}</div>
            <div><strong>{{ __('wallet.amount_label') }}</strong> RM {{ number_format($withdrawal->withdrawal_amount, 2) }}</div>
            <div><strong>{{ __('wallet.bank_label') }}</strong> {{ $withdrawal->bank_name }}</div>
        </div>

        @if($errors->any())
        <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:11px; margin-bottom:10px;">
            @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('wallet.confirm-code.post', $withdrawal->request_id) }}">
            @csrf
            <label style="font-size:9.5px; font-weight:600; color:#374151; display:block; margin-bottom:5px; text-align:center;">{{ __('wallet.confirmation_code_label') }}</label>
            <input type="text" name="code" required maxlength="6" autocomplete="off" placeholder="123456" style="width:100%; border:1px solid #d1d5db; border-radius:8px; padding:10px; font-size:20px; font-weight:700; text-align:center; letter-spacing:6px; box-sizing:border-box; margin-bottom:12px;">
            <button type="submit" style="width:100%; background:#1565C0; color:#fff; border:none; border-radius:8px; padding:9px; font-size:12.5px; font-weight:600; cursor:pointer;">{{ __('wallet.confirm_withdrawal_button') }}</button>
        </form>

        <div style="text-align:center; margin-top:12px;">
            <a href="{{ route('wallet.show') }}" style="color:#9ca3af; text-decoration:none; font-size:10.5px;">{{ __('wallet.back_to_wallet_link') }}</a>
        </div>
    </div>

</div>
@endsection
