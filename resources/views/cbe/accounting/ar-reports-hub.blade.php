@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ar_reports_hub_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #360) — AR Reports hub: 9 downloadable Excel
     reports beyond Debtor Ledger/Statement and AR Aging (built earlier
     under their own picker/direct links). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ar_reports_hub_page_title') }}</div>
        <a href="{{ route('cbe.accounting.invoices') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_invoices') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:10px;">

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; flex-shrink:0;">
            <a href="{{ route('cbe.accounting.reports.invoice-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_invoice_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.receipt-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_receipt_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ar-debit-note-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_debit_note_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ar-credit-note-listing') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_credit_note_listing') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.outstanding-receivables') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_outstanding_receivables') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.debtor-balance') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_debtor_balance') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.monthly-ar-summary') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_monthly_ar_summary') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.report_current_year_note') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ar-gl-reconciliation') }}" style="display:block; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:10px 12px; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.report_ar_gl_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.download_button') }}</div>
            </a>
        </div>

        <div style="border-top:1px solid #f3f4f6; padding-top:10px; display:flex; flex-direction:column; gap:8px;">
            <form method="GET" action="{{ route('cbe.accounting.reports.ar-transaction-report') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_ar_transaction_report') }}</div>
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

            <form method="GET" action="{{ route('cbe.accounting.reports.payment-collection') }}" style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1; max-width:600px;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_accounting.report_payment_collection') }}</div>
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
        </div>
    </div>
</div>
@endsection
