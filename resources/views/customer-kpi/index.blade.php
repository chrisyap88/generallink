@extends('layouts.dashboard')

@section('page-title', __('customer_kpi.title'))

{{-- REDESIGNED 25 Jul 2026 — per Chris: "I DONT WANT THIS CUSTOMER KPI
     DASHBOARD, I WANT CUSTOMER DASHBOARD LIKE THIS... YOU PUT ALL THIS
     INTO THE RESPECTION BOX. BOTTOM PERFORMANCE OVERVIEW IS ALL THE
     CUSTOMER GROUP IN THE ACCORDING TO PUBLIC AND PRIHATIN2U." Rebuilt
     to literally reuse the main KPI Dashboard's own CSS classes/grid
     (.db/.month-bar/.grid/.card/.ct/.row-item/.radio-row/.chart-wrap/
     .pc/.pt/.perf-card, copied verbatim from dashboard/admin.blade.php)
     instead of a look-alike — same 3x3 box grid, same Performance
     Overview table at the bottom, now broken down by GROUP LABEL
     (Direct Selling / System Default vs each Organization Rewards Group such as
     PRIHATIN2U) instead of by KPI metric name. --}}

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;font-family:'Poppins',sans-serif;}
.page-content{padding:0;height:100%;width:100%;}

.db{position:absolute;inset:0;background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);padding:6px;display:flex;flex-direction:column;gap:5px;overflow:hidden;}
.month-bar{display:flex;align-items:center;gap:8px;flex-shrink:0;background:rgba(255,255,255,0.65);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.4);border-radius:10px;padding:4px 12px;box-shadow:0 2px 8px rgba(120,180,190,0.15);flex-wrap:wrap;}
.month-bar select,.month-bar input{border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;font-weight:600;color:#1565C0;cursor:pointer;outline:none;border-radius:5px;padding:2px 4px;}
.month-bar .as-of{font-size:7px;color:#64748B;}
.month-bar .group-chip{display:inline-flex;align-items:center;gap:4px;font-size:8px;background:#1565C0;color:#fff;border-radius:10px;padding:3px 8px;white-space:nowrap;flex-shrink:0;}
.month-bar .group-chip a{color:#fff;text-decoration:none;font-size:9px;line-height:1;}

.grid{display:grid;grid-template-columns:1fr 1fr 1fr;grid-template-rows:2fr 2.2fr 95px;gap:6px;flex:1;min-height:0;}

.card{background:rgba(255,255,255,0.65);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.4);border-radius:12px;box-shadow:0 4px 16px rgba(120,180,190,0.15);padding:5px 8px;display:flex;flex-direction:column;overflow:hidden;min-height:0;}
.ct{font-size:7px;font-weight:700;color:#000;letter-spacing:0.3px;margin-bottom:1px;flex-shrink:0;text-align:center;}

.row-item{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:7px 10px;flex:1;text-decoration:none;min-height:0;}
.row-lbl{font-size:8px;color:#000;letter-spacing:0.2px;white-space:nowrap;}
.row-val{font-size:9px;color:#000;font-weight:600;flex-shrink:0;}

.radio-row{display:flex;gap:4px;margin-bottom:2px;flex-shrink:0;align-items:center;flex-wrap:nowrap;white-space:nowrap;}
.radio-row label{font-size:7px;color:#000;cursor:pointer;display:flex;align-items:center;gap:2px;flex-shrink:0;}
.radio-row input[type=radio]{accent-color:#1565C0;width:10px;height:10px;}

.chart-wrap{position:relative;flex:1;min-height:80px;}
.chart-wrap canvas{position:absolute;inset:0;width:100%!important;height:100%!important;}

.pc{grid-column:1/4;background:rgba(255,255,255,0.65);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.4);border-radius:12px;box-shadow:0 4px 16px rgba(120,180,190,0.15);padding:3px 10px;overflow:hidden;min-height:0;display:flex;flex-direction:column;}
.pt{width:100%;border-collapse:collapse;table-layout:fixed;}
.pt th{padding:2px 6px;font-size:7px;color:#000;text-transform:uppercase;background:rgba(255,255,255,0.5);border-bottom:1px solid rgba(125,231,231,0.3);}
.pt th.num,.pt td.num{text-align:center;}
.pt th.kpi,.pt td.kpi{text-align:left;width:34%;}
.pt td{padding:3px 6px;font-size:7px;color:#000;border-bottom:1px solid rgba(125,231,231,0.1);}
.pt tr:last-child td{border:none;}

.perf-card{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:6px 7px;gap:5px;flex:1;min-height:0;}
.perf-name{font-size:8px;color:#000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.perf-amt{font-size:9px;color:#000;flex-shrink:0;white-space:nowrap;}
{{-- Box 3 (Top 3 State Performer) packs the 3 states AND the Active/Not
     Active rows into one fixed-height card, so its state rows need to
     stay compact — the bigger .perf-card size above (for Box 1's Top 3
     Customers, which has no competing section below it) was clipping
     the 3rd state + the status rows entirely in this box. --}}
.perf-card-sm{padding:3px 6px;gap:4px;}
.perf-card-sm .perf-name{font-size:7px;}
.perf-card-sm .perf-amt{font-size:7.5px;}

.rfTaList div:hover{background:#E3F2FD;}
</style>
@endpush

@section('content')
<div class="db">

    {{-- FILTER BAR — simplified 26 Jul 2026 per Chris: "the top filter
         only need customer group follow by Go, the rest no need
         Management, Operation, Affiliate, Category, Type, Occupation,
         Source, Status." The agent-hierarchy cascade (gl_id/tl_id/
         introducer_id) and attribute filters (category/type/occupation/
         source/status) are still fully supported server-side (the
         controller reads them from the querystring exactly as before,
         defaulting to "no filter" when absent) — only the pickers were
         removed from this screen. --}}
    <form method="GET" action="{{ route('customer-kpi.index') }}" class="month-bar">
        <input type="hidden" name="loaded" value="1">
        <span class="as-of">🏢</span>

        @if($agent->role === 'ADMIN')
        {{-- NEW 18 Aug 2026 — per Chris: pick Direct Selling / Organization
             Rewards / Community & Business Enterprise as a top-level
             filter first, so the Customer Group list below only shows
             groups of that type instead of everything mixed together. --}}
        <select name="group_type" onchange="this.form.submit()" style="font-weight:700;">
            <option value="">{{ __('customer_kpi.all_types_dash') }}</option>
            <option value="DSG" {{ $groupType === 'DSG' ? 'selected' : '' }}>{{ __('dashboard.admin_direct_selling_group') }}</option>
            <option value="ORG" {{ $groupType === 'ORG' ? 'selected' : '' }}>{{ __('dashboard.admin_org_rewards_group') }}</option>
            <option value="CBE" {{ $groupType === 'CBE' ? 'selected' : '' }}>{{ __('customer_kpi.community_business_enterprise') }}</option>
        </select>
        <select name="group_label_id" onchange="this.form.submit()" style="font-weight:700;">
            <option value="">{{ __('customer_kpi.all_customer_groups_dash') }}</option>
            @if(!$groupType || $groupType === 'DSG')
            <option value="SYSTEM_DEFAULT" {{ $groupLabelId === 'SYSTEM_DEFAULT' ? 'selected' : '' }}>{{ __('customer_kpi.public_system_default_option') }}</option>
            @endif
            @foreach($groupLabels as $gl)
            <option value="{{ $gl->group_label_id }}" {{ $groupLabelId === $gl->group_label_id ? 'selected' : '' }}>{{ $gl->group_name }}</option>
            @endforeach
        </select>

        {{-- Selected-scope chip — same solid blue pill + ✕-to-clear pattern
             as the main KPI Dashboard's #group-selected-badge. --}}
        @if($groupLabelName)
        <span class="group-chip">
            {{ $groupLabelName }}
            <a href="{{ route('customer-kpi.index', request()->except(['p','group_label_id'])) }}" title="{{ __('dashboard.clear_word') }}">✕</a>
        </span>
        @endif
        @endif

        <button type="submit" style="margin-left:auto;background:#1565C0;color:#fff;border:none;border-radius:6px;padding:4px 10px;font-size:8px;font-weight:700;cursor:pointer;white-space:nowrap;">{{ __('customer_kpi.go_button') }}</button>
        @if($groupLabelId || $groupType || $glId || $tlId || $introId || collect($filters)->filter()->isNotEmpty())
        <a href="{{ route('customer-kpi.index') }}" style="font-size:8px;color:#64748B;text-decoration:none;font-weight:600;">{{ __('dashboard.clear_word') }}</a>
        @endif
    </form>

    @unless($loaded)
    <div style="flex:1;display:flex;align-items:center;justify-content:center;text-align:center;color:#1565C0;font-size:11px;font-weight:600;background:rgba(255,255,255,0.5);border-radius:12px;">
        {{ __('customer_kpi.choose_filters_note') }}
    </div>
    @else
    <div class="grid">

        {{-- BOX 1: Top 3 Customers (Sales/Earnings radio, mirrors Top 3 Performers) --}}
        <div class="card" style="border-top:3px solid #7DE7E7;">
            <div class="ct" style="display:flex;justify-content:space-between;align-items:center;">
                <span>{{ __('customer_kpi.top_3_customers_heading') }}</span>
                <a href="{{ route('customer-kpi.list', array_merge(request()->except('p'), ['sort'=>'sales'])) }}" id="ck-viewall" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all_link') }}</a>
            </div>
            <div class="radio-row">
                <label><input type="radio" name="ck-metric" value="sales" checked onchange="switchCk(this.value)"> {{ __('dashboard.sales_radio') }}</label>
                <label><input type="radio" name="ck-metric" value="earning" onchange="switchCk(this.value)"> {{ __('dashboard.earnings_radio') }}</label>
            </div>
            <div id="ck-list" style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;"></div>
        </div>

        {{-- BOX 2: Customer Overview (mirrors Network Overview). Each row
             is a clickable drill-down into the ranked "View All" list,
             filtered to exactly that population — Total clears any
             status filter, Active/Prospect jump straight to that status. --}}
        <div class="card" style="border-top:3px solid #6EE7F9;">
            <div class="ct">{{ __('customer_kpi.customer_overview_heading') }}</div>
            <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
                <a class="row-item" href="{{ route('customer-kpi.list', collect(request()->except(['p','status_id','sort']))->toArray()) }}"><div class="row-lbl">{{ __('customer_kpi.total_customers_label') }}</div><div class="row-val">{{ number_format($totalCustomers) }}</div></a>
                <a class="row-item" href="{{ route('customer-kpi.list', array_merge(request()->except(['p','status_id','sort']), ['status_id' => $activeStatusId])) }}"><div class="row-lbl">{{ __('growth.status_active') }}</div><div class="row-val">{{ number_format($activeCustomers) }}</div></a>
                <a class="row-item" href="{{ route('customer-kpi.list', array_merge(request()->except(['p','status_id','sort']), ['status_id' => $prospectStatusId])) }}"><div class="row-lbl">{{ __('customer_kpi.prospect_label') }}</div><div class="row-val">{{ number_format($prospectCustomers) }}</div></a>
            </div>
        </div>

        {{-- BOX 3: By Location / By Status — per Chris: "include by
             location top 3 states contributor radio sales earning drill
             down all stage sequence by contribution rank state." Top
             half = Top 3 States by Sales/Earning (medal-ranked, same
             pattern as Box 1); each state is clickable straight into a
             ranked customer list for that state, which shows each
             customer's Owner. Bottom half = the existing By Status
             breakdown, unchanged, each row still clickable. --}}
        <div class="card" style="border-top:3px solid #EF4444;">
            <div class="ct" style="display:flex;justify-content:space-between;align-items:center;">
                <span>{{ __('customer_kpi.top_3_state_performer_heading') }}</span>
                <a id="state-viewall" href="{{ route('customer-kpi.states') }}" style="font-size:7px;color:#1565C0;font-weight:700;text-decoration:none;">{{ __('customer_kpi.all_states_link') }}</a>
            </div>
            <div class="radio-row">
                <label><input type="radio" name="loc-metric" value="sales" checked onchange="switchState(this.value)"> {{ __('dashboard.sales_radio') }}</label>
                <label><input type="radio" name="loc-metric" value="earning" onchange="switchState(this.value)"> {{ __('dashboard.earnings_radio') }}</label>
            </div>
            <div id="state-list" style="display:flex;flex-direction:column;gap:2px;flex:0 0 auto;"></div>
            <div style="border-top:1px solid rgba(125,231,231,0.3);margin:3px 0;"></div>
            {{-- By Status — per Chris: "the status active not active must
                 show." Active/Not Active are guaranteed, always-visible
                 rows (never crowded out by the finer 17-state pipeline
                 breakdown) — Not Active drills into the full per-status
                 breakdown screen, since "not active" itself spans many
                 individual statuses, not one status_id to filter on. --}}
            <div style="display:flex;flex-direction:column;gap:2px;flex:1;min-height:0;overflow:hidden;">
                <a class="row-item" href="{{ route('customer-kpi.list', array_merge(request()->except(['p','status_id','sort','state']), ['status_id' => $activeStatusId])) }}"><div class="row-lbl">{{ __('growth.status_active') }}</div><div class="row-val">{{ number_format($activeCustomers) }}</div></a>
                <a class="row-item" href="{{ route('customer-kpi.statuses', request()->except(['p','sort'])) }}"><div class="row-lbl">{{ __('customer_kpi.not_active_label') }}</div><div class="row-val">{{ number_format($totalCustomers - $activeCustomers) }}</div></a>
            </div>
        </div>

        {{-- BOX 4: By Category — click a bar/slice to drill the whole
             dashboard down to that Category. --}}
        <div class="card" style="border-top:3px solid #5EEAD4;">
            <div class="ct">{{ __('customer_kpi.by_category_heading') }}</div>
            <div class="radio-row">
                <label><input type="radio" name="cat-type" value="bar" checked onchange="renderChart('catChart',{{ Illuminate\Support\Js::from($byCategory->pluck('label')) }},{{ Illuminate\Support\Js::from($byCategory->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byCategory->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'customer_category_id')"> {{ __('dashboard.bar_option') }}</label>
                <label><input type="radio" name="cat-type" value="line" onchange="renderChart('catChart',{{ Illuminate\Support\Js::from($byCategory->pluck('label')) }},{{ Illuminate\Support\Js::from($byCategory->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byCategory->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'customer_category_id')"> {{ __('dashboard.line_option') }}</label>
                <label><input type="radio" name="cat-type" value="pie" onchange="renderChart('catChart',{{ Illuminate\Support\Js::from($byCategory->pluck('label')) }},{{ Illuminate\Support\Js::from($byCategory->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byCategory->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'customer_category_id')"> {{ __('dashboard.pie_option') }}</label>
            </div>
            <div class="chart-wrap"><canvas id="catChart"></canvas></div>
        </div>

        {{-- BOX 5: By Occupation --}}
        <div class="card" style="border-top:3px solid #A7F3F0;">
            <div class="ct">{{ __('customer_kpi.by_occupation_heading') }}</div>
            <div class="radio-row">
                <label><input type="radio" name="occ-type" value="bar" checked onchange="renderChart('occChart',{{ Illuminate\Support\Js::from($byOccupation->pluck('label')) }},{{ Illuminate\Support\Js::from($byOccupation->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byOccupation->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'occupation_group_id')"> {{ __('dashboard.bar_option') }}</label>
                <label><input type="radio" name="occ-type" value="line" onchange="renderChart('occChart',{{ Illuminate\Support\Js::from($byOccupation->pluck('label')) }},{{ Illuminate\Support\Js::from($byOccupation->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byOccupation->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'occupation_group_id')"> {{ __('dashboard.line_option') }}</label>
                <label><input type="radio" name="occ-type" value="pie" onchange="renderChart('occChart',{{ Illuminate\Support\Js::from($byOccupation->pluck('label')) }},{{ Illuminate\Support\Js::from($byOccupation->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($byOccupation->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'occupation_group_id')"> {{ __('dashboard.pie_option') }}</label>
            </div>
            <div class="chart-wrap"><canvas id="occChart"></canvas></div>
        </div>

        {{-- BOX 6: By Source --}}
        <div class="card" style="border-top:3px solid #10B981;">
            <div class="ct">{{ __('customer_kpi.by_source_heading') }}</div>
            <div class="radio-row">
                <label><input type="radio" name="src-type" value="bar" checked onchange="renderChart('srcChart',{{ Illuminate\Support\Js::from($bySource->pluck('label')) }},{{ Illuminate\Support\Js::from($bySource->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($bySource->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'source_id')"> {{ __('dashboard.bar_option') }}</label>
                <label><input type="radio" name="src-type" value="line" onchange="renderChart('srcChart',{{ Illuminate\Support\Js::from($bySource->pluck('label')) }},{{ Illuminate\Support\Js::from($bySource->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($bySource->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'source_id')"> {{ __('dashboard.line_option') }}</label>
                <label><input type="radio" name="src-type" value="pie" onchange="renderChart('srcChart',{{ Illuminate\Support\Js::from($bySource->pluck('label')) }},{{ Illuminate\Support\Js::from($bySource->pluck('total')) }},this.value,{{ Illuminate\Support\Js::from($bySource->pluck('id')->map(fn($v) => $v ?? 'NONE')) }},'source_id')"> {{ __('dashboard.pie_option') }}</label>
            </div>
            <div class="chart-wrap"><canvas id="srcChart"></canvas></div>
        </div>

        {{-- PERFORMANCE OVERVIEW — by Group Label (Public / PRIHATIN2U /
             etc), always all groups side by side regardless of any
             Customer Group filter already applied (see cascadeAgentIds
             in the controller). Each row is clickable — drills the WHOLE
             dashboard down into that one Customer Group. --}}
        <div class="pc">
            <div style="font-size:7px;color:#000;text-align:center;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">{{ __('customer_kpi.performance_overview_by_group_heading') }}</div>
            <table class="pt">
                <thead>
                    <tr><th class="kpi">{{ __('customer_kpi.col_group') }}</th><th class="num">{{ __('customer_kpi.col_customers') }}</th><th class="num">{{ __('customer_kpi.col_sales_rm') }}</th><th class="num">{{ __('customer_kpi.col_earning_rm') }}</th></tr>
                </thead>
                <tbody>
                    @forelse($groupBreakdown as $g)
                    <tr style="cursor:pointer;" onclick="window.location='{{ route('customer-kpi.index', array_merge(request()->except('group_label_id'), ['group_label_id' => $g->link_group_label_id, 'loaded' => 1])) }}'">
                        <td class="kpi">{{ $g->group_name }}{{ $groupLabelId === $g->link_group_label_id ? ' ✓' : '' }}</td>
                        <td class="num">{{ number_format($g->customers) }}</td>
                        <td class="num">{{ number_format($g->sales, 2) }}</td>
                        <td class="num">{{ number_format($g->earning, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="kpi" style="text-align:center;">{{ __('customer_kpi.no_data_in_scope_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>
    @endunless
</div>

@if($loaded)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var ckTopSales = {{ Illuminate\Support\Js::from($topSales->map(fn($r,$i)=>['id'=>$r->customer_id,'name'=>$r->full_name,'total'=>(float)$r->total,'rank'=>$i+1])) }};
var ckTopEarning = {{ Illuminate\Support\Js::from($topEarning->map(fn($r,$i)=>['id'=>$r->customer_id,'name'=>$r->full_name,'total'=>(float)$r->total,'rank'=>$i+1])) }};
var medals = ['🥇','🥈','🥉'];
var fRM = function(v){ return 'RM '+parseFloat(v||0).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2}); };
// Same __ID__-placeholder convention used by resources/views/customers/
// index.blade.php — safer than hand-building the URL, since it goes
// through the real route() helper for whichever role prefix applies.
var CK_PROFILE_TEMPLATE = '{{ route($rolePrefix.".customers.show", ["id" => "__ID__"]) }}';

// Name -> full Customer Profile — per Chris: "when i point to the
// customer name you allow me drill down to see the entire customer
// profile." (Profile/Sales History/Reminders/Activity Log/KPI
// Dashboard tabs — not just this screen's own transaction list.)
function switchCk(metric){
    var rows = metric === 'earning' ? ckTopEarning : ckTopSales;
    var list = document.getElementById('ck-list');
    var viewAllUrl = '{{ route("customer-kpi.list") }}?' + new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(window.location.search)), {sort: metric})).toString();
    document.getElementById('ck-viewall').href = viewAllUrl;
    if (!rows.length) { list.innerHTML = '<div style="font-size:7px;color:#94a3b8;text-align:center;padding:8px;">{{ __('customer_kpi.no_data_yet_js') }}</div>'; return; }
    list.innerHTML = rows.map(function(r){
        var profileUrl = CK_PROFILE_TEMPLATE.replace('__ID__', r.id);
        return '<a href="'+profileUrl+'" class="perf-card" style="text-decoration:none;"><div class="perf-name">'+medals[r.rank-1]+' '+r.name+'</div><div class="perf-amt">'+fRM(r.total)+'</div></a>';
    }).join('');
}
switchCk('sales');

// By Location — Top 3 States, same medal-ranked pattern as Box 1.
// Clicking a state opens the ranked customer list for that ONE state
// (sales or earning, whichever radio is selected), which itself shows
// each customer's Owner — per Chris: "drill down who is the customer
// and ownership (created by)."
var ckTopStatesSales = {{ Illuminate\Support\Js::from($topStatesSales->map(fn($r,$i)=>['state'=>$r->state,'total'=>(float)$r->total,'rank'=>$i+1])) }};
var ckTopStatesEarning = {{ Illuminate\Support\Js::from($topStatesEarning->map(fn($r,$i)=>['state'=>$r->state,'total'=>(float)$r->total,'rank'=>$i+1])) }};

function switchState(metric){
    var rows = metric === 'earning' ? ckTopStatesEarning : ckTopStatesSales;
    var list = document.getElementById('state-list');
    var viewAllUrl = '{{ route("customer-kpi.states") }}?' + new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(window.location.search)), {sort: metric})).toString();
    document.getElementById('state-viewall').href = viewAllUrl;
    if (!rows.length) { list.innerHTML = '<div style="font-size:7px;color:#94a3b8;text-align:center;padding:4px;">{{ __('customer_kpi.no_location_data_yet_js') }}</div>'; return; }
    list.innerHTML = rows.map(function(r){
        var stateParam = r.state === 'Unspecified' ? 'UNSPECIFIED' : r.state;
        var url = '{{ route("customer-kpi.list") }}?' + new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(window.location.search)), {sort: metric, state: stateParam})).toString();
        return '<a href="'+url+'" class="perf-card perf-card-sm" style="text-decoration:none;"><div class="perf-name">'+medals[r.rank-1]+' '+r.state+'</div><div class="perf-amt">'+fRM(r.total)+'</div></a>';
    }).join('');
}
switchState('sales');

var ckCharts = {};
// Clicking a bar/slice/point drills the WHOLE dashboard down to that
// one bucket — resubmits the current filter set with filterKey=id set,
// same "drill down must show meaningful detail" ask as everywhere else
// in this screen. ids/filterKey are optional (omitted = not clickable).
function ckDrillTo(filterKey, value){
    var params = new URLSearchParams(window.location.search);
    params.set('loaded', '1');
    if (value === null || value === undefined || value === '') { params.delete(filterKey); } else { params.set(filterKey, value); }
    window.location = '{{ route("customer-kpi.index") }}?' + params.toString();
}
function renderChart(canvasId, labels, data, type, ids, filterKey){
    if (ckCharts[canvasId]) { ckCharts[canvasId].destroy(); }
    var ctx = document.getElementById(canvasId).getContext('2d');
    var colors = ['#1565C0','#00897B','#F9A825','#8E24AA','#EF4444','#10B981','#6366F1','#EC4899'];
    var onClickFn = (ids && filterKey) ? function(evt, elements){
        if (!elements.length) { return; }
        var idx = elements[0].index;
        ckDrillTo(filterKey, ids[idx]);
    } : undefined;
    // Chris caught the Chart.js tooltip showing "undefined" — that's
    // Chart.js's default tooltip body template ("{dataset.label}:
    // {value}"), and every dataset below was missing a `label`, so it
    // rendered literally undefined. Fixed by naming every dataset
    // 'Customers' and pointing each tooltip's label callback there.
    var cfg;
    if (type === 'pie') {
        cfg = { type:'pie', data:{ labels:labels, datasets:[{ label:'Customers', data:data, backgroundColor:colors, borderWidth:1, borderColor:'#fff' }] },
            options:{ responsive:true, maintainAspectRatio:false, onClick:onClickFn, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: function(item){ return item.label + ': ' + item.parsed; } } } } } };
    } else if (type === 'line') {
        cfg = { type:'line', data:{ labels:labels, datasets:[{ label:'Customers', data:data, borderColor:'#1565C0', backgroundColor:'rgba(21,101,192,0.15)', fill:true, tension:0.3, pointRadius:3 }] },
            options:{ responsive:true, maintainAspectRatio:false, onClick:onClickFn, scales:{ y:{ beginAtZero:true, ticks:{ font:{ size:6 } } }, x:{ ticks:{ display:false }, grid:{ display:false } } }, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: function(item){ return 'Customers: ' + item.parsed.y; } } } } } };
    } else {
        cfg = { type:'bar', data:{ labels:labels, datasets:[{ label:'Customers', data:data, backgroundColor:'#1565C0', borderRadius:3 }] },
            options:{ responsive:true, maintainAspectRatio:false, onClick:onClickFn, scales:{ y:{ beginAtZero:true, ticks:{ font:{ size:6 } } }, x:{ ticks:{ display:false }, grid:{ display:false } } }, plugins:{ legend:{ display:false }, tooltip:{ callbacks:{ label: function(item){ return 'Customers: ' + item.parsed.y; } } } } } };
    }
    if (onClickFn) { ctx.canvas.style.cursor = 'pointer'; }
    ckCharts[canvasId] = new Chart(ctx, cfg);
}

renderChart('catChart', {{ Illuminate\Support\Js::from($byCategory->pluck('label')) }}, {{ Illuminate\Support\Js::from($byCategory->pluck('total')) }}, 'bar', {{ Illuminate\Support\Js::from($byCategory->pluck('id')->map(fn($v) => $v ?? 'NONE')) }}, 'customer_category_id');
renderChart('occChart', {{ Illuminate\Support\Js::from($byOccupation->pluck('label')) }}, {{ Illuminate\Support\Js::from($byOccupation->pluck('total')) }}, 'bar', {{ Illuminate\Support\Js::from($byOccupation->pluck('id')->map(fn($v) => $v ?? 'NONE')) }}, 'occupation_group_id');
renderChart('srcChart', {{ Illuminate\Support\Js::from($bySource->pluck('label')) }}, {{ Illuminate\Support\Js::from($bySource->pluck('total')) }}, 'bar', {{ Illuminate\Support\Js::from($bySource->pluck('id')->map(fn($v) => $v ?? 'NONE')) }}, 'source_id');
</script>
@endif

{{-- Typeahead must always run — the Group/Team Leader/Introducer search
     boxes are part of the always-visible filter bar, not the gated
     grid below it, so they need to work even before Apply is clicked. --}}
<script>
// Typeahead — identical pattern to Yearly Renewal Forecast.
(function() {
    var TYPEAHEAD_URL = '{{ route('renewal-forecast.agent-typeahead') }}';
    function initTypeahead(boxId, hiddenId, listId, mode, parentHiddenId, clearHiddenIds) {
        var box = document.getElementById(boxId);
        if (!box) { return; }
        var hidden = document.getElementById(hiddenId);
        var list = document.getElementById(listId);
        var timer = null;
        box.addEventListener('input', function() {
            hidden.value = '';
            clearTimeout(timer);
            var q = box.value.trim();
            if (q.length < 1) { list.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var parentVal = parentHiddenId ? (document.getElementById(parentHiddenId).value || '') : '';
                var url = TYPEAHEAD_URL + '?mode=' + encodeURIComponent(mode) + '&q=' + encodeURIComponent(q) + '&parent_id=' + encodeURIComponent(parentVal);
                fetch(url).then(function(r) { return r.json(); }).then(function(data) {
                    list.innerHTML = '';
                    if (!data.length) { list.style.display = 'none'; return; }
                    var rect = box.getBoundingClientRect();
                    list.style.left = rect.left + 'px';
                    list.style.top = rect.bottom + 'px';
                    list.style.width = Math.max(rect.width, 200) + 'px';
                    data.forEach(function(item) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:5px 8px; font-size:9px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.full_name + ' (' + item.agent_code + ')';
                        row.addEventListener('mousedown', function() {
                            box.value = item.full_name;
                            hidden.value = item.agent_id;
                            list.style.display = 'none';
                            (clearHiddenIds || []).forEach(function(id) {
                                var el = document.getElementById(id);
                                if (el) { el.value = ''; }
                            });
                        });
                        list.appendChild(row);
                    });
                    list.style.display = 'block';
                });
            }, 200);
        });
        document.addEventListener('click', function(e) {
            if (e.target !== box) { list.style.display = 'none'; }
        });
    }
    initTypeahead('ckGlBox', 'ckGlId', 'ckGlList', 'gl', null, ['ckTlId','ckIntroId']);
    initTypeahead('ckTlBox', 'ckTlId', 'ckTlList', 'tl', 'ckGlId', ['ckIntroId']);
    initTypeahead('ckIntroBox', 'ckIntroId', 'ckIntroList', 'introducer', 'ckTlId', []);
})();
</script>
@endsection
