@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.show_pr_title'))

@section('content')

@php $prStatusColors = ['PENDING' => '#D97706', 'APPROVED' => '#2e7d32', 'REJECTED' => '#c62828', 'CONVERTED' => '#546E7A', 'CONVERTED_TO_PO' => '#546E7A']; @endphp
@php $prStatusLabels = ['PENDING' => __('cbe_records.status_pending'), 'APPROVED' => __('cbe_records.status_approved'), 'REJECTED' => __('cbe_records.status_rejected'), 'CONVERTED' => __('cbe_accounting.status_converted'), 'CONVERTED_TO_PO' => __('cbe_accounting.status_converted_to_po')]; @endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.show_pr_title') }} — {{ $pr->doc_ref_no }}
            <span style="background:{{ $prStatusColors[$pr->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600; margin-left:6px;">{{ $prStatusLabels[$pr->status] }}</span>
        </div>
        <a href="{{ route('cbe.accounting.purchase-requests') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; margin-bottom:10px; font-size:9.5px;">
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_date') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ \Carbon\Carbon::parse($pr->request_date)->format('d M Y') }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_pr_requested_by') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->requested_by_name }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_supplier') }}</div>
                <div style="margin-top:2px;">
                    @if($pr->supplier_id)
                    <a href="{{ route('cbe.accounting.supplier-enquiry', $pr->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $pr->supplier_name }}</a>
                    @else
                    <span style="color:#263238;">—</span>
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_required_date') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->required_date ? \Carbon\Carbon::parse($pr->required_date)->format('d M Y') : '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_cost_centre') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->centre_name ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_fund') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->fund_name ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_purpose') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->description ?: '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_remarks') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $pr->remarks ?: '—' }}</div>
            </div>
        </div>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_category') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_qty') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_unit_price') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#263238;">{{ $l->description ?: '—' }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $l->category_name ?: '—' }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#6b7280;">{{ rtrim(rtrim(number_format($l->quantity, 2), '0'), '.') }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#6b7280;">RM {{ number_format($l->unit_price, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:600; color:#263238;">RM {{ number_format($l->line_amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="padding:6px; text-align:right; font-weight:700; color:#263238;">{{ __('cbe_accounting.col_po_total') }}</td>
                        <td style="padding:6px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($pr->amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if($pr->status === 'REJECTED' && $pr->rejection_reason)
            <div style="margin-top:10px; font-size:9.5px; color:#c62828;">{{ __('cbe_accounting.field_rejection_reason') }}: {{ $pr->rejection_reason }}</div>
            @endif
            @if($pr->po_doc_ref_no)
            <div style="margin-top:10px; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.pr_converted_to_po_note') }}</div>
            <div style="font-size:9.5px; margin-top:3px;"><a href="{{ route('cbe.accounting.purchase-orders.show', $pr->converted_po_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $pr->po_doc_ref_no }}</a></div>
            @endif
            @if($pr->bill_doc_ref_no)
            <div style="margin-top:10px; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.pr_converted_to_bill_note') }}</div>
            <div style="font-size:9.5px; margin-top:3px;"><a href="{{ route('cbe.accounting.bill-enquiry.show', $pr->converted_bill_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $pr->bill_doc_ref_no }}</a></div>
            @endif
        </div>
    </div>
</div>
@endsection
