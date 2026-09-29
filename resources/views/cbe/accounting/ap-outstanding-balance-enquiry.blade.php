@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ap_outstanding_balance_enquiry_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #374) — Outstanding Payables Enquiry: on-screen
     (not downloadable) list of every supplier with a non-zero AP
     balance. The downloadable equivalent is the AP Aging report.
     Mirrors outstanding-balance-enquiry.blade.php on the AR side. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ap_outstanding_balance_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.suppliers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_suppliers') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_supplier') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_outstanding_amount') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balances as $b)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $b->supplier_name }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#c62828;">RM {{ number_format($b->outstanding, 2) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            <a href="{{ route('cbe.accounting.supplier-enquiry', $b->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:9px; font-weight:600;">{{ __('cbe_accounting.outstanding_balance_view_link') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.ap_outstanding_balance_no_suppliers_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($balances->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $balances->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $balances->currentPage(), 'last' => $balances->lastPage(), 'total' => $balances->total()]) }}</span>
            @if($balances->hasMorePages())
                <a href="{{ $balances->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
