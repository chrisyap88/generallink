@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ar_hub_title'))

@section('content')

{{-- NEW 10 Sep 2026 (Task #398) — Accounts Receivable sub-hub, split out
     of the old single mega Accounting Hub. Process-flow order: entry
     screens first, enquiry next, reports last (per Chris: "you dont put
     reports before entry programs"). Customer master, Payment Terms and
     Payment Methods moved to the new Master Files hub. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ar_hub_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(!$hasNode)
    <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
    @else
    <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.invoices') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_invoices') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_invoices_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.ar-debit-notes') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_debit_notes') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_debit_notes_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.ar-credit-notes') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_credit_notes') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_credit_notes_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.donation-entry.create') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_donation_entry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_donation_entry_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.donation-pledges') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_donation_pledges') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_donation_pledges_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.ar-adjustments') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_adjustments') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_adjustments_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.invoice-enquiry') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_invoice_enquiry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_invoice_enquiry_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.receipt-enquiry') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_receipt_enquiry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_receipt_enquiry_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.ar-opening-balances') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_opening_balances') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_opening_balances_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.reports.ar-aging') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_aging') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_aging_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.fund-balance') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fund_balance') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_fund_balance_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.reports.ar-reports') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_ar_reports_hub') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_ar_reports_hub_desc') }}</div>
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
