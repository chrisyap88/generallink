@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.master_files_hub_title'))

@section('content')

{{-- NEW 10 Sep 2026 (Task #398) — per Chris: every master/reference table
     used across AR/AP/GL/FA/Bank Reconciliation, centralised in one place
     instead of scattered across each module ("all module should
     centralized in one module call master file maintenance"). Nothing
     new is built here — every link below reuses an existing screen. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.master_files_hub_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(!$hasNode)
    <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
    @else
    <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_entity_maintenance') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_entity_maintenance_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.customers') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_debtor_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_debtor_master_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.customer-categories') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_debtor_category') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_debtor_category_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.suppliers') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_creditor_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_creditor_master_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.supplier-categories') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_creditor_category') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_creditor_category_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_chart_of_accounts') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_chart_of_accounts_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.account-categories') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_transaction_type') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_transaction_type_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.document-number-control') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_doc_number_control') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_doc_number_control_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.asset-categories') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fa_category') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_fa_category_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.asset-locations') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fa_location') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_fa_location_desc') }}</div>
            </a>
            <a href="{{ route('cbe.finance.bank-accounts') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_account_number') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_account_number_desc') }}</div>
            </a>
            <a href="{{ route('cbe.finance.bank-transaction-types') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_bank_transaction_types_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_bank_transaction_types_master_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.bank-reconciliation-rules') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_reconciliation_rules') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_reconciliation_rules_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.payment-terms') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_payment_terms_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_payment_terms_master_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.payment-methods') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_payment_methods_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_payment_methods_master_desc') }}</div>
            </a>
        </div>
        <div style="flex:1; display:flex; gap:8px;">
            <a href="{{ route('cbe.accounting.funds') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_fund_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_fund_master_desc') }}</div>
            </a>
            <a href="{{ route('cbe.accounting.tax-rates') }}" style="flex:1; background:#fff; border:1px solid #d1d5db; border-left:3px solid var(--gl-blue); border-radius:0 8px 8px 0; padding:10px 12px; display:flex; flex-direction:column; text-decoration:none;">
                <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_tax_rates_master') }}</div>
                <div style="font-size:8.5px; color:#6b7280; margin-top:3px; flex:1;">{{ __('cbe_accounting.tile_tax_rates_master_desc') }}</div>
            </a>
            <div style="flex:1;"></div>
        </div>
    </div>
    @endif
</div>
@endsection
