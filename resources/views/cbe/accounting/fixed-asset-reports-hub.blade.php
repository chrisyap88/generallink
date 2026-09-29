@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.fa_reports_hub_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #395 Phase 3) — Fixed Asset Reports hub:
     downloadable Excel reports beyond the on-screen Fixed Assets list
     and Asset Enquiry search. Mirrors gl-reports-hub.blade.php /
     purchasing-reports-hub.blade.php exactly. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.fa_reports_hub_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.fixed-asset-audit-log') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.fixed_asset_audit_log_page_title') }}</a>
            <a href="{{ route('cbe.accounting.fixed-assets') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.tile_fixed_assets') }}</a>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:10px;">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; flex-shrink:0;">
            <a href="{{ route('cbe.accounting.reports.fixed-asset-schedule') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_asset_register') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.fixed-asset-disposal-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fixed_asset_disposal_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.depreciation-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_depreciation_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.asset-write-off') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_asset_write_off') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.asset-by-location') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_asset_by_location') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.asset-by-department') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_asset_by_department') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.asset-by-fund') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_asset_by_fund') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.fa-gl-reconciliation') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_fa_gl_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.asset-enquiry') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.asset_enquiry_page_title') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_search_note') }}</div>
            </a>
        </div>

        <div style="border-top:1px solid #f3f4f6; padding-top:10px; display:flex; flex-direction:column; gap:8px;">
            <form method="GET" action="{{ route('cbe.accounting.reports.asset-acquisition') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:400px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_asset_acquisition') }} — {{ __('cbe_accounting.report_custom_range_note') }}</div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
                            <input type="date" name="from" value="{{ now()->startOfYear()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
                            <input type="date" name="to" value="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>

            <form method="GET" action="{{ route('cbe.accounting.reports.asset-transfer') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:400px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_asset_transfer') }} — {{ __('cbe_accounting.report_custom_range_note') }}</div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
                            <input type="date" name="from" value="{{ now()->startOfYear()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
                            <input type="date" name="to" value="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
