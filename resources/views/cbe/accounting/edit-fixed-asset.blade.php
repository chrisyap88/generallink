@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.edit_fixed_asset_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.edit_fixed_asset_button') }}</div>
        <a href="{{ route('cbe.accounting.fixed-assets.show', $asset->asset_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.fixed-assets.update', $asset->asset_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            @method('PUT')
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; align-content:start;">
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_name') }}</label>
                    <input type="text" name="asset_name" value="{{ old('asset_name', $asset->asset_name) }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_tag') }}</label>
                    <input type="text" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_category') }}</label>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->category_id }}" {{ old('category_id', $asset->category_id) == $c->category_id ? 'selected' : '' }}>{{ $c->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_location') }}</label>
                    <select name="location_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($locations as $l)
                        <option value="{{ $l->location_id }}" {{ old('location_id', $asset->location_id) == $l->location_id ? 'selected' : '' }}>{{ $l->location_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                    <select name="cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}" {{ old('cost_centre_id', $asset->cost_centre_id) == $cc->centre_id ? 'selected' : '' }}>{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                    <select name="fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}" {{ old('fund_id', $asset->fund_id) == $f->fund_id ? 'selected' : '' }}>{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_supplier') }}</label>
                    <select name="supplier_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}" {{ old('supplier_id', $asset->supplier_id) == $s->supplier_id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_no') }}</label>
                    <input type="text" name="invoice_no" value="{{ old('invoice_no', $asset->invoice_no) }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                <div style="grid-column:1 / -1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_funding_source') }}</label>
                    <input type="text" name="funding_source" value="{{ old('funding_source', $asset->funding_source) }}" maxlength="150" placeholder="{{ __('cbe_accounting.field_asset_funding_source_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                {{-- Locked fields — shown for reference only, not editable, since they
                     already drove the capitalization journal and every depreciation
                     amount calculated since. --}}
                <div style="grid-column:1 / -1; margin-top:4px; padding-top:8px; border-top:1px solid #f3f4f6; display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; font-size:10px;">
                    <div><span style="color:#9ca3af; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_acquisition_cost') }}</span><br>RM {{ number_format($asset->acquisition_cost, 2) }}</div>
                    <div><span style="color:#9ca3af; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_useful_life_months') }}</span><br>{{ $asset->useful_life_months }}</div>
                    <div><span style="color:#9ca3af; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_acquired_date') }}</span><br>{{ \Carbon\Carbon::parse($asset->acquired_date)->format('d M Y') }}</div>
                </div>
                <div style="grid-column:1 / -1; font-size:8.5px; color:#9ca3af; line-height:1.5;">{{ __('cbe_accounting.edit_fixed_asset_locked_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.fixed-assets.show', $asset->asset_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
