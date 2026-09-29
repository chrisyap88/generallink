@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.row_bills_due_soon'))

@section('content')

{{-- NEW 27 Aug 2026 — Box 6's merged "Bills Due/Overdue" row drill-down
(master spec Section 55 follow-up), per Chris: "group the bill due and
overdue into one when drill down show 2 tap folder." Same list style as
nodes.blade.php — fixed-height page, only the row list scrolls
internally, 2 tab folders instead of city tabs. --}}
<style>
.cbe-tab-btn{padding:5px 14px; font-size:9px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; user-select:none; text-decoration:none; display:inline-block;}
.cbe-tab-btn.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.cbe-bill-row{display:flex; align-items:center; justify-content:space-between; padding:4px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px; line-height:1.25; gap:8px;}
.cbe-bill-row:last-child{border-bottom:none;}
.cbe-bill-no{color:#263238; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-bill-supplier{color:#6b7280; font-size:7.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-bill-amt{font-weight:700; color:#0d3c72; white-space:nowrap; flex-shrink:0;}
.cbe-bill-due{color:#94A3B8; font-size:7.5px; white-space:nowrap; flex-shrink:0;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_exec.row_bills_due_soon') }} / {{ __('cbe_exec.row_bills_overdue') }}</div>
            <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $scopeLabel }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', $backQuery) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    <div style="flex-shrink:0; display:flex; gap:4px; border-bottom:1px solid #e2e8f0;">
        <div class="cbe-tab-btn active" data-tab="due-soon" onclick="cbeBillsTab('due-soon')">{{ __('cbe_exec.row_bills_due_soon') }} ({{ $dueSoon->count() }})</div>
        <div class="cbe-tab-btn" data-tab="overdue" onclick="cbeBillsTab('overdue')">{{ __('cbe_exec.row_bills_overdue') }} ({{ $overdue->count() }})</div>
    </div>

    <div id="cbe-bills-due-soon" style="flex:1; min-height:0; display:flex; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($dueSoon as $b)
            <div class="cbe-bill-row">
                <div style="min-width:0;">
                    <div class="cbe-bill-no">{{ $b->bill_no ?: '#'.substr($b->bill_id, 0, 8) }}</div>
                    <div class="cbe-bill-supplier">{{ $b->supplier_name ?? '—' }}@if($b->description) — {{ $b->description }}@endif</div>
                </div>
                <div class="cbe-bill-due">{{ $b->due_date ? \Carbon\Carbon::parse($b->due_date)->format('d M Y') : '—' }}</div>
                <div class="cbe-bill-amt">RM {{ number_format($b->amount - $b->paid_amount, 2) }}</div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>

    <div id="cbe-bills-overdue" style="display:none; flex:1; min-height:0; flex-direction:column; background:#fff; border:1px solid #e2e8f0; border-left:4px solid #c62828; border-radius:10px; box-shadow:0 2px 8px rgba(198,40,40,0.08); overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($overdue as $b)
            <div class="cbe-bill-row">
                <div style="min-width:0;">
                    <div class="cbe-bill-no">{{ $b->bill_no ?: '#'.substr($b->bill_id, 0, 8) }}</div>
                    <div class="cbe-bill-supplier">{{ $b->supplier_name ?? '—' }}@if($b->description) — {{ $b->description }}@endif</div>
                </div>
                <div class="cbe-bill-due" style="color:#c62828;">{{ $b->due_date ? \Carbon\Carbon::parse($b->due_date)->format('d M Y') : '—' }}</div>
                <div class="cbe-bill-amt" style="color:#c62828;">RM {{ number_format($b->amount - $b->paid_amount, 2) }}</div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>
    </div>
</div>

<script>
function cbeBillsTab(tab){
    document.getElementById('cbe-bills-due-soon').style.display = (tab === 'due-soon') ? 'flex' : 'none';
    document.getElementById('cbe-bills-overdue').style.display = (tab === 'overdue') ? 'flex' : 'none';
    document.querySelectorAll('.cbe-tab-btn').forEach(function(b){ b.classList.toggle('active', b.getAttribute('data-tab') === tab); });
}
</script>
@endsection
