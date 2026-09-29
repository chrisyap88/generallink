@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.gl_reports_hub_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #385) — GL Reports hub: downloadable Excel
     reports beyond Trial Balance / Balance Sheet / P&L / General Ledger
     (already built earlier as their own direct links). Mirrors
     ap-reports-hub.blade.php / ar-reports-hub.blade.php. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.gl_reports_hub_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:10px;">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; flex-shrink:0;">
            <a href="{{ route('cbe.accounting.reports.journal-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_journal_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.gl-account-balance') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_gl_account_balance') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.gl-monthly-summary') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_gl_monthly_summary') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.unposted-journals') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_unposted_journals') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.reversal-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_reversal_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.adjustment-journal-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_adjustment_journal_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.fa-gl-reconciliation') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_fa_gl_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bank-reconciliation-gl') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bank_reconciliation_gl') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
        </div>

        <div style="border-top:1px solid #f3f4f6; padding-top:10px; display:flex; flex-direction:column; gap:8px;">
            <form method="GET" action="{{ route('cbe.accounting.reports.journal-listing') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_journal_listing') }} — {{ __('cbe_accounting.report_custom_range_note') }}</div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
                            <input type="date" name="from_date" value="{{ now()->startOfYear()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
                            <input type="date" name="to_date" value="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>

            <form method="GET" action="{{ route('cbe.accounting.reports.gl-monthly-summary') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:300px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_gl_monthly_summary') }} — {{ __('cbe_accounting.report_custom_range_note') }}</div>
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_year') }}</label>
                    <input type="number" name="year" value="{{ now()->year }}" min="2020" max="2099" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
