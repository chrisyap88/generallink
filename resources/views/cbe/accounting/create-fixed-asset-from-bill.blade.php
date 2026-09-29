@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.create_fixed_asset_from_bill_title'))

@section('content')

{{-- UPDATED 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade: widened
     to a 2-column layout to add Category/Location/Department/Fund and
     Depreciation Method, matching the standalone create-fixed-asset
     screen. Does not post a second GL journal — the Bill's own journal
     already recorded the acquisition (see
     CbeAccountingService::createFixedAssetFromBill()). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.create_fixed_asset_from_bill_title') }} — {{ $b->bill_no ?: $b->doc_ref_no }}</div>
        <a href="{{ route('cbe.accounting.bill-enquiry.show', $b->bill_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px 16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.bill-enquiry.store-fixed-asset', $b->bill_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; font-size:8.5px; color:#9ca3af; margin-bottom:6px;">{{ __('cbe_accounting.fixed_asset_from_bill_helper_note', ['supplier' => $b->supplier_name]) }}</div>
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; align-content:start;">
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_name') }}</label>
                    <input type="text" name="asset_name" value="{{ old('asset_name', $b->description) }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_tag') }}</label>
                    <input type="text" name="asset_tag" value="{{ old('asset_tag') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_category') }}</label>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->category_id }}">{{ $c->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_location') }}</label>
                    <select name="location_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($locations as $l)
                        <option value="{{ $l->location_id }}">{{ $l->location_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                    <select name="cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}">{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                    <select name="fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}">{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_acquisition_cost') }}</label>
                        <input type="number" step="0.01" min="0.01" name="acquisition_cost" value="{{ old('acquisition_cost', $b->amount) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_salvage_value') }}</label>
                        <input type="number" step="0.01" min="0" name="salvage_value" value="{{ old('salvage_value', 0) }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_useful_life_months') }}</label>
                        <input type="number" step="1" min="1" name="useful_life_months" value="{{ old('useful_life_months', 60) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_depreciation_method') }}</label>
                        <select name="depreciation_method" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                            <option value="STRAIGHT_LINE">{{ __('cbe_accounting.depr_method_straight_line') }}</option>
                            <option value="REDUCING_BALANCE">{{ __('cbe_accounting.depr_method_reducing_balance') }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_acquired_date') }}</label>
                    <input type="text" value="{{ \Carbon\Carbon::parse($b->bill_date)->format('d M Y') }}" disabled style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box; background:#f3f4f6; color:#6b7280;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_no') }}</label>
                    <input type="text" value="{{ $b->bill_no ?: '—' }}" disabled style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box; background:#f3f4f6; color:#6b7280;">
                </div>

                <div style="grid-column:1 / -1; font-size:8.5px; color:#9ca3af; line-height:1.5;">{{ __('cbe_accounting.fixed_asset_helper_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:8px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.bill-enquiry.show', $b->bill_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
