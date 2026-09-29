@extends('layouts.dashboard')

@section('page-title', __('finance.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.approvals.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('finance.back_to_approvals_link') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:6px;">

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('finance.withdrawal_request_details_title') }}</div>

            @php
                $withdrawalStatusLabels = [
                    'PAID' => __('masterfile.status_paid'),
                    'REJECTED' => __('masterfile.status_rejected'),
                    'APPROVED' => __('masterfile.status_approved'),
                    'PENDING' => __('finance.status_pending'),
                ];
            @endphp
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_status') }}</strong>
                <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600;
                    {{ $withdrawal->status === 'PAID' ? 'background:#e8f5e9;color:#1b5e20;' : ($withdrawal->status === 'REJECTED' ? 'background:#fde8e8;color:#b71c1c;' : ($withdrawal->status === 'APPROVED' ? 'background:#e0f7fa;color:#1565C0;' : 'background:#fff8e1;color:#92400e;')) }}">
                    {{ $withdrawalStatusLabels[$withdrawal->status] ?? $withdrawal->status }}
                </span>
            </div>
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_agent') }}</strong> {{ $agent->full_name ?? '—' }} ({{ $agent->agent_code ?? '—' }})</div>
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_amount') }}</strong> RM {{ number_format($withdrawal->withdrawal_amount, 2) }}</div>
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_bank') }}</strong> {{ $withdrawal->bank_name }}</div>
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_account_holder') }}</strong> {{ $withdrawal->account_holder_name }}</div>
            <div style="font-size:11px; margin-bottom:10px;">
                <strong>{{ __('finance.row_account_number') }}</strong>
                <span id="acctDisplay">{{ $maskedAccount }}</span>
                <span onclick="decryptAccount()" style="color:#1B9AE4; cursor:pointer; font-weight:600; font-size:10px; margin-left:6px;">{{ __('finance.decrypt_to_verify_link') }}</span>
            </div>
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_submitted') }}</strong> {{ \Carbon\Carbon::parse($withdrawal->submitted_date)->format('d M Y, h:i A') }}</div>
            @if($withdrawal->approved_date)
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_approved') }}</strong> {{ \Carbon\Carbon::parse($withdrawal->approved_date)->format('d M Y, h:i A') }}</div>
            @endif
            @if($withdrawal->paid_date)
            <div style="font-size:11px; margin-bottom:6px;"><strong>{{ __('finance.row_paid_with_ref') }}</strong> {{ \Carbon\Carbon::parse($withdrawal->paid_date)->format('d M Y, h:i A') }} {{ __('finance.ref_suffix', ['ref' => $withdrawal->payment_reference]) }}</div>
            @endif
        </div>

        @if($withdrawal->status === 'APPROVED')
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('finance.mark_as_paid_title') }}</div>
            <div style="font-size:10.5px; color:#6b7280; margin-bottom:10px;">{{ __('finance.mark_as_paid_note') }}</div>
            <form method="POST" action="{{ route('admin.finance.withdrawal.mark-paid', $withdrawal->request_id) }}">
                @csrf
                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('finance.field_payment_reference_required') }} <span style="color:#e53935;">*</span></label>
                <input type="text" name="payment_reference" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; box-sizing:border-box;">
                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('finance.field_remarks_optional') }} <span style="font-weight:400; color:#9ca3af;">({{ __('network.optional_placeholder') }})</span></label>
                <textarea name="remarks" id="withdrawalRemarksField" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box; resize:vertical;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'withdrawalRemarksField', 'carolynType' => 'withdrawal_remarks'])
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('finance.confirm_payment_completed_button') }}</button>
            </form>
        </div>
        @elseif($withdrawal->status === 'PENDING')
        <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:14px; font-size:11px; color:#92400e;">
            {{ __('finance.pending_2stage_note') }}
        </div>
        @elseif($withdrawal->status === 'PAID')
        <div style="background:#e8f5e9; border:1px solid #a5d6a7; border-radius:8px; padding:14px; font-size:11px; color:#1b5e20;">
            {{ __('finance.already_paid_note') }}
        </div>
        @endif

    </div>
</div>

<script>
function decryptAccount() {
    if (!confirm(@json(__('finance.decrypt_confirm_js')))) return;
    fetch('{{ route('admin.finance.withdrawal.decrypt', $withdrawal->request_id) }}')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('acctDisplay').textContent = data.account_number;
        });
}
</script>
@endsection
