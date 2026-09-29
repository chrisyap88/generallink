@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.show_po_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @php $poStatusColors = ['OPEN' => '#D97706', 'PARTIALLY_RECEIVED' => '#1565C0', 'FULLY_RECEIVED' => '#2e7d32', 'CLOSED' => '#546E7A', 'CANCELLED' => '#c62828']; @endphp
    @php $poApprovalColors = ['PENDING_APPROVAL' => '#D97706', 'APPROVED' => '#2e7d32', 'REJECTED' => '#c62828']; @endphp

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.show_po_title') }} — {{ $order->doc_ref_no }}
                <span style="background:{{ $poStatusColors[$order->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600; margin-left:6px;">{{ __('cbe_accounting.po_status_'.strtolower($order->status)) }}</span>
                @if($order->approval_status !== 'APPROVED')
                <span style="background:{{ $poApprovalColors[$order->approval_status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600; margin-left:4px;">{{ __('cbe_accounting.po_approval_'.strtolower($order->approval_status)) }}</span>
                @endif
            </div>
            @if($order->request_doc_ref_no)
            <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('cbe_accounting.po_from_request_note_prefix') }} <a href="{{ route('cbe.accounting.purchase-requests.show', $order->request_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ $order->request_doc_ref_no }}</a></div>
            @endif
        </div>
        <a href="{{ route('cbe.accounting.purchase-orders') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; display:grid; grid-template-columns:repeat(4, 1fr); gap:8px; margin-bottom:10px; font-size:9.5px;">
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_supplier') }}</div>
                <div style="margin-top:2px;"><a href="{{ route('cbe.accounting.supplier-enquiry', $order->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $order->supplier_name }}</a></div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_date') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ \Carbon\Carbon::parse($order->po_date)->format('d M Y') }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_expected_delivery') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $order->expected_delivery_date ? \Carbon\Carbon::parse($order->expected_delivery_date)->format('d M Y') : '—' }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_purpose') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $order->description ?: '—' }}</div>
            </div>
        </div>

        @if(!in_array($glStage, ['PENDING_APPROVAL', 'REJECTED', 'CANCELLED']))
        @php
            $glSteps = ['READY_FOR_AP' => 1, 'AP_INVOICE_CREATED' => 2, 'GL_POSTED' => 3, 'ERROR' => 2, 'REVERSED' => 3];
            $glCurrentStep = $glSteps[$glStage] ?? 1;
            $glAlert = in_array($glStage, ['ERROR', 'REVERSED']);
        @endphp
        <div style="flex-shrink:0; margin-bottom:10px; display:flex; align-items:center; gap:6px; background:var(--gl-light); border-radius:6px; padding:7px 12px;">
            @foreach(['READY_FOR_AP' => 1, 'AP_INVOICE_CREATED' => 2, 'GL_POSTED' => 3] as $stepKey => $stepNum)
            <div style="display:flex; align-items:center; gap:6px; {{ $stepNum < 3 ? 'flex:1;' : '' }}">
                <div style="width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:8px; font-weight:700; color:#fff; background:{{ $glCurrentStep >= $stepNum ? ($glAlert && $stepNum === $glCurrentStep ? '#c62828' : '#2e7d32') : '#c4c9d0' }}; flex-shrink:0;">{{ $stepNum }}</div>
                <span style="font-size:9px; font-weight:600; color:{{ $glCurrentStep >= $stepNum ? '#263238' : '#9ca3af' }};">{{ __('cbe_accounting.gl_stage_'.strtolower($stepKey)) }}</span>
                @if($stepNum < 3)<div style="flex:1; height:1px; background:{{ $glCurrentStep > $stepNum ? '#2e7d32' : '#d1d5db' }};"></div>@endif
            </div>
            @endforeach
            @if($glAlert)
            <span style="margin-left:8px; background:{{ $glStage === 'ERROR' ? '#c62828' : '#D97706' }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8px; font-weight:600;">{{ __('cbe_accounting.gl_stage_'.strtolower($glStage)) }}</span>
            @endif
        </div>
        @endif

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
                        <td style="padding:6px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($order->amount, 2) }}</td>
                    </tr>
                </tfoot>
            </table>

            @if($grns->isNotEmpty())
            <div style="margin-top:10px; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.po_grns_received_note') }}</div>
            @foreach($grns as $g)
            <div style="font-size:9.5px; color:#263238; margin-top:3px;"><a href="{{ route('cbe.accounting.goods-receipts.show', $g->grn_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $g->doc_ref_no }}</a> — {{ \Carbon\Carbon::parse($g->grn_date)->format('d M Y') }}</div>
            @endforeach
            @endif
            @if($bills->isNotEmpty())
            <div style="margin-top:10px; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.po_bills_raised_note') }}</div>
            @foreach($bills as $b)
            <div style="font-size:9.5px; color:#263238; margin-top:3px;"><a href="{{ route('cbe.accounting.bill-enquiry.show', $b->bill_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $b->doc_ref_no }}</a> — {{ \Carbon\Carbon::parse($b->bill_date)->format('d M Y') }} — RM {{ number_format($b->amount, 2) }}</div>
            @endforeach
            @endif
        </div>

        <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
            @if($order->approval_status === 'PENDING_APPROVAL')
            <form method="POST" action="{{ route('cbe.accounting.purchase-orders.approve', $order->po_id) }}">
                @csrf
                <button type="submit" style="background:#fff; color:#2e7d32; border:1px solid #2e7d32; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.approve_po_button') }}</button>
            </form>
            <form method="POST" action="{{ route('cbe.accounting.purchase-orders.reject', $order->po_id) }}" onsubmit="var r = prompt('{{ __('cbe_accounting.reject_po_reason_prompt') }}'); if(r === null || r.trim() === '') { return false; } document.getElementById('po_reject_reason_{{ $order->po_id }}').value = r; return true;">
                @csrf
                <input type="hidden" id="po_reject_reason_{{ $order->po_id }}" name="reason" value="">
                <button type="submit" style="background:#fff; color:#e53935; border:1px solid #e53935; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.reject_po_button') }}</button>
            </form>
            @endif
            @if($order->approval_status === 'APPROVED' && !in_array($order->status, ['CLOSED', 'CANCELLED']))
            <form method="POST" action="{{ route('cbe.accounting.purchase-orders.convert-to-bill', $order->po_id) }}" onsubmit="var d = prompt('{{ __('cbe_accounting.convert_bill_date_prompt') }}', '{{ now()->toDateString() }}'); if(d === null || d.trim() === '') { return false; } document.getElementById('po_bill_date_{{ $order->po_id }}').value = d; return true;">
                @csrf
                <input type="hidden" id="po_bill_date_{{ $order->po_id }}" name="bill_date" value="">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.convert_to_bill_button') }}</button>
            </form>
            @endif
            @if($order->approval_status === 'APPROVED' && in_array($order->status, ['OPEN', 'PARTIALLY_RECEIVED']))
            <a href="{{ route('cbe.accounting.goods-receipts.create', $order->po_id) }}" style="background:#fff; color:var(--gl-blue); border:1px solid var(--gl-blue); border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; text-decoration:none;">{{ __('cbe_accounting.receive_goods_button') }}</a>
            <form method="POST" action="{{ route('cbe.accounting.purchase-orders.mark-received', $order->po_id) }}">
                @csrf
                <button type="submit" style="background:#fff; color:#2e7d32; border:1px solid #2e7d32; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.mark_received_button') }}</button>
            </form>
            <form method="POST" action="{{ route('cbe.accounting.purchase-orders.cancel', $order->po_id) }}" onsubmit="return confirm('{{ __('cbe_records.deactivate_confirm_js') }}');">
                @csrf
                <button type="submit" style="background:#fff; color:#e53935; border:1px solid #e53935; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.cancel_po_button') }}</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
