@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_supplier_invoice_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_supplier_invoice_button') }} — {{ $receipt->doc_ref_no }}</div>
        <a href="{{ route('cbe.accounting.goods-receipts.show', $receipt->grn_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.supplier-invoice.store', $receipt->grn_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_bill_no') }}</label>
                    <input type="text" name="bill_no" value="{{ old('bill_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:130px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_bill_date') }}</label>
                    <input type="date" name="bill_date" value="{{ old('bill_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:130px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_due_date') }}</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>

            <div style="flex-shrink:0; margin-bottom:4px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.invoice_match_helper_note') }}</div>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_grn_received') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_po_unit_price') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:80px;">{{ __('cbe_accounting.col_invoiced_qty') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:90px;">{{ __('cbe_accounting.col_invoiced_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $l)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#263238;">
                                {{ $l->description ?: '—' }}
                                <input type="hidden" name="grn_line_id[]" value="{{ $l->grn_line_id }}">
                                <input type="hidden" name="po_line_id[]" value="{{ $l->po_line_id }}">
                                <input type="hidden" name="line_description[]" value="{{ $l->description }}">
                                <input type="hidden" name="line_category_id[]" value="{{ $l->category_id }}">
                            </td>
                            <td style="padding:4px 6px; text-align:right; color:#6b7280;">{{ rtrim(rtrim(number_format($l->received_quantity, 2), '0'), '.') }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#6b7280;">{{ $l->po_unit_price !== null ? 'RM '.number_format($l->po_unit_price, 2) : '—' }}</td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="invoiced_qty[]" value="{{ $l->received_quantity }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="invoiced_unit_price[]" value="{{ $l->po_unit_price }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.goods-receipts.show', $receipt->grn_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
