@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.purchase_requests_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.purchase_requests_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.purchase-requests.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_purchase_request_button') }}</a>
            <a href="{{ route('cbe.accounting.bills') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_bills') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_ref') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_supplier') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_requested_by') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $prStatusColors = ['PENDING' => '#D97706', 'APPROVED' => '#2e7d32', 'REJECTED' => '#c62828', 'CONVERTED' => '#546E7A', 'CONVERTED_TO_PO' => '#546E7A']; @endphp
                    @php $prStatusLabels = ['PENDING' => __('cbe_records.status_pending'), 'APPROVED' => __('cbe_records.status_approved'), 'REJECTED' => __('cbe_records.status_rejected'), 'CONVERTED' => __('cbe_accounting.status_converted'), 'CONVERTED_TO_PO' => __('cbe_accounting.status_converted_to_po')]; @endphp
                    @forelse($requests as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; font-weight:600; white-space:nowrap;"><a href="{{ route('cbe.accounting.purchase-requests.show', $r->request_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $r->doc_ref_no }}</a></td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($r->request_date)->format('d M Y') }}</td>
                        <td style="padding:5px 6px; color:#263238;">{{ $r->supplier_name ?: '—' }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $r->requested_by_name }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($r->amount, 2) }}</td>
                        <td style="padding:5px 6px; text-align:center;">
                            <span style="background:{{ $prStatusColors[$r->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8px; font-weight:600;">{{ $prStatusLabels[$r->status] }}</span>
                        </td>
                        <td style="padding:5px 6px; text-align:right;">
                            <div style="display:flex; gap:4px; justify-content:flex-end;">
                                @if($r->status === 'PENDING')
                                <form method="POST" action="{{ route('cbe.accounting.purchase-requests.approve', $r->request_id) }}" onsubmit="return confirm('{{ __('cbe_accounting.approve_confirm_js') }}');">
                                    @csrf
                                    <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.approve_button') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cbe.accounting.purchase-requests.reject', $r->request_id) }}" onsubmit="var r = prompt('{{ __('cbe_accounting.reject_reason_prompt') }}'); if(r === null || r.trim() === '') { return false; } document.getElementById('pr_reason_{{ $r->request_id }}').value = r; return true;">
                                    @csrf
                                    <input type="hidden" id="pr_reason_{{ $r->request_id }}" name="reason" value="">
                                    <button type="submit" style="background:#c62828; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.reject_button') }}</button>
                                </form>
                                @elseif($r->status === 'APPROVED')
                                <form method="POST" action="{{ route('cbe.accounting.purchase-requests.convert-to-po', $r->request_id) }}" onsubmit="var d = prompt('{{ __('cbe_accounting.convert_po_date_prompt') }}', '{{ now()->toDateString() }}'); if(d === null || d.trim() === '') { return false; } document.getElementById('pr_po_date_{{ $r->request_id }}').value = d; return true;">
                                    @csrf
                                    <input type="hidden" id="pr_po_date_{{ $r->request_id }}" name="po_date" value="">
                                    <button type="submit" style="background:#546E7A; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.convert_to_po_button') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cbe.accounting.purchase-requests.convert', $r->request_id) }}" onsubmit="var d = prompt('{{ __('cbe_accounting.convert_bill_date_prompt') }}', '{{ now()->toDateString() }}'); if(d === null || d.trim() === '') { return false; } document.getElementById('pr_conv_date_{{ $r->request_id }}').value = d; return true;">
                                    @csrf
                                    <input type="hidden" id="pr_conv_date_{{ $r->request_id }}" name="bill_date" value="">
                                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.convert_to_bill_button') }}</button>
                                </form>
                                @else
                                <span style="color:#9ca3af;">—</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_purchase_requests_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($requests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $requests->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $requests->currentPage(), 'last' => $requests->lastPage(), 'total' => $requests->total()]) }}</span>
            @if($requests->hasMorePages())
                <a href="{{ $requests->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
