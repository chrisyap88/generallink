@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.show_grn_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @php $grnStatusColors = ['CONFIRMED' => '#2e7d32', 'DRAFT' => '#9ca3af', 'POSTED' => '#1565C0', 'CANCELLED' => '#c62828']; @endphp

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.show_grn_title') }} — {{ $receipt->doc_ref_no }}
                <span style="background:{{ $grnStatusColors[$receipt->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600; margin-left:6px;">{{ __('cbe_accounting.grn_status_'.strtolower($receipt->status)) }}</span>
            </div>
            <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('cbe_accounting.grn_against_po_note_prefix') }} <a href="{{ route('cbe.accounting.purchase-orders.show', $receipt->po_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ $receipt->po_doc_ref_no }}</a></div>
        </div>
        <a href="{{ route('cbe.accounting.goods-receipts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
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
                <div style="margin-top:2px;"><a href="{{ route('cbe.accounting.supplier-enquiry', $receipt->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $receipt->supplier_name }}</a></div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_grn_date') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ \Carbon\Carbon::parse($receipt->grn_date)->format('d M Y') }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_grn_type') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ __('cbe_accounting.grn_type_'.strtolower($receipt->receipt_type)) }}</div>
            </div>
            <div>
                <div style="font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_remarks') }}</div>
                <div style="color:#263238; margin-top:2px;">{{ $receipt->remarks ?: '—' }}</div>
            </div>
        </div>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_grn_ordered') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_grn_received') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_grn_rejected') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_grn_condition') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#263238;">{{ $l->description ?: '—' }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#6b7280;">{{ rtrim(rtrim(number_format($l->ordered_quantity, 2), '0'), '.') }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:600; color:#263238;">{{ rtrim(rtrim(number_format($l->received_quantity, 2), '0'), '.') }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#c62828;">{{ rtrim(rtrim(number_format($l->rejected_quantity, 2), '0'), '.') }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ __('cbe_accounting.grn_condition_'.strtolower($l->condition_note)) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
            @if($receipt->status === 'CONFIRMED')
            <a href="{{ route('cbe.accounting.supplier-invoice.create', $receipt->grn_id) }}" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; text-decoration:none;">{{ __('cbe_accounting.create_supplier_invoice_button') }}</a>
            @endif
            @if(!in_array($receipt->status, ['CANCELLED', 'POSTED']))
            <a href="{{ route('cbe.accounting.purchase-returns.create', $receipt->grn_id) }}" style="background:#fff; color:var(--gl-blue); border:1px solid var(--gl-blue); border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; text-decoration:none;">{{ __('cbe_accounting.return_goods_button') }}</a>
            <form method="POST" action="{{ route('cbe.accounting.goods-receipts.cancel', $receipt->grn_id) }}" onsubmit="return confirm('{{ __('cbe_records.deactivate_confirm_js') }}');">
                @csrf
                <button type="submit" style="background:#fff; color:#e53935; border:1px solid #e53935; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.cancel_grn_button') }}</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
