@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.show_rfq_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @php $rfqStatusColors = ['DRAFT' => '#9ca3af', 'SENT' => '#D97706', 'QUOTED' => '#1565C0', 'EVALUATED' => '#7b1fa2', 'AWARDED' => '#2e7d32', 'CANCELLED' => '#c62828']; @endphp

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.show_rfq_title') }} — {{ $quotation->doc_ref_no }}
                <span style="background:{{ $rfqStatusColors[$quotation->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600; margin-left:6px;">{{ __('cbe_accounting.rfq_status_'.strtolower($quotation->status)) }}</span>
            </div>
            <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ $quotation->description ?: '—' }}</div>
        </div>
        <a href="{{ route('cbe.accounting.purchase-quotations') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">

        <div style="flex-shrink:0; font-size:8px; color:#9ca3af; margin-bottom:6px;">{{ __('cbe_accounting.rfq_comparison_helper_note') }}</div>

        <form method="POST" action="{{ route('cbe.accounting.purchase-quotations.quotes', $quotation->rfq_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_po_supplier') }}</th>
                            <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.field_rfq_quoted_amount') }}</th>
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.field_rfq_quotation_ref') }}</th>
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:120px;">{{ __('cbe_accounting.field_rfq_quotation_date') }}</th>
                            <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:90px;">{{ __('cbe_accounting.col_rfq_selected') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invited as $row)
                        <tr style="border-bottom:1px solid #f3f4f6; {{ $row->is_selected ? 'background:#e8f5e9;' : '' }}">
                            <td style="padding:5px 6px; font-weight:600;"><a href="{{ route('cbe.accounting.supplier-enquiry', $row->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $row->supplier_name }}</a></td>
                            <td style="padding:5px 6px;">
                                <input type="number" step="0.01" min="0" name="quoted_amount[{{ $row->rfq_supplier_id }}]" value="{{ $row->quoted_amount }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:5px 6px;">
                                <input type="text" name="quotation_ref[{{ $row->rfq_supplier_id }}]" value="{{ $row->quotation_ref }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                            </td>
                            <td style="padding:5px 6px;">
                                <input type="date" name="quotation_date[{{ $row->rfq_supplier_id }}]" value="{{ $row->quotation_date }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                            </td>
                            <td style="padding:5px 6px; text-align:center;">
                                @if($row->is_selected)
                                <span style="color:#2e7d32; font-weight:700; font-size:9px;">{{ __('cbe_accounting.rfq_selected_label') }}</span>
                                @elseif(!in_array($quotation->status, ['AWARDED', 'CANCELLED']))
                                <button type="submit" formaction="{{ route('cbe.accounting.purchase-quotations.select', [$quotation->rfq_id, $row->rfq_supplier_id]) }}" style="background:#fff; color:var(--gl-blue); border:1px solid var(--gl-blue); border-radius:14px; padding:3px 10px; font-size:8.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.rfq_select_button') }}</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                @if(!in_array($quotation->status, ['AWARDED', 'CANCELLED']))
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.rfq_save_quotes_button') }}</button>
                @endif
            </div>
        </form>

        <div style="flex-shrink:0; padding-top:8px; display:flex; justify-content:flex-end; gap:8px; border-top:1px solid #f3f4f6; margin-top:8px;">
            @if($quotation->status === 'EVALUATED')
            <form method="POST" action="{{ route('cbe.accounting.purchase-quotations.convert-to-po', $quotation->rfq_id) }}" onsubmit="var d = prompt('{{ __('cbe_accounting.convert_bill_date_prompt') }}', '{{ now()->toDateString() }}'); if(d === null || d.trim() === '') { return false; } document.getElementById('rfq_po_date_{{ $quotation->rfq_id }}').value = d; return true;">
                @csrf
                <input type="hidden" id="rfq_po_date_{{ $quotation->rfq_id }}" name="po_date" value="">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.rfq_convert_to_po_button') }}</button>
            </form>
            @endif
            @if(!in_array($quotation->status, ['AWARDED', 'CANCELLED']))
            <form method="POST" action="{{ route('cbe.accounting.purchase-quotations.cancel', $quotation->rfq_id) }}" onsubmit="return confirm('{{ __('cbe_records.deactivate_confirm_js') }}');">
                @csrf
                <button type="submit" style="background:#fff; color:#e53935; border:1px solid #e53935; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.rfq_cancel_button') }}</button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
