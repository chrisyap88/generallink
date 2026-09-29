@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.donation_pledges_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #366) — Donation Pledge list: GL-integrated,
     donor-scoped promises to give, separate from the event-scoped
     Donor Register contributions. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.donation_pledges_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.donation-pledges.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_donation_pledge_button') }}</a>
            <a href="{{ route('cbe.accounting.invoices') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_invoices') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <form method="GET" style="flex-shrink:0; margin-bottom:8px; display:flex; gap:8px;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('cbe_accounting.field_donor_search') }}" style="flex:1; max-width:280px; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; box-sizing:border-box;">
        <button type="submit" style="background:#546E7A; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pledge_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe.sb_donor_register') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_outstanding_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pledges as $p)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.donation-pledges.show', $p->pledge_id) }}'">
                        <td style="padding:5px 8px; color:#546E7A; white-space:nowrap;">{{ $p->pledge_no }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($p->pledge_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $p->donor_name }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">RM {{ number_format($p->amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:{{ ($p->amount - $p->received_amount) > 0.004 ? '#c62828' : '#2e7d32' }};">RM {{ number_format($p->amount - $p->received_amount, 2) }}</td>
                        <td style="padding:5px 8px;"><span style="font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:10px; background:{{ $p->status === 'FULFILLED' ? '#e8f5e9' : ($p->status === 'CANCELLED' ? '#f3f4f6' : '#fff3e0') }}; color:{{ $p->status === 'FULFILLED' ? '#2e7d32' : ($p->status === 'CANCELLED' ? '#6b7280' : '#e65100') }};">{{ __('cbe_accounting.pledge_status_'.strtolower($p->status)) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_donation_pledges_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($pledges->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pledges->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $pledges->currentPage(), 'last' => $pledges->lastPage(), 'total' => $pledges->total()]) }}</span>
            @if($pledges->hasMorePages())
                <a href="{{ $pledges->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
