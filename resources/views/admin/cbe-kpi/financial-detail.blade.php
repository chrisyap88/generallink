@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.box_financial_overview'))

@section('content')

{{-- NEW 27 Aug 2026 — Box 3 Financial Overview's 3-tab drill-down
(master spec Section 54), per Chris: "display the details of the
entries from the AR by individual row... Expenditure and payments is
to refer to AP ledger... a CBE may have more than one bank account, so
i need all bank statement." Income/Expense tabs read straight from the
journal (same source CbeAccountingService totals from), Bank Accounts
tab shows each account's selected-period + previous-period statement
balance. Same fixed-height, internal-scroll-only layout as the other
Box 6 drilldowns. --}}
<style>
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none; text-decoration:none; display:inline-block;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-fin-row{display:flex; align-items:center; justify-content:space-between; padding:4px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px; line-height:1.25; gap:8px;}
.cbe-fin-row:last-child{border-bottom:none;}
.cbe-fin-desc{color:#263238; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-fin-meta{color:#6b7280; font-size:7.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-fin-amt{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
.cbe-bank-card{display:flex; align-items:center; justify-content:space-between; padding:6px 12px; border-bottom:1px solid #eef2f7; font-size:8.5px; gap:10px;}
.cbe-bank-card:last-child{border-bottom:none;}
.cbe-bank-name{color:#263238; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-bank-meta{color:#6b7280; font-size:7.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-bank-stmt{text-align:center; flex-shrink:0; min-width:90px;}
.cbe-bank-stmt-label{font-size:7px; color:#94A3B8; font-weight:700; text-transform:uppercase;}
.cbe-bank-stmt-amt{font-size:9.5px; font-weight:700; color:#0d3c72;}
.cbe-bank-stmt-missing{font-size:7.5px; color:#c62828; font-style:italic;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_exec.box_financial_overview') }}</div>
            <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $scopeLabel }} — {{ $periodLabel }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', $backQuery) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn{{ $initialTab === 'income' ? ' active' : '' }}" data-tab="income" onclick="cbeFinTab('income')">{{ __('cbe_exec.tab_income') }} — RM {{ number_format($incomeTotal, 2) }}</div>
        <div class="cbe-tab-btn{{ $initialTab === 'expenses' ? ' active' : '' }}" data-tab="expenses" onclick="cbeFinTab('expenses')">{{ __('cbe_exec.tab_expenses') }} — RM {{ number_format($expenseTotal, 2) }}</div>
        <div class="cbe-tab-btn{{ $initialTab === 'bank' ? ' active' : '' }}" data-tab="bank" onclick="cbeFinTab('bank')">{{ __('cbe_exec.tab_bank_accounts') }} ({{ $bankRows->count() }})</div>
    </div>

    <div id="cbe-fin-income" style="display:{{ $initialTab === 'income' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($incomeEntries as $e)
            <div class="cbe-fin-row">
                <div style="min-width:0;">
                    <div class="cbe-fin-desc">{{ $e->description ?: $e->account_name }}</div>
                    <div class="cbe-fin-meta">{{ \Carbon\Carbon::parse($e->entry_date)->format('d M Y') }} · {{ $e->account_name }}</div>
                </div>
                <div class="cbe-fin-amt">RM {{ number_format($e->amount, 2) }}</div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-fin-expenses" style="display:{{ $initialTab === 'expenses' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($expenseEntries as $e)
            <div class="cbe-fin-row">
                <div style="min-width:0;">
                    <div class="cbe-fin-desc">{{ $e->description ?: $e->account_name }}</div>
                    <div class="cbe-fin-meta">{{ \Carbon\Carbon::parse($e->entry_date)->format('d M Y') }} · {{ $e->account_name }}</div>
                </div>
                <div class="cbe-fin-amt">RM {{ number_format($e->amount, 2) }}</div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-fin-bank" style="display:{{ $initialTab === 'bank' ? 'flex' : 'none' }}; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($bankRows as $row)
            <div class="cbe-bank-card">
                <div style="min-width:0; flex:1;">
                    <div class="cbe-bank-name">{{ $row['account']->bank_name }}@if($row['account']->account_name) — {{ $row['account']->account_name }}@endif</div>
                    <div class="cbe-bank-meta">{{ __('cbe_exec.bank_account_no') }}: {{ $row['account']->account_number }}</div>
                </div>
                <div class="cbe-bank-stmt">
                    <div class="cbe-bank-stmt-label">{{ $row['prevLabel'] }}</div>
                    @if($row['prevStmt'])
                    <div class="cbe-bank-stmt-amt">RM {{ number_format($row['prevStmt']->closing_balance ?? 0, 2) }}</div>
                    @else
                    <div class="cbe-bank-stmt-missing">{{ __('cbe_exec.bank_statement_missing') }}</div>
                    @endif
                </div>
                <div class="cbe-bank-stmt">
                    <div class="cbe-bank-stmt-label">{{ $row['curLabel'] }}</div>
                    @if($row['curStmt'])
                    <div class="cbe-bank-stmt-amt">RM {{ number_format($row['curStmt']->closing_balance ?? 0, 2) }}</div>
                    @else
                    <div class="cbe-bank-stmt-missing">{{ __('cbe_exec.bank_statement_missing') }}</div>
                    @endif
                </div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_exec.bank_no_accounts') }}</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function cbeFinTab(tab){
    ['income','expenses','bank'].forEach(function(t){
        document.getElementById('cbe-fin-'+t).style.display = (t === tab) ? 'flex' : 'none';
    });
    document.querySelectorAll('.cbe-tab-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-tab') === tab); });
}
</script>
@endsection
