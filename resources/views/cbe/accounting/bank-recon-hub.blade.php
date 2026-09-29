@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.bank_recon_hub_title'))

@section('content')

{{-- NEW 10 Sep 2026 (Task #398) — Bank Reconciliation sub-hub, split out
     of the old single mega Accounting Hub. Bank Account Number, Bank
     Transaction Types and Reconciliation Rules moved to the new Master
     Files hub — they are setup, not day-to-day transactions. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.bank_recon_hub_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(!$hasNode)
    <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
    @else
    <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.bank-reconciliations') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_reconciliation') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_reconciliation_desc') }}</div>
            </a>
            <a href="{{ route('cbe.finance.transfers.create') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_transfer') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_transfer_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.petty-cash-funds') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_petty_cash') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_petty_cash_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.bank-account-enquiry') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_account_enquiry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_account_enquiry_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.bank-transaction-enquiry') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_transaction_enquiry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_transaction_enquiry_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.unmatched-transaction-enquiry') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_unmatched_transaction_enquiry') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_unmatched_transaction_enquiry_desc') }}</div>
            </a>
        </div>
        <div style="flex-shrink:0; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.reports.cash-bank-position') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; align-items:center; text-decoration:none;">
                <div style="flex:1;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_cash_bank_position') }}</div>
                    <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.tile_cash_bank_position_desc') }}</div>
                </div>
            </a>
            <a href="{{ route('cbe.accounting.bank-reconciliation-reports-hub') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; align-items:center; text-decoration:none;">
                <div style="flex:1;">
                    <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_reconciliation_reports_hub') }}</div>
                    <div style="font-size:8.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_accounting.tile_bank_reconciliation_reports_hub_desc') }}</div>
                </div>
            </a>
        </div>
    </div>
    @endif
</div>
@endsection
