@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ap_reports_hub_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #375) — AP Reports hub: downloadable Excel
     reports beyond AP Aging (built earlier as its own direct link).
     Mirrors ar-reports-hub.blade.php exactly. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ap_reports_hub_page_title') }}</div>
        <a href="{{ route('cbe.accounting.suppliers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_suppliers') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:10px;">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; flex-shrink:0;">
            <a href="{{ route('cbe.accounting.reports.supplier-statement-picker') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.supplier_statement_title') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-outstanding-payables') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_outstanding_payables') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-aging') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_ap_aging') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.bill-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_bill_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-payment-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_payment_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-credit-note-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_credit_note_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-debit-note-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_debit_note_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.supplier-balance') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_supplier_balance') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.monthly-ap-summary') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_monthly_ap_summary') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ap-gl-reconciliation') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_ap_gl_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
        </div>

        <div style="border-top:1px solid #f3f4f6; padding-top:10px; display:flex; flex-direction:column; gap:8px;">
            <form method="GET" action="{{ route('cbe.accounting.reports.ap-transaction-report') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_ap_transaction_report') }}</div>
                    <div style="display:flex; gap:8px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
                            <input type="date" name="from_date" value="{{ now()->startOfMonth()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
                            <input type="date" name="to_date" value="{{ now()->toDateString() }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.download_button') }}</button>
            </form>

            <form method="GET" action="{{ route('cbe.accounting.reports.expense-summary-by-supplier') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_expense_by_supplier') }}</div>
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

            <form method="GET" action="{{ route('cbe.accounting.reports.expense-summary-by-category') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_expense_by_category') }}</div>
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
        </div>
    </div>
</div>
@endsection
