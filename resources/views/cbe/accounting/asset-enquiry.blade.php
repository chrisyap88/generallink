@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.asset_enquiry_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #395 Phase 3) — Asset Enquiry: on-screen search
     across every fixed asset by keyword (name/tag)/category/location/
     department/fund/status/supplier. Mirrors ap-payment-enquiry.blade.php
     ("search then list" pattern), extended to a 2-row filter form since
     there are more filters here than any other Enquiry screen in the
     app. Results only render after the first search, keeping this
     screen fixed-height/no-scroll either way. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.asset_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.fixed-assets') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.tile_fixed_assets') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.asset-enquiry') }}" style="flex-shrink:0; display:flex; flex-direction:column; gap:6px; margin-bottom:8px;">
            <div style="display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr; gap:8px;">
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_search_keyword') }}</label>
                    <input type="text" name="keyword" value="{{ $keyword }}" placeholder="{{ __('cbe_accounting.field_asset_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_category') }}</label>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->category_id }}" {{ $categoryId == $c->category_id ? 'selected' : '' }}>{{ $c->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_asset_location') }}</label>
                    <select name="location_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($locations as $l)
                        <option value="{{ $l->location_id }}" {{ $locationId == $l->location_id ? 'selected' : '' }}>{{ $l->location_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                    <select name="cost_centre_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($costCentres as $cc)
                        <option value="{{ $cc->centre_id }}" {{ $costCentreId == $cc->centre_id ? 'selected' : '' }}>{{ $cc->centre_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1.3fr; gap:8px; align-items:flex-end;">
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                    <select name="fund_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($funds as $f)
                        <option value="{{ $f->fund_id }}" {{ $fundId == $f->fund_id ? 'selected' : '' }}>{{ $f->fund_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_supplier') }}</label>
                    <select name="supplier_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}" {{ $supplierId == $s->supplier_id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.col_status') }}</label>
                    <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>{{ __('cbe_accounting.fa_status_active') }}</option>
                        <option value="FULLY_DEPRECIATED" {{ $status === 'FULLY_DEPRECIATED' ? 'selected' : '' }}>{{ __('cbe_accounting.fa_status_fully_depreciated') }}</option>
                        <option value="DISPOSED" {{ $status === 'DISPOSED' ? 'selected' : '' }}>{{ __('cbe_accounting.fa_status_disposed') }}</option>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" name="search" value="1" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
                    <a href="{{ route('cbe.accounting.asset-enquiry') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
                </div>
            </div>
        </form>

        @if(! $searched)
        <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_accounting.asset_enquiry_start_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_asset_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_asset_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_asset_location') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_pr_fund') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_net_book_value') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $a)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.fixed-assets.show', $a->asset_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ $a->asset_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->category_name ?: ($a->asset_class ?: '—') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->location_name ?: ($a->location ?: '—') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->fund_name ?: '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#2e7d32;">RM {{ number_format($a->net_book_value, 2) }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ __('cbe_accounting.fa_status_'.strtolower($a->status)) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.asset_enquiry_no_results_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($assets->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $assets->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $assets->currentPage(), 'last' => $assets->lastPage(), 'total' => $assets->total()]) }}</span>
            @if($assets->hasMorePages())
                <a href="{{ $assets->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
