@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_fixed_asset_button'))

@section('content')

{{-- UPDATED 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, spec
     section 1.1 (Asset Master): widened to a 2-column layout to fit
     Category/Location/Department/Fund/Supplier/Invoice No./
     Capitalisation Date/Depreciation Method/Depreciation Start Date
     alongside the original fields, still on one non-scrolling screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_fixed_asset_button') }}</div>
        <a href="{{ route('cbe.accounting.fixed-assets') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px 16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.fixed-assets.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:8px 16px; align-content:start;">
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_name') }}</label>
                    <input type="text" name="asset_name" value="{{ old('asset_name') }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
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
                        <option value="{{ $c->category_id }}" data-zh="{{ $c->category_name_zh }}" {{ old('category_id') == $c->category_id ? 'selected' : '' }}>{{ $c->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_location') }}</label>
                    <select name="location_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($locations as $l)
                        <option value="{{ $l->location_id }}" {{ old('location_id') == $l->location_id ? 'selected' : '' }}>{{ $l->location_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                    <select name="cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}" {{ old('cost_centre_id') == $cc->centre_id ? 'selected' : '' }}>{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                    <select name="fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}" {{ old('fund_id') == $f->fund_id ? 'selected' : '' }}>{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_supplier') }}</label>
                    <select name="supplier_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}" {{ old('supplier_id') == $s->supplier_id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_no') }}</label>
                    <input type="text" name="invoice_no" value="{{ old('invoice_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_acquisition_cost') }}</label>
                        <input type="number" step="0.01" min="0.01" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
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

                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_acquired_date') }}</label>
                        <input type="date" name="acquired_date" value="{{ old('acquired_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_capitalisation_date') }}</label>
                        <input type="date" name="capitalisation_date" value="{{ old('capitalisation_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_depreciation_start_date') }}</label>
                    <input type="date" name="depreciation_start_date" value="{{ old('depreciation_start_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>

                <div style="grid-column:1 / -1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_funding_source') }}</label>
                    <input type="text" name="funding_source" value="{{ old('funding_source') }}" maxlength="150" placeholder="{{ __('cbe_accounting.field_asset_funding_source_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="grid-column:1 / -1; font-size:8.5px; color:#9ca3af; line-height:1.5;">{{ __('cbe_accounting.fixed_asset_helper_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:8px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.fixed-assets') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest for
    // Asset Category, matched against the Asset Name box using his own
    // asset category list (land/building/machinery/computer/motor/van/
    // aircond/furniture/bike, etc.). Only fills it if still on the
    // placeholder -- never overrides a category already picked.
    var HINTS = {!! json_encode($assetHints) !!};
    var nameInput = document.querySelector('input[name="asset_name"]');
    var sel = document.querySelector('select[name="category_id"]');
    function suggest() {
        if (!sel || sel.value !== '') { return; }
        var text = nameInput.value.toLowerCase();
        if (!text) { return; }
        for (var i = 0; i < HINTS.length; i++) {
            var rule = HINTS[i];
            var kwHit = false;
            for (var k = 0; k < rule.keywords.length; k++) {
                if (text.indexOf(rule.keywords[k]) !== -1) { kwHit = true; break; }
            }
            if (!kwHit) { continue; }
            var opts = sel.options;
            for (var j = 0; j < opts.length; j++) {
                var optText = (opts[j].textContent + ' ' + (opts[j].getAttribute('data-zh') || '')).toLowerCase();
                var hintHit = false;
                for (var h = 0; h < rule.hints.length; h++) {
                    if (optText.indexOf(rule.hints[h]) !== -1) { hintHit = true; break; }
                }
                if (hintHit) {
                    sel.value = opts[j].value;
                    sel.style.background = '#eef7ee';
                    return;
                }
            }
        }
    }
    if (nameInput) { nameInput.addEventListener('blur', suggest); }
})();
</script>
@endsection
