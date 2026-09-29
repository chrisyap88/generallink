@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ap_payment_enquiry_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #374) — Payment Enquiry detail: one payment's
     full information plus its GL posting status. Mirrors
     receipt-enquiry-show.blade.php on the AR side. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $p->supplier_name }} — {{ $p->bill_no ?: $p->doc_ref_no }}</div>
        <a href="{{ route('cbe.accounting.ap-payment-enquiry') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px; max-width:640px;">
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_pv_no') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;">{{ $p->pv_no ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_dn_date') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;">{{ \Carbon\Carbon::parse($p->payment_date)->format('d M Y') }}</div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_bill') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;"><a href="{{ route('cbe.accounting.bill-enquiry.show', $p->bill_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $p->bill_no ?: $p->doc_ref_no }}</a></div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_payment_method') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;">{{ $p->payment_method ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_bank_account') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;">{{ $p->bank_name ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_reference_no') }}</div>
                <div style="font-size:11px; color:#263238; font-weight:600;">{{ $p->reference_no ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_gl_status') }}</div>
                @if($p->journal_id)
                <a href="{{ route('cbe.accounting.journal-vouchers.show', $p->journal_id) }}" style="font-size:11px; font-weight:700; text-decoration:none; color:{{ $p->gl_posting_status === 'POSTED' ? '#2e7d32' : ($p->gl_posting_status === 'REVERSED' ? '#c62828' : '#9e9e9e') }};">{{ __('cbe_accounting.gl_status_'.strtolower($p->gl_posting_status ?: 'not_posted')) }}</a>
                @else
                <div style="font-size:11px; color:#9e9e9e; font-weight:600;">{{ __('cbe_accounting.gl_status_not_posted') }}</div>
                @endif
            </div>
            <div>
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_bill_amount') }}</div>
                <div style="font-size:15px; color:#2e7d32; font-weight:700;">RM {{ number_format($p->amount, 2) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
