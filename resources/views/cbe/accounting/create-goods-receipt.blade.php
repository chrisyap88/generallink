@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_goods_receipt_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_goods_receipt_button') }} — {{ $order->doc_ref_no }}</div>
        <a href="{{ route('cbe.accounting.purchase-orders.show', $order->po_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
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
        <form method="POST" action="{{ route('cbe.accounting.goods-receipts.store', $order->po_id) }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px;">
                <div style="width:130px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_grn_date') }}</label>
                    <input type="date" name="grn_date" value="{{ old('grn_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:150px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_grn_type') }}</label>
                    <select name="receipt_type" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="GOODS">{{ __('cbe_accounting.grn_type_goods') }}</option>
                        <option value="SERVICE">{{ __('cbe_accounting.grn_type_service') }}</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_remarks') }}</label>
                    <input type="text" name="remarks" value="{{ old('remarks') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:170px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_attachment') }}</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; font-size:9px; box-sizing:border-box;">
                </div>
            </div>

            <div style="flex-shrink:0; margin-bottom:4px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.grn_lines_helper_note') }}</div>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_grn_ordered') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_grn_outstanding') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:80px;">{{ __('cbe_accounting.col_grn_received') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:80px;">{{ __('cbe_accounting.col_grn_rejected') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_grn_condition') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lines as $l)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px; color:#263238;">
                                {{ $l->description ?: '—' }}
                                <input type="hidden" name="po_line_id[]" value="{{ $l->line_id }}">
                                <input type="hidden" name="line_description[]" value="{{ $l->description }}">
                                <input type="hidden" name="ordered_qty[]" value="{{ $l->quantity }}">
                            </td>
                            <td style="padding:4px 6px; text-align:right; color:#6b7280;">{{ rtrim(rtrim(number_format($l->quantity, 2), '0'), '.') }}</td>
                            <td style="padding:4px 6px; text-align:right; color:#6b7280;">{{ rtrim(rtrim(number_format($l->outstanding_quantity, 2), '0'), '.') }}</td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" max="{{ $l->outstanding_quantity }}" name="received_qty[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="rejected_qty[]" value="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <select name="condition_note[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="GOOD">{{ __('cbe_accounting.grn_condition_good') }}</option>
                                    <option value="DAMAGED">{{ __('cbe_accounting.grn_condition_damaged') }}</option>
                                    <option value="SHORT">{{ __('cbe_accounting.grn_condition_short') }}</option>
                                </select>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.purchase-orders.show', $order->po_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
