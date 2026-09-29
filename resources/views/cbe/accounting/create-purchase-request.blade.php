@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_purchase_request_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_purchase_request_button') }}</div>
        <a href="{{ route('cbe.accounting.purchase-requests') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
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
        <form method="POST" action="{{ route('cbe.accounting.purchase-requests.store') }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px;">
                <div style="flex:1.2;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_supplier') }}</label>
                    <select name="supplier_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.field_dn_bill_none') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}" {{ old('supplier_id') == $s->supplier_id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width:120px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_date') }}</label>
                    <input type="date" name="request_date" value="{{ old('request_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:120px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_required_date') }}</label>
                    <input type="date" name="required_date" value="{{ old('required_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1.2;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_purpose') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                    <select name="cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.none_option') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}" {{ old('cost_centre_id') == $cc->centre_id ? 'selected' : '' }}>{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                    <select name="fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.none_option') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}" {{ old('fund_id') == $f->fund_id ? 'selected' : '' }}>{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1.2;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_remarks') }}</label>
                    <input type="text" name="remarks" value="{{ old('remarks') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:170px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_attachment') }}</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; font-size:9px; box-sizing:border-box;">
                </div>
            </div>

            <div style="flex-shrink:0; margin-bottom:4px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.pr_lines_helper_note') }}</div>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_category') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_line_qty') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_line_unit_price') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 0; $i < 5; $i++)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">
                                <input type="text" name="line_description[]" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                            </td>
                            <td style="padding:4px 6px;">
                                <select name="line_category[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_records.none_option') }}</option>
                                    @foreach($categories as $c)
                                    <option value="{{ $c->category_id }}">{{ $c->category_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_qty[]" value="1" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_unit_price[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.purchase-requests') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
