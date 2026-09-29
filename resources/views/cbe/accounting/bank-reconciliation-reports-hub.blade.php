@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.br_reports_hub_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 5: Reports hub. Mirrors fixed-asset-reports-hub.blade.php /
     gl-reports-hub.blade.php exactly — a grid of one-click Excel
     downloads, plus small forms below for the 2 reports that need a
     parameter (date range / which reconciliation). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.br_reports_hub_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.bank-reconciliation-audit-log') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.br_audit_log_page_title') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.bank_reconciliations_page_title') }}</a>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:10px;">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; flex-shrink:0;">
            <a href="{{ route('cbe.accounting.reports.bank-account-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bank_account_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.outstanding-cheques') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_outstanding_cheques') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.deposits-in-transit') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_deposits_in_transit') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.unmatched-bank-transactions') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_unmatched_bank_transactions') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bank-charges') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bank_charges') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bank-interest') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bank_interest') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bank-reconciliation-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_reconciliation_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bank-reconciliation-gl') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bank_gl_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
        </div>

        <div style="border-top:1px solid #f3f4f6; padding-top:10px; display:flex; flex-direction:column; gap:8px;">
            <form method="GET" action="{{ route('cbe.accounting.reports.bank-transaction-report') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:400px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_bank_transaction_report') }} — {{ __('cbe_accounting.report_custom_range_note') }}</div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
                            <input type="date" name="from" value="{{ now()->startOfMonth()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
                            <input type="date" name="to" value="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>

            <div style="flex:1; max-width:400px;">
                <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_bank_reconciliation_statement') }}</div>
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <select id="brStmtSelect" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($reconciliations as $r)
                        <option value="{{ route('cbe.accounting.reports.bank-reconciliation-statement', $r->reconciliation_id) }}">{{ $r->reconciliation_no ?: \Carbon\Carbon::parse($r->statement_date)->format('d/m/Y') }} — {{ $r->bank_name ?: __('cbe_accounting.field_bank_account_default') }}</option>
                        @endforeach
                    </select>
                    <button type="button" onclick="var u=document.getElementById('brStmtSelect').value; if(u){window.location=u;}" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
