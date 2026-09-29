@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.donation_pledges_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #366) — Donation Pledge detail: header, GL
     posting status, receipts received so far, and an inline Log Receipt
     form to record the next amount received against this pledge. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $row->pledge_no }} — {{ $row->donor_name }}</div>
        <a href="{{ route('cbe.accounting.donation-pledges') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:20px; align-items:center; flex-wrap:wrap;">
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_dn_date') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ \Carbon\Carbon::parse($row->pledge_date)->format('d M Y') }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_status') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ __('cbe_accounting.pledge_status_'.strtolower($row->status)) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_bill_amount') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">RM {{ number_format($row->amount, 2) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_pledge_received') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">RM {{ number_format($row->received_amount, 2) }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_gl_status') }}</div>
            @if($row->journal_id)
            <a href="{{ route('cbe.accounting.journal-vouchers.show', $row->journal_id) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; color:{{ $row->gl_posting_status === 'POSTED' ? '#2e7d32' : ($row->gl_posting_status === 'REVERSED' ? '#c62828' : '#9e9e9e') }};">{{ __('cbe_accounting.gl_status_'.strtolower($row->gl_posting_status ?: 'not_posted')) }}</a>
            @else
            <div style="font-size:10.5px; color:#9e9e9e; font-weight:600;">{{ __('cbe_accounting.gl_status_not_posted') }}</div>
            @endif
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_outstanding_amount') }}</div>
            <div style="font-size:15px; font-weight:700; color:{{ ($row->amount - $row->received_amount) > 0.004 ? '#c62828' : '#2e7d32' }};">RM {{ number_format($row->amount - $row->received_amount, 2) }}</div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px; overflow:hidden;">
        <div style="flex:1; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:10px; font-weight:700; color:#263238; margin-bottom:5px; flex-shrink:0;">{{ __('cbe_accounting.receipt_enquiry_page_title') }}</div>
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_receipt_no') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_bank_account') }}</th>
                            <th style="text-align:right; padding:3px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($receipts as $r)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#546E7A;">{{ $r->receipt_no }}</td>
                            <td style="padding:4px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($r->receipt_date)->format('d M Y') }}</td>
                            <td style="padding:4px 6px; color:#263238;">{{ $r->bank_name ?: '—' }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#2e7d32; font-weight:700;">RM {{ number_format($r->amount, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="padding:10px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_pledge_receipts_note') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="width:280px; flex-shrink:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="font-size:10px; font-weight:700; color:#263238; margin-bottom:6px; flex-shrink:0;">{{ __('cbe_accounting.log_pledge_receipt_button') }}</div>
            @if($row->status === 'CANCELLED' || $row->status === 'FULFILLED')
            <div style="font-size:9.5px; color:#9ca3af; padding:8px 0;">{{ __('cbe_accounting.pledge_status_'.strtolower($row->status)) }}</div>
            @else
            <form method="POST" action="{{ route('cbe.accounting.donation-pledges.receipts.store', $row->pledge_id) }}" style="display:flex; flex-direction:column; gap:7px;">
                @csrf
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.col_dn_date') }}</label>
                    <input type="date" name="receipt_date" value="{{ now()->toDateString() }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.col_bill_amount') }}</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_bank_account') }}</label>
                    <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($bankAccounts as $b)
                        <option value="{{ $b->bank_account_id }}">{{ $b->bank_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_payment_method') }}</label>
                    <input type="text" name="payment_method" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_reference_no') }}</label>
                    <input type="text" name="reference_no" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; box-sizing:border-box;">
                </div>
                <button type="submit" style="margin-top:2px; background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:7px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
