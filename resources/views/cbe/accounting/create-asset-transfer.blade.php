@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_asset_transfer_button'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #395) — Fixed Asset Module upgrade, spec
     section 12 (Asset Transfer). Updates the SAME asset row — never
     duplicates it — and logs a full from/to history row. No GL entry:
     moving an asset between locations/departments/funds is not a
     monetary event. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_asset_transfer_button') }} — {{ $asset->asset_name }}</div>
        <a href="{{ route('cbe.accounting.fixed-assets.show', $asset->asset_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.fixed-assets.transfer.store', $asset->asset_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:10px; max-width:560px;">
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_transfer_date') }}</label>
                    <input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required style="width:220px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; font-size:9px; color:#9ca3af; text-transform:uppercase; font-weight:700;">
                    <div>{{ __('cbe_accounting.col_transfer_from') }}</div>
                    <div>{{ __('cbe_accounting.col_transfer_to') }}</div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; align-items:center;">
                    <div style="font-size:11px; color:#263238; background:#f3f4f6; border-radius:6px; padding:7px 10px;">{{ __('cbe_accounting.field_asset_location') }}: {{ $fromLocationName ?: '—' }}</div>
                    <select name="to_location_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($locations as $l)
                        <option value="{{ $l->location_id }}" {{ old('to_location_id') == $l->location_id ? 'selected' : '' }}>{{ $l->location_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; align-items:center;">
                    <div style="font-size:11px; color:#263238; background:#f3f4f6; border-radius:6px; padding:7px 10px;">{{ __('cbe_accounting.field_pr_cost_centre') }}: {{ $fromCentreName ?: '—' }}</div>
                    <select name="to_cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}" {{ old('to_cost_centre_id') == $cc->centre_id ? 'selected' : '' }}>{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 16px; align-items:center;">
                    <div style="font-size:11px; color:#263238; background:#f3f4f6; border-radius:6px; padding:7px 10px;">{{ __('cbe_accounting.field_pr_fund') }}: {{ $fromFundName ?: '—' }}</div>
                    <select name="to_fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}" {{ old('to_fund_id') == $f->fund_id ? 'selected' : '' }}>{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_transfer_reason') }}</label>
                    <input type="text" name="reason" value="{{ old('reason') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="font-size:9px; color:#9ca3af; line-height:1.5;">{{ __('cbe_accounting.asset_transfer_helper_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.fixed-assets.show', $asset->asset_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
