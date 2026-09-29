@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.finance_title'))

@section('content')

<style>
.ed-box{flex:1; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; padding:10px 12px; display:flex; flex-direction:column; min-width:0; box-sizing:border-box; box-shadow:0 2px 10px rgba(21,101,192,0.10);}
.ed-box-title{font-size:9px; font-weight:700; color:var(--gl-blue); text-transform:uppercase; letter-spacing:0.3px; margin-bottom:6px; padding-bottom:5px; border-bottom:1px solid #eef2f7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-rows{flex:1; display:flex; flex-direction:column; gap:3px; justify-content:center; min-height:0;}
.ed-row{display:flex; justify-content:space-between; align-items:center; font-size:8px; gap:6px;}
.ed-row span:first-child{color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-row span:last-child{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
.ed-lock{font-size:7.5px; color:#92700a; background:#FFF8E1; border-radius:4px; padding:3px 6px; margin-top:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.ed-link{color:var(--gl-blue); text-decoration:none; font-size:8px; font-weight:600;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_exec.finance_title') }}</div>
            <div style="font-size:9px; color:#6b7280;">{{ $officer->node_name }}{{ $officer->node_name_zh ? ' ('.$officer->node_name_zh.')' : '' }} — {{ $officer->group_name }}</div>
        </div>
        <span style="background:{{ $isPaid ? '#2e7d32' : '#94A3B8' }}; color:#fff; border-radius:12px; padding:4px 12px; font-size:8.5px; font-weight:700;">{{ $isPaid ? __('cbe_exec.tier_paid') : __('cbe_exec.tier_free') }}</span>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_cash_bank') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_cash_bank_balance') }}</span><span>RM {{ number_format($financial['cash_balance'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_month_cash_flow') }}</span><span style="color:{{ $financialMonth['net_surplus'] >= 0 ? '#2e7d32' : '#c62828' }};">RM {{ number_format($financialMonth['net_surplus'], 2) }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_income_revenue') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_income_month') }}</span><span>RM {{ number_format($financialMonth['total_income'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_income_ytd') }}</span><span>RM {{ number_format($financial['total_income'], 2) }}</span></div>
                @if(!$isPaid)
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'top income sources & budget vs actual']) }}</div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_expenses_spending') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_expense_month') }}</span><span>RM {{ number_format($financialMonth['total_expense'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_expense_ytd') }}</span><span>RM {{ number_format($financial['total_expense'], 2) }}</span></div>
                @if(!$isPaid)
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'top expense categories & budget vs actual']) }}</div>
                @endif
            </div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:8px;">
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_ar') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_ar_outstanding') }}</span><span>RM {{ number_format($arOutstanding, 2) }}</span></div>
                @if(!$isPaid)
                <div class="ed-lock">{{ __('cbe_exec.upgrade_locked', ['feature' => 'AR aging & collection rate']) }}</div>
                @endif
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_ap') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_ap_outstanding') }}</span><span>RM {{ number_format($financial['ap_outstanding'], 2) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_bills_due_soon') }}</span><span>{{ number_format($billsDueSoon) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_bills_overdue') }}</span><span style="color:{{ $billsOverdue > 0 ? '#c62828' : 'var(--gl-blue)' }};">{{ number_format($billsOverdue) }}</span></div>
            </div>
        </div>
        <div class="ed-box">
            <div class="ed-box-title">{{ __('cbe_exec.box_pending_finance') }}</div>
            <div class="ed-rows">
                <div class="ed-row"><span>{{ __('cbe_exec.row_bills_awaiting') }}</span><span>{{ number_format($apBillCount) }}</span></div>
                <div class="ed-row"><span>{{ __('cbe_exec.row_unreconciled') }}</span><span>{{ __('cbe_exec.coming_soon') }}</span></div>
            </div>
        </div>
    </div>

    <div style="flex:1.1; min-height:0;">
        <div class="ed-box" style="height:100%;">
            <div class="ed-box-title">{{ __('cbe_exec.box_financial_performance') }}</div>
            <div style="flex:1; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <a href="{{ route('cbe.accounting.chart-of-accounts') }}" class="ed-link">📒 {{ __('cbe_exec.link_chart_of_accounts') }}</a>
                <a href="{{ route('cbe.accounting.reports.trial-balance') }}" class="ed-link">⚖️ {{ __('cbe_exec.link_trial_balance') }}</a>
                <a href="{{ route('cbe.accounting.reports.balance-sheet') }}" class="ed-link">🏦 {{ __('cbe_exec.link_balance_sheet') }}</a>
                <a href="{{ route('cbe.accounting.reports.profit-loss') }}" class="ed-link">📈 {{ __('cbe_exec.link_profit_loss') }}</a>
                <a href="{{ route('cbe.accounting.reports.general-ledger') }}" class="ed-link">📚 {{ __('cbe_exec.link_general_ledger') }}</a>
                <a href="{{ route('cbe.accounting.reports.ap-aging') }}" class="ed-link">⏳ {{ __('cbe_exec.link_ap_aging') }}</a>
                <a href="{{ route('cbe.accounting.suppliers') }}" class="ed-link">🏭 {{ __('cbe_exec.link_suppliers') }}</a>
                <a href="{{ route('cbe.accounting.bills') }}" class="ed-link">🧾 {{ __('cbe_exec.link_bills') }}</a>
            </div>
        </div>
    </div>
</div>
@endsection
