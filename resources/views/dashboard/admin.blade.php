@extends('layouts.dashboard')
@section('page-title', __('dashboard.kpi_dashboard_title'))

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;font-family:'Poppins',sans-serif;}
.page-content{padding:0;height:100%;width:100%;}

.db{
  position:absolute;inset:0;
  background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);
  padding:6px;
  display:flex;flex-direction:column;
  gap:5px;
  overflow:hidden;
}
.month-bar{display:flex;align-items:center;gap:8px;flex-shrink:0;background:rgba(255,255,255,0.65);backdrop-filter:blur(18px);border:1px solid rgba(255,255,255,0.4);border-radius:10px;padding:4px 12px;box-shadow:0 2px 8px rgba(120,180,190,0.15);}
.month-bar button{background:none;border:none;cursor:pointer;font-size:12px;color:#1565C0;padding:0 4px;line-height:1;}
.month-bar .period-label{font-size:9px;font-weight:700;color:#1565C0;min-width:110px;text-align:center;}
.month-bar select{border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;font-weight:600;color:#1565C0;cursor:pointer;outline:none;border-radius:5px;padding:2px 4px;}
.month-bar .as-of{font-size:7px;color:#64748B;}

.grid{
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  grid-template-rows:2fr 2.2fr 95px;
  gap:6px;
  flex:1;
  min-height:0;
}

.card{
  background:rgba(255,255,255,0.65);
  backdrop-filter:blur(18px);
  border:1px solid rgba(255,255,255,0.4);
  border-radius:12px;
  box-shadow:0 4px 16px rgba(120,180,190,0.15);
  padding:5px 8px;
  display:flex;flex-direction:column;
  overflow:hidden;min-height:0;
}

.ct{font-size:7px;font-weight:700;color:#000;letter-spacing:0.3px;margin-bottom:1px;flex-shrink:0;text-align:center;}

.row-item{
  background:rgba(255,255,255,0.55);
  border:1px solid rgba(125,231,231,0.25);
  border-radius:7px;
  display:flex;align-items:center;justify-content:space-between;
  padding:4px 10px;flex:1;text-decoration:none;min-height:0;
}
.row-lbl{font-size:7px;color:#000;letter-spacing:0.2px;white-space:nowrap;}
.row-val{font-size:8px;color:#000;font-weight:600;flex-shrink:0;}

.radio-row{display:flex;gap:4px;margin-bottom:2px;flex-shrink:0;align-items:center;flex-wrap:nowrap;white-space:nowrap;}
.radio-row label{font-size:7px;color:#000;cursor:pointer;display:flex;align-items:center;gap:2px;flex-shrink:0;}
.radio-row input[type=radio]{accent-color:#1565C0;width:10px;height:10px;}

.chart-wrap{position:relative;flex:1;min-height:80px;}
.chart-wrap canvas{position:absolute;inset:0;width:100%!important;height:100%!important;display:none;}

.pc{
  grid-column:1/4;
  background:rgba(255,255,255,0.65);
  backdrop-filter:blur(18px);
  border:1px solid rgba(255,255,255,0.4);
  border-radius:12px;
  box-shadow:0 4px 16px rgba(120,180,190,0.15);
  padding:3px 10px;
  overflow:hidden;min-height:0;
  display:flex;flex-direction:column;
}
.pt{width:100%;border-collapse:collapse;table-layout:fixed;}
.pt th{padding:2px 6px;font-size:7px;color:#000;text-transform:uppercase;background:rgba(255,255,255,0.5);border-bottom:1px solid rgba(125,231,231,0.3);}
.pt th.num,.pt td.num{text-align:center;}
.pt th.kpi,.pt td.kpi{text-align:left;width:25%;}
.pt td{padding:3px 6px;font-size:7px;color:#000;border-bottom:1px solid rgba(125,231,231,0.1);}
.pt tr:last-child td{border:none;}

.sk{background:linear-gradient(90deg,rgba(125,231,231,0.2) 25%,rgba(125,231,231,0.4) 50%,rgba(125,231,231,0.2) 75%);background-size:200% 100%;animation:sh 1.5s infinite;border-radius:6px;}
@keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}

.perf-card{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:3px 7px;gap:5px;flex:1;min-height:0;}
.perf-name{font-size:7px;color:#000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.perf-sub{font-size:7px;color:#555;}
.perf-amt{font-size:8px;color:#000;flex-shrink:0;white-space:nowrap;}

.group-suggestion:hover{background:#E3F2FD;}
</style>
@endpush

@section('content')
<div class="db">

{{-- Combined Viewing (month/year) + Scope bar — one row, not two, to save
     vertical space for the actual dashboard cards below. --}}
<div class="month-bar">
  <span class="as-of">🏢</span>
  <select id="category-select" onchange="onCategoryChange()">
    <option value="" selected>{{ __('dashboard.admin_select_scope') }}</option>
    <option value="all">{{ __('dashboard.admin_all_company_wide') }}</option>
    <option value="public">{{ __('dashboard.admin_direct_selling_group') }}</option>
    <option value="special">{{ __('dashboard.admin_org_rewards_group') }}</option>
    <option value="cbe">{{ __('dashboard.admin_cbe_group') }}</option>
  </select>
  <div style="position:relative;display:none;flex:1;max-width:520px;" id="group-search-wrap">
    <input type="text" id="group-search" placeholder="{{ str_replace(':label', \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), __('dashboard.admin_group_search_placeholder')) }}" autocomplete="off"
      style="border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;color:#1565C0;outline:none;border-radius:5px;padding:3px 6px;width:100%;box-sizing:border-box;"
      oninput="onGroupSearchInput()">
    <div id="group-suggestions" style="display:none;position:absolute;top:100%;left:0;margin-top:2px;background:#fff;border:1px solid #b2ebf2;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;z-index:50;width:100%;min-width:420px;"></div>
  </div>
  {{-- CHANGED 23 Aug 2026 — per Chris: "all select filter must be in one
       row, display Go at the end, and you should have typeahead
       features". CBE has no Group Leader/downline to search for by name
       — it's a real tree (HQ -> State -> Temple, or however many levels
       a community defines) — so this stays a drill-down chain, but each
       step is now a type-to-search box (not a giant <select> — Selangor
       alone has 281 Temples) and every step sits in one single row that
       never wraps. Nothing loads on every pick anymore — Admin drills as
       deep as they want, then clicks Go once. --}}
  <div style="display:none;align-items:center;gap:5px;flex-wrap:nowrap;" id="cbe-drill-wrap">
    <div id="cbe-level-selects" style="display:flex;align-items:center;gap:5px;flex-wrap:nowrap;"></div>
    <button id="cbe-go-btn" onclick="onCbeGo()" style="display:none;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:8px;font-weight:700;cursor:pointer;flex-shrink:0;white-space:nowrap;">
      ▶ {{ __('dashboard.admin_go_button') }}
    </button>
  </div>
  <span id="group-selected-badge" style="display:none;align-items:center;gap:4px;font-size:8px;background:#1565C0;color:#fff;border-radius:10px;padding:3px 8px;white-space:nowrap;flex-shrink:0;">
    <span id="group-selected-label" style="white-space:nowrap;"></span>
    <button onclick="clearGroupSelection()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:9px;padding:0;line-height:1;flex-shrink:0;" title="{{ __('dashboard.clear_word') }}">✕</button>
  </span>
  <button id="confirm-all-btn" onclick="confirmLoadAll()" style="display:none;background:#D97706;color:#fff;border:none;border-radius:5px;padding:3px 10px;font-size:8px;font-weight:700;cursor:pointer;flex-shrink:0;">
    ⚠ {{ __('dashboard.admin_load_company_wide') }}
  </button>

  {{-- Month/year controls: completely hidden (not just disabled) until
       a scope is actually confirmed — no point showing them, or the
       redundant calendar icon / period text, before there is any data
       to browse by month. --}}
  <span id="month-controls-wrap" style="display:none;align-items:center;gap:8px;flex-shrink:0;">
    <span style="font-size:7px;color:#64748B;">|</span>
    <span id="period-label" style="display:none;"></span>
    <button id="prevMonthBtn" onclick="changeMonth(-1)" title="{{ __('dashboard.prev_month') }}">&#8592;</button>
    <select id="month-select" onchange="onMonthSelect()">
      <option value="1">{{ __('dashboard.jan') }}</option><option value="2">{{ __('dashboard.feb') }}</option>
      <option value="3">{{ __('dashboard.mar') }}</option><option value="4">{{ __('dashboard.apr') }}</option>
      <option value="5">{{ __('dashboard.may') }}</option><option value="6">{{ __('dashboard.jun') }}</option>
      <option value="7">{{ __('dashboard.jul') }}</option><option value="8">{{ __('dashboard.aug') }}</option>
      <option value="9">{{ __('dashboard.sep') }}</option><option value="10">{{ __('dashboard.oct') }}</option>
      <option value="11">{{ __('dashboard.nov') }}</option><option value="12">{{ __('dashboard.dec') }}</option>
    </select>
    <select id="year-select" onchange="onMonthSelect()">
      @for($y = 2024; $y <= date('Y'); $y++)
        <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
      @endfor
    </select>
    <button id="nextMonthBtn" onclick="changeMonth(1)" title="{{ __('dashboard.next_month') }}">&#8594;</button>
    <span class="as-of" id="mtd-note"></span>
  </span>

  {{-- NEW 22 Jul 2026 — per Chris: Admin is management, and needs quick
       access to the full drill-down Sales and Earning Income Forecast
       (Group -> Team Leader -> Introducer, all groups) straight from
       the home dashboard, not buried only in the sidebar. Always
       visible regardless of scope-selection state above. --}}
  <a href="{{ route('renewal-forecast.index') }}" style="margin-left:auto; background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:4px 10px; font-size:8px; font-weight:700; display:flex; align-items:center; gap:4px; white-space:nowrap;">📈 {{ __('dashboard.admin_sales_earning_forecast') }}</a>
</div>

<div id="scope-prompt" style="flex:1;display:flex;align-items:center;justify-content:center;text-align:center;color:#1565C0;font-size:11px;font-weight:600;background:rgba(255,255,255,0.5);border-radius:12px;">
  🏢 {{ __('dashboard.admin_scope_prompt') }}
</div>

<div class="grid" id="dashboard-grid" style="display:none;">

  {{-- BOX 1: Top Performers --}}
  <div class="card" style="border-top:3px solid #7DE7E7;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>🏆 {{ __('dashboard.top3_performers') }}</span><a id="perf-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all') }} →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="perf-type" value="sales" checked onchange="switchPerf()"> {{ __('dashboard.sales') }}</label>
      <label><input type="radio" name="perf-type" value="earnings" onchange="switchPerf()"> {{ __('dashboard.earnings') }}</label>
      <span style="color:#ccc;font-size:7px;">|</span>
      {{-- FIXED 1 Aug 2026 — per Chris: these must reflect whichever group
           is picked in the top filter, not the generic system default.
           That selection happens client-side (no page reload), so the
           text is rendered here as a fallback for first paint, then kept
           in sync by loadMetrics() in JS via the ids below whenever the
           group filter changes. --}}
      <label><input type="radio" name="perf-role" value="gl" checked onchange="switchPerf()"> <span id="perf-lbl-gl">{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER') }}</span></label>
      <label><input type="radio" name="perf-role" value="tl" onchange="switchPerf()"> <span id="perf-lbl-tl">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }}</span></label>
      <label><input type="radio" name="perf-role" value="intro" onchange="switchPerf()"> <span id="perf-lbl-intro">{{ \App\Services\RoleLabelService::shortLabel('INTRODUCER') }}</span></label>
    </div>
    <div id="perf-list" style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
    </div>
  </div>

  {{-- BOX 2: Network Overview --}}
  <div class="card" style="border-top:3px solid #6EE7F9;">
    <div class="ct">{{ __('dashboard.admin_network_overview') }}</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="{{ route('admin.network') }}" class="row-item">
        <div class="row-lbl">{{ \App\Services\RoleLabelService::plural('GROUP_LEADER') }}</div>
        <div class="row-val" id="b2-gl"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.network.all-tls') }}" class="row-item">
        <div class="row-lbl">{{ \App\Services\RoleLabelService::plural('TEAM_LEADER') }}</div>
        <div class="row-val" id="b2-tl"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.network.all-intros') }}" class="row-item">
        <div class="row-lbl">{{ \App\Services\RoleLabelService::plural('INTRODUCER') }}</div>
        <div class="row-val" id="b2-intro"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.agents.pending') }}" class="row-item">
        <div class="row-lbl">{{ __('dashboard.admin_pending_assignment') }}</div>
        <div class="row-val" id="b2-pend"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <div class="row-item">
        <div class="row-lbl">{{ __('dashboard.admin_total_agents') }}</div>
        <div class="row-val" id="b2-total"><div class="sk" style="width:24px;height:12px;"></div></div>
      </div>
    </div>
  </div>

  {{-- BOX 6: Pending Actions --}}
  <div class="card" style="border-top:3px solid #EF4444;">
    <div class="ct">⚠️ {{ __('dashboard.admin_pending_actions') }}</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="#" id="b6-unclaim-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.admin_unclaimed_earning_rm') }}</div><div class="row-val" id="b6-unclaim"><div class="sk" style="width:40px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.admin_renewal_lte30') }}</div><div class="row-val" id="b6-r30"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30p-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.admin_renewal_gt30') }}</div><div class="row-val" id="b6-r30p"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-redeem-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.admin_redemption') }}</div><div class="row-val" id="b6-redeem"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-pts-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.admin_points_purchase_approval') }}</div><div class="row-val" id="b6-pts"><div class="sk" style="width:24px;height:12px;"></div></div></a>
    </div>
  </div>

  {{-- BOX 4: Sales by Vendor --}}
  <div class="card" style="border-top:3px solid #5EEAD4;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_vendors') }}</span><a id="vendor-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all') }} →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="vendor" value="bar" checked onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> {{ __('dashboard.bar') }}</label>
      <label><input type="radio" name="vendor" value="line" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> {{ __('dashboard.line') }}</label>
      <label><input type="radio" name="vendor" value="pie" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> {{ __('dashboard.pie') }}</label>
      <span id="vendor-legend" style="margin-left:4px;display:flex;align-items:center;gap:4px;font-size:6px;color:#555;">
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(21,101,192,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.sales') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(27,94,32,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.earning') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(217,119,6,0.8);display:inline-block;border-radius:1px;"></span>%</span>
      </span>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="vendor-sk" style="position:absolute;inset:0;"></div>
      <canvas id="vendorChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_graph_vendor') }}</div>
  </div>

  {{-- BOX 5: Sales by Product --}}
  <div class="card" style="border-top:3px solid #A7F3F0;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_products') }}</span><a id="product-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all') }} →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="product" value="bar" checked onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> {{ __('dashboard.bar') }}</label>
      <label><input type="radio" name="product" value="line" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> {{ __('dashboard.line') }}</label>
      <label><input type="radio" name="product" value="pie" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> {{ __('dashboard.pie') }}</label>
      <span id="product-legend" style="margin-left:4px;display:flex;align-items:center;gap:4px;font-size:6px;color:#555;">
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(21,101,192,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.sales') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(27,94,32,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.earning') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(217,119,6,0.8);display:inline-block;border-radius:1px;"></span>%</span>
      </span>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="product-sk" style="position:absolute;inset:0;"></div>
      <canvas id="productChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_graph_product') }}</div>
  </div>

  {{-- BOX 3: Sales & Earnings Trend --}}
  <div class="card" style="border-top:3px solid #10B981;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_months') }}</span><a id="trend-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all') }} →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="trend" value="line" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> {{ __('dashboard.line') }}</label>
      <label><input type="radio" name="trend" value="bar" checked onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> {{ __('dashboard.bar') }}</label>
      <label><input type="radio" name="trend" value="pie" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> {{ __('dashboard.pie') }}</label>
      <span id="trend-legend" style="margin-left:4px;display:flex;align-items:center;gap:4px;font-size:6px;color:#555;">
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(21,101,192,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.sales') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(27,94,32,0.8);display:inline-block;border-radius:1px;"></span>{{ __('dashboard.earning') }}</span>
        <span style="display:flex;align-items:center;gap:1px;"><span style="width:8px;height:8px;background:rgba(217,119,6,0.8);display:inline-block;border-radius:1px;"></span>%</span>
      </span>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="trend-sk" style="position:absolute;inset:0;"></div>
      <canvas id="trendChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_graph_trend') }}</div>
  </div>

  {{-- PERFORMANCE TABLE --}}
  <div class="pc">
    <div style="font-size:7px;color:#000;text-align:center;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">📊 {{ __('dashboard.performance_overview') }}</div>
    <table class="pt">
      <thead>
        <tr>
          <th class="kpi" style="width:25%;">{{ __('dashboard.kpi') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.last_mtd') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.mtd') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.ytd') }}</th>
        </tr>
      </thead>
      <tbody id="perf-tbody">
        <tr><td colspan="4"><div class="sk" style="height:12px;margin:2px 0;"></div></td></tr>
        <tr><td colspan="4"><div class="sk" style="height:12px;margin:2px 0;"></div></td></tr>
        <tr><td colspan="4"><div class="sk" style="height:12px;margin:2px 0;"></div></td></tr>
      </tbody>
    </table>
  </div>

</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
var metricsUrl='{{ route("admin.dashboard.metrics") }}';
var groupTypeaheadUrl='{{ route("admin.dashboard.group-typeahead") }}';
var cbeChildrenUrl='{{ route("admin.dashboard.cbe-children") }}';
// Base app URL (e.g. http://localhost/generallink/public) — needed because
// several links below are built as plain strings rather than through
// Laravel's route() helper, and a bare leading "/" would resolve to the
// domain root instead of this app's actual install path, causing 404s.
var baseUrl='{{ rtrim(url("/"), "/") }}';
// Laravel's session ID changes on every fresh login (logout invalidates the
// old session and issues a new one) — used below to make sure a saved
// scope selection can NEVER leak into a different login session, whether
// that's a different Admin user or the same Admin logging back in. This is
// the real fix, independent of which button/path was used to log out.
var currentSessionId='{{ session()->getId() }}';
// NEW 18 Aug 2026 — text injected here (before the raw-JS block below,
// so Blade still processes it) for the strings this script builds via
// JS string concatenation/innerHTML — those can't be wrapped in __()
// directly since they're not rendered by Blade.
var i18n = {
  noData: @json(__('dashboard.no_data')),
  noMatches: @json(__('dashboard.admin_no_matches')),
  cbeAllOption: @json(__('dashboard.admin_cbe_all_level')),
  cbeSelectCommunity: @json(__('dashboard.admin_cbe_select_community')),
  cbeSearchLevel: @json(__('dashboard.admin_cbe_search_level_placeholder')),
  promoted: @json(__('dashboard.promoted')),
  salesAmount: @json(__('dashboard.sales_amount')),
  earningIncome: @json(__('dashboard.earning_income')),
  unclaimedEarning: @json(__('dashboard.unclaimed_earning')),
  currentMonth: @json(__('dashboard.current_month_note')),
  m1: @json(__('dashboard.jan')), m2: @json(__('dashboard.feb')), m3: @json(__('dashboard.mar')), m4: @json(__('dashboard.apr')),
  m5: @json(__('dashboard.may')), m6: @json(__('dashboard.jun')), m7: @json(__('dashboard.jul')), m8: @json(__('dashboard.aug')),
  m9: @json(__('dashboard.sep')), m10: @json(__('dashboard.oct')), m11: @json(__('dashboard.nov')), m12: @json(__('dashboard.dec'))
};
@verbatim
var fRM=function(v){return 'RM '+parseFloat(v||0).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});};
var fN=function(v){return Number(v||0).toLocaleString();};
var medals=['🥇','🥈','🥉'];

var selMonth = new Date().getMonth()+1;
var selYear  = new Date().getFullYear();
var monthNames = ['',i18n.m1,i18n.m2,i18n.m3,i18n.m4,i18n.m5,i18n.m6,i18n.m7,i18n.m8,i18n.m9,i18n.m10,i18n.m11,i18n.m12];

// Scope filter state. Nothing loads until Admin explicitly picks a scope —
// at real-world scale (millions of GL/TL/Introducers), auto-loading
// "All" on every page open would hammer the database. 'all' must be
// deliberately confirmed via the warning button, never automatic.
var selCategory = '';
var selGroupId = '';
var selGroupLabel = '';
var selCbeCity = ''; // set only when a CBE "Branch" (a city grouping) is picked; see cbeSearchChildren()/onCbePick().
var groupSearchTimer = null;
var scopeConfirmed = false;

function scopeParams(){
  return '&category='+encodeURIComponent(selCategory)+'&group_id='+encodeURIComponent(selGroupId)+'&cbe_city='+encodeURIComponent(selCbeCity);
}

// Remember the last confirmed scope + month so that navigating away (e.g.
// clicking "View All" on a box, then going back) restores the same view
// instead of forcing Admin to re-pick the scope from scratch every time.
var SCOPE_STORAGE_KEY = 'gl_admin_dashboard_scope_v1';

function saveScopeState(){
  try{
    localStorage.setItem(SCOPE_STORAGE_KEY, JSON.stringify({
      category: selCategory, groupId: selGroupId, groupLabel: selGroupLabel, cbeCity: selCbeCity,
      month: selMonth, year: selYear, sessionId: currentSessionId
    }));
  }catch(e){}
}

function loadScopeState(){
  try{
    var raw = localStorage.getItem(SCOPE_STORAGE_KEY);
    return raw ? JSON.parse(raw) : null;
  }catch(e){ return null; }
}

function clearScopeState(){
  try{ localStorage.removeItem(SCOPE_STORAGE_KEY); }catch(e){}
}

function setMonthControlsEnabled(enabled){
  var wrap = document.getElementById('month-controls-wrap');
  if(wrap) wrap.style.display = enabled ? 'flex' : 'none';
}

function showScopePrompt(){
  scopeConfirmed = false;
  document.getElementById('scope-prompt').style.display = 'flex';
  document.getElementById('dashboard-grid').style.display = 'none';
  setMonthControlsEnabled(false);
}

function showDashboardGrid(){
  document.getElementById('scope-prompt').style.display = 'none';
  document.getElementById('dashboard-grid').style.display = 'grid';
  setMonthControlsEnabled(true);
}

function onCategoryChange(){
  selCategory = document.getElementById('category-select').value;
  clearGroupSelection(false);
  document.getElementById('confirm-all-btn').style.display = 'none';
  document.getElementById('cbe-drill-wrap').style.display = 'none';
  clearScopeState();
  showScopePrompt();

  if(selCategory === ''){
    document.getElementById('group-search-wrap').style.display = 'none';
  } else if(selCategory === 'all'){
    document.getElementById('group-search-wrap').style.display = 'none';
    document.getElementById('confirm-all-btn').style.display = 'inline-block';
  } else if(selCategory === 'cbe'){
    document.getElementById('group-search-wrap').style.display = 'none';
    document.getElementById('cbe-drill-wrap').style.display = 'flex';
    startCbeDrill();
  } else {
    document.getElementById('group-search-wrap').style.display = 'block';
  }
}

// CHANGED 23 Aug 2026 — per Chris: "all select filter must be in one
// row, display Go at the end, and you should have typeahead features".
// CBE drill-down is still Community -> State -> (Branch, if that
// State's Temples span more than one city) -> Temple/Affiliate, but
// every step is now a type-to-search box appended inline into the same
// row, and NOTHING calls loadMetrics() as you pick — cbeScope just
// tracks the deepest thing picked so far, and only the Go button
// actually applies it (see onCbeGo()). This also removes the "confirm
// each level and it auto-reloads" pattern that fired one query per
// click while drilling.
var cbeScope = {nodeId:'', city:'', label:''};

function startCbeDrill(){
  cbeScope = {nodeId:'', city:'', label:''};
  document.getElementById('cbe-level-selects').innerHTML = '';
  document.getElementById('cbe-go-btn').style.display = 'none';
  addCbeTypeaheadLevel(i18n.cbeSelectCommunity, cbeSearchCommunity, function(item){
    onCbePick(item, null);
  });
}

// Generic "type to search, click a suggestion" box — used for every CBE
// drill level (Community, State, Branch, Affiliate). `searchFn(q, cb)`
// fetches matches for whatever's typed; `onPick(item)` fires when one is
// clicked. Kept narrow (fixed width) on purpose so several of these plus
// the Go button still fit on one line without wrapping.
function addCbeTypeaheadLevel(placeholder, searchFn, onPick){
  var wrap = document.createElement('div');
  wrap.style.cssText = 'position:relative;flex-shrink:0;';
  var input = document.createElement('input');
  input.type = 'text';
  input.placeholder = placeholder;
  input.autocomplete = 'off';
  input.className = 'cbe-level-input';
  input.style.cssText = "border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:8px;font-weight:600;color:#1565C0;outline:none;border-radius:5px;padding:3px 6px;width:118px;box-sizing:border-box;";
  var box = document.createElement('div');
  box.style.cssText = 'display:none;position:absolute;top:100%;left:0;margin-top:2px;background:#fff;border:1px solid #b2ebf2;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);max-height:220px;overflow-y:auto;z-index:60;min-width:190px;';
  var timer = null;

  input.addEventListener('input', function(){
    var q = input.value.trim();
    if(timer) clearTimeout(timer);
    if(q.length < 1){ box.style.display='none'; return; }
    timer = setTimeout(function(){
      searchFn(q, function(items){
        if(!items.length){
          box.innerHTML = '<div style="padding:6px 8px;font-size:8px;color:#94A3B8;">'+i18n.noMatches+'</div>';
          box.style.display = 'block';
          return;
        }
        box.innerHTML = items.map(function(it, idx){
          return '<div class="cbe-suggestion" data-idx="'+idx+'" style="padding:6px 8px;font-size:8px;color:#1565C0;cursor:pointer;border-bottom:1px solid #eee;white-space:nowrap;">'+it.label+'</div>';
        }).join('');
        box.style.display = 'block';
        Array.prototype.forEach.call(box.querySelectorAll('.cbe-suggestion'), function(el){
          el.addEventListener('click', function(){
            var it = items[parseInt(el.getAttribute('data-idx'), 10)];
            input.value = it.label;
            box.style.display = 'none';
            onPick(it);
          });
        });
      });
    }, 250);
  });

  wrap.appendChild(input);
  wrap.appendChild(box);
  document.getElementById('cbe-level-selects').appendChild(wrap);
  return input;
}

function cbeSearchCommunity(q, cb){
  fetch(groupTypeaheadUrl+'?category=cbe&q='+encodeURIComponent(q)+'&_='+Date.now(), {cache:'no-store'})
    .then(function(r){return r.json();})
    .then(function(list){
      cb(list.map(function(g){ return {kind:'node', nodeId:g.group_id, label:g.label}; }));
    }).catch(function(){ cb([]); });
}

function cbeSearchChildren(parentNodeId, cityFilter, q, cb){
  var url = cbeChildrenUrl+'?parent_node_id='+encodeURIComponent(parentNodeId)+'&q='+encodeURIComponent(q)+'&_='+Date.now();
  if(cityFilter){ url += '&city='+encodeURIComponent(cityFilter); }
  fetch(url, {cache:'no-store'})
    .then(function(r){return r.json();})
    .then(function(data){
      if(data.mode === 'city_group'){
        cb((data.groups||[]).map(function(g){
          return {kind:'city', parentNodeId:parentNodeId, city:g.city, label:g.city+' ('+g.count+')', levelName:data.level_name};
        }));
      } else {
        cb((data.children||[]).map(function(c){
          return {kind:'node', nodeId:c.node_id, label:c.label, levelName:data.level_name};
        }));
      }
    }).catch(function(){ cb([]); });
}

// Fires when a suggestion is clicked at any level. `parentInput` is the
// input box the pick happened in — anything appended AFTER it (deeper,
// now-stale levels from a previous path) gets removed first so drilling
// back up and picking a different branch never leaves orphaned boxes.
function onCbePick(item, parentInput){
  if(parentInput){
    var wrap = document.getElementById('cbe-level-selects');
    var afterParent = parentInput.parentNode.nextSibling;
    while(afterParent){ var toRemove = afterParent; afterParent = afterParent.nextSibling; wrap.removeChild(toRemove); }
  }

  if(item.kind === 'city'){
    cbeScope = {nodeId: item.parentNodeId, city: item.city, label: item.label};
  } else {
    cbeScope = {nodeId: item.nodeId, city: '', label: item.label};
  }
  document.getElementById('cbe-go-btn').style.display = 'inline-block';

  // Probe whether there's a next level to drill into (empty search =
  // "give me everything at this level", capped at 30 by the backend).
  // If there's nothing, this is the deepest level — no more boxes,
  // Admin just clicks Go.
  cbeSearchChildren(cbeScope.nodeId, cbeScope.city, '', function(nextItems){
    if(!nextItems.length) return;
    var levelLabel = nextItems[0].levelName || '';
    var newInput = addCbeTypeaheadLevel(i18n.cbeSearchLevel.replace(':level', levelLabel), function(q, cb){
      cbeSearchChildren(cbeScope.nodeId, cbeScope.city, q, cb);
    }, function(picked){ onCbePick(picked, newInput); });
  });
}

function onCbeGo(){
  if(!cbeScope.nodeId) return;
  selGroupId = cbeScope.nodeId;
  selCbeCity = cbeScope.city;
  selGroupLabel = cbeScope.label;
  document.getElementById('group-selected-label').textContent = cbeScope.label;
  document.getElementById('group-selected-badge').style.display = 'flex';
  scopeConfirmed = true;
  saveScopeState();
  loadMetrics();
}

function confirmLoadAll(){
  scopeConfirmed = true;
  saveScopeState();
  loadMetrics();
}

function onGroupSearchInput(){
  var q = document.getElementById('group-search').value.trim();
  if(groupSearchTimer) clearTimeout(groupSearchTimer);
  var box = document.getElementById('group-suggestions');
  if(q.length < 1){ box.style.display='none'; return; }
  groupSearchTimer = setTimeout(function(){
    fetch(groupTypeaheadUrl+'?category='+encodeURIComponent(selCategory)+'&q='+encodeURIComponent(q)+'&_='+Date.now(), {cache:'no-store'})
      .then(function(r){return r.json();})
      .then(function(list){
        if(!list.length){ box.innerHTML='<div style="padding:6px 8px;font-size:8px;color:#94A3B8;">'+i18n.noMatches+'</div>'; box.style.display='block'; return; }
        box.innerHTML = list.map(function(g){
          var safeLabel = g.label.replace(/'/g,"&#39;");
          return '<div class="group-suggestion" data-id="'+g.group_id+'" data-label="'+safeLabel+'" style="padding:6px 8px;font-size:8px;color:#1565C0;cursor:pointer;border-bottom:1px solid #eee;">'+g.label+'</div>';
        }).join('');
        box.style.display='block';
        Array.prototype.forEach.call(box.querySelectorAll('.group-suggestion'), function(el){
          el.addEventListener('click', function(){
            selectGroupSuggestion(el.getAttribute('data-id'), el.getAttribute('data-label'));
          });
        });
      }).catch(function(){});
  }, 250);
}

function selectGroupSuggestion(id, label){
  selGroupId = id;
  selGroupLabel = label;
  selCbeCity = ''; // a real node fully identifies scope on its own — any inherited Branch/city filter no longer applies
  document.getElementById('group-search').value = '';
  document.getElementById('group-suggestions').style.display='none';
  document.getElementById('group-search-wrap').style.display='none';
  document.getElementById('group-selected-label').textContent = label;
  document.getElementById('group-selected-badge').style.display='flex';
  scopeConfirmed = true;
  saveScopeState();
  loadMetrics();
}

function clearGroupSelection(reload){
  selGroupId = '';
  selGroupLabel = '';
  selCbeCity = '';
  document.getElementById('group-selected-badge').style.display='none';
  document.getElementById('group-search').value='';
  document.getElementById('group-suggestions').style.display='none';
  if(selCategory === 'cbe'){
    document.getElementById('cbe-drill-wrap').style.display='flex';
    startCbeDrill();
  } else if(selCategory !== 'all' && selCategory !== '') {
    document.getElementById('group-search-wrap').style.display='block';
  }
  if(reload !== false){
    clearScopeState();
    showScopePrompt();
  }
}

function initPicker(){
  document.getElementById('month-select').value = selMonth;
  document.getElementById('year-select').value  = selYear;
  updatePeriodLabel();
}

function updatePeriodLabel(){
  var now = new Date();
  var isCurrent = selMonth===(now.getMonth()+1) && selYear===now.getFullYear();
  document.getElementById('period-label').textContent = monthNames[selMonth]+' '+selYear;
  document.getElementById('mtd-note').textContent = isCurrent ? i18n.currentMonth : '';
}

function changeMonth(dir){
  selMonth += dir;
  if(selMonth < 1){ selMonth=12; selYear--; }
  if(selMonth > 12){ selMonth=1; selYear++; }
  var now = new Date();
  if(selYear > now.getFullYear() || (selYear===now.getFullYear() && selMonth > now.getMonth()+1)){
    selMonth -= dir;
    if(selMonth < 1){ selMonth=12; selYear--; }
    if(selMonth > 12){ selMonth=1; selYear++; }
    return;
  }
  document.getElementById('month-select').value = selMonth;
  document.getElementById('year-select').value  = selYear;
  updatePeriodLabel();
  if(scopeConfirmed){ saveScopeState(); loadMetrics(); }
}

function onMonthSelect(){
  selMonth = parseInt(document.getElementById('month-select').value);
  selYear  = parseInt(document.getElementById('year-select').value);
  updatePeriodLabel();
  if(scopeConfirmed){ saveScopeState(); loadMetrics(); }
}

function mkChart(cid,skid,type,lb,sa,ea,pc){
  var sk=document.getElementById(skid);
  var cv=document.getElementById(cid);
  if(sk)sk.style.display='none';
  cv.style.display='block';
  var ctx=cv.getContext('2d');
  var chart;
  if(type==='pie'){
    var pieColors=['#90CAF9','#FFF176','#F48FB1','#7C3AED','#DB2777','#0891B2','#059669','#DC2626'];
    var isTrend=(cid==='trendChart');
    var pieLabels=isTrend?['Sales','Earnings']:lb;
    var pieData=isTrend
      ?[sa.reduce(function(a,b){return a+b;},0),ea.reduce(function(a,b){return a+b;},0)]
      :sa;
    var pieBg=isTrend?['#1565C0','#1B5E20']:lb.map(function(_,i){return pieColors[i%pieColors.length];});
    chart=new Chart(ctx,{type:'pie',
      data:{labels:pieLabels,datasets:[{data:pieData,backgroundColor:pieBg,borderWidth:2,borderColor:'#fff'}]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false},
          tooltip:{callbacks:{label:function(c){
            var tot=c.dataset.data.reduce(function(a,b){return a+b;},0);
            var sharePct=tot>0?Math.round(c.raw/tot*100):0;
            var earnAmt=ea[c.dataIndex]||0;
            var earnPct=c.raw>0?((earnAmt/c.raw)*100).toFixed(1):'0.0';
            if(isTrend) return c.label+': RM '+c.raw.toLocaleString()+' ('+sharePct+'%)';
            return [c.label+': RM '+c.raw.toLocaleString()+' ('+sharePct+'%)','Earning Income: RM '+earnAmt.toLocaleString(),'Earning %: '+earnPct+'%'];
          }}}
        }}});
  } else {
    var dsSales={label:'Sales',data:sa,borderColor:'#1565C0',
      backgroundColor:type==='bar'?'rgba(21,101,192,0.8)':'transparent',
      borderWidth:1.5,tension:0.4,fill:false,pointRadius:2,
      borderRadius:type==='bar'?3:0,yAxisID:'yLeft'};
    var dsEarn={label:'Earnings',data:ea,borderColor:'#1B5E20',
      backgroundColor:type==='bar'?'rgba(27,94,32,0.8)':'transparent',
      borderWidth:1.5,tension:0.4,fill:false,pointRadius:2,
      borderRadius:type==='bar'?3:0,yAxisID:'yLeft'};
    var dsPct={label:'% Earn/Sales',data:pc,borderColor:'#D97706',
      backgroundColor:type==='bar'?'rgba(217,119,6,0.8)':'transparent',
      borderWidth:1.5,tension:0.4,fill:false,pointRadius:2,borderDash:[4,2],
      borderRadius:type==='bar'?3:0,yAxisID:'yRight'};
    chart=new Chart(ctx,{type:type,
      data:{labels:lb,datasets:[dsSales,dsEarn,dsPct]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:false}},
        scales:{
          x:{display:false,grid:{display:false}},
          yLeft:{type:'linear',position:'left',
            ticks:{font:{size:7,family:'Poppins'},color:'#1565C0',
              callback:function(v){return v>=1000?(v/1000).toFixed(0)+'K':v;}},
            grid:{color:'rgba(0,0,0,0.05)'}},
          yRight:{type:'linear',position:'right',
            min:0,max:100,
            display:false,
            grid:{display:false}}
        }}});
  }
  setTimeout(function(){chart.resize();},150);
  return chart;
}

var PD={gl:{sales:[],earnings:[]},tl:{sales:[],earnings:[]},intro:{sales:[],earnings:[]}};
var PL=false;

function perfDrillUrl(role,d){
  var mp='?from=dashboard&month='+selMonth+'&year='+selYear;
  if(role==='gl')    return baseUrl+'/admin/network/'+d.agent_id+mp;
  if(role==='tl')    return baseUrl+'/admin/network/'+(d.gl_id||'0')+'/'+d.agent_id+mp;
  if(role==='intro') return baseUrl+'/admin/network/'+(d.gl_id||'0')+'/'+(d.tl_id||'0')+'/'+d.agent_id+mp;
  return '#';
}
function switchPerf(){
  var vaBase=baseUrl+'/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear+scopeParams();
  var role=document.querySelector('input[name="perf-role"]:checked').value;
  var el=document.getElementById('perf-viewall');
  if(el)el.href=vaBase+'&type=performers&role='+role;
  if(!PL)return;
  var type=document.querySelector('input[name="perf-type"]:checked').value;
  var role=document.querySelector('input[name="perf-role"]:checked').value;
  var data=PD[role][type]||[];
  var html='';
  for(var i=0;i<data.length;i++){
    var d=data[i];
    var url=perfDrillUrl(role,d);
    // A GL whose agent_code contains "-" was promoted out of another GL's
    // downline (same convention used by the group-name search badge) —
    // show a small tag so Admin knows at a glance, without needing a
    // separate radio filter.
    var promotedTag = (role==='gl' && d.agent_code && d.agent_code.indexOf('-')!==-1)
      ? ' <span style="font-size:6px;font-weight:700;color:#fff;background:#1B9AE4;border-radius:8px;padding:1px 5px;vertical-align:middle;">'+i18n.promoted+'</span>'
      : '';
    html+='<a href="'+url+'" style="text-decoration:none;" class="perf-card">'
      +'<div style="display:flex;align-items:center;gap:4px;min-width:0;flex:1;">'
      +'<span style="font-size:13px;flex-shrink:0;">'+(medals[i]||'')+'</span>'
      +'<div style="min-width:0;overflow:hidden;">'
      +'<div class="perf-name">'+d.full_name+promotedTag+'</div>'

      +'</div></div>'
      +'<div class="perf-amt">'+fRM(d.total)+'</div>'
      +'</a>';
  }
  document.getElementById('perf-list').innerHTML=html||'<div style="text-align:center;color:#64748B;font-size:9px;padding:8px;">'+i18n.noData+'</div>';
}

var tC=null,tL=[],tS=[],tE=[],tP=[];
function switchChart(type){if(tC)tC.destroy();tC=mkChart('trendChart','trend-sk',type,tL,tS,tE,tP);var el=document.getElementById('trend-legend');if(el)el.style.display=type==='pie'?'none':'flex';}
var vC=null,vL=[],vS=[],vE=[],vP=[];
function switchVendor(type){
  if(vC)vC.destroy();
  vC=mkChart('vendorChart','vendor-sk',type,vL,vS,vE,vP);
}
var pC=null,pL=[],pS=[],pE=[],pP=[];
function switchProduct(type){
  if(pC)pC.destroy();
  pC=mkChart('productChart','product-sk',type,pL,pS,pE,pP);
}

function loadMetrics(){
  showDashboardGrid();
  ['b2-gl','b2-tl','b2-intro','b2-pend','b2-total',
   'b6-unclaim','b6-r30','b6-r30p','b6-redeem','b6-pts'].forEach(function(id){
    var el=document.getElementById(id);
    if(el) el.innerHTML='<div class="sk" style="width:24px;height:12px;"></div>';
  });
  PL=false;
  document.getElementById('perf-list').innerHTML='<div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div>';

  fetch(metricsUrl+'?month='+selMonth+'&year='+selYear+scopeParams()+'&_='+Date.now())
  .then(function(r){return r.json();})
  .then(function(d){
    if(d.shortLabels){
      var lgl=document.getElementById('perf-lbl-gl'); if(lgl) lgl.textContent=d.shortLabels.gl;
      var ltl=document.getElementById('perf-lbl-tl'); if(ltl) ltl.textContent=d.shortLabels.tl;
      var lin=document.getElementById('perf-lbl-intro'); if(lin) lin.textContent=d.shortLabels.intro;
    }
    document.getElementById('b2-gl').textContent=fN(d.totalGL);
    document.getElementById('b2-tl').textContent=fN(d.totalTL);
    document.getElementById('b2-intro').textContent=fN(d.totalIntroducers);
    document.getElementById('b2-pend').textContent=fN(d.pendingAssignment);
    document.getElementById('b2-total').textContent=fN((d.totalGL||0)+(d.totalTL||0)+(d.totalIntroducers||0));

    tL=d.trend_labels||[];tS=d.trend_sales||[];tE=d.trend_earn||[];
    tP=tS.map(function(s,i){return s>0?parseFloat((tE[i]/s*100).toFixed(1)):0;});
    var savedTrend='';
    try{savedTrend=localStorage.getItem('gl_chart_type_trend')||'';}catch(e){}
    var defaultTrend=savedTrend||'bar';
    document.querySelector('input[name="trend"][value="'+defaultTrend+'"]').checked=true;
    switchChart(defaultTrend);

    vL=(d.vendors||[]).map(function(v){return v.name;});
    vS=(d.vendors||[]).map(function(v){return parseFloat(v.sales)||0;});
    vE=(d.vendors||[]).map(function(v){return parseFloat(v.earn)||0;});
    vP=vS.map(function(s,i){return s>0?parseFloat((vE[i]/s*100).toFixed(1)):0;});
    var savedVendor='';
    try{savedVendor=localStorage.getItem('gl_chart_type_vendor')||'bar';}catch(e){}
    document.querySelector('input[name="vendor"][value="'+savedVendor+'"]').checked=true;
    switchVendor(savedVendor);

    pL=(d.products||[]).map(function(p){return p.name;});
    pS=(d.products||[]).map(function(p){return parseFloat(p.sales)||0;});
    pE=(d.products||[]).map(function(p){return parseFloat(p.earn)||0;});
    pP=pS.map(function(s,i){return s>0?parseFloat((pE[i]/s*100).toFixed(1)):0;});
    var savedProduct='';
    try{savedProduct=localStorage.getItem('gl_chart_type_product')||'bar';}catch(e){}
    document.querySelector('input[name="product"][value="'+savedProduct+'"]').checked=true;
    switchProduct(savedProduct);

    var ddBase2=baseUrl+'/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear+scopeParams();
    document.getElementById('b6-unclaim-row').href=ddBase2+'&type=unclaimed&period=mtd';
    document.getElementById('b6-unclaim').innerHTML='<span style="color:#E53E3E;font-weight:700;">'+fRM(d.unclaimedCommission||0)+'</span>';
    document.getElementById('b6-r30-row').href=baseUrl+'/admin/dashboard/drilldown?type=renewal_lte30&month='+selMonth+'&year='+selYear+scopeParams();
    document.getElementById('b6-r30').textContent=fN(d.renewal_lte30||0);
    document.getElementById('b6-r30p-row').href=baseUrl+'/admin/dashboard/drilldown?type=renewal_gt30&month='+selMonth+'&year='+selYear+scopeParams();
    document.getElementById('b6-r30p').textContent=fN(d.renewal_gt30||0);
    document.getElementById('b6-redeem-row').href=baseUrl+'/admin/transactions?type=redemption';
    document.getElementById('b6-redeem').textContent=fN(d.redemption_pending||0);
    document.getElementById('b6-pts-row').href=baseUrl+'/admin/points';
    document.getElementById('b6-pts').textContent=fN(d.pointsPurchasePending||0);

    var ddBase=baseUrl+'/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear+scopeParams();
    var lnk=function(type,period,val){return '<a href="'+ddBase+'&type='+type+'&period='+period+'" style="color:#1565C0;text-decoration:none;font-weight:600;">'+fRM(val)+'</a>';};
    document.getElementById('perf-tbody').innerHTML=
      '<tr><td class="kpi">'+i18n.salesAmount+'</td><td class="num">'+lnk('sales','last_mtd',d.salesLastMTD||0)+'</td><td class="num">'+lnk('sales','mtd',d.totalPremiumMonth||0)+'</td><td class="num">'+lnk('sales','ytd',d.totalPremiumYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">'+i18n.earningIncome+'</td><td class="num">'+lnk('earnings','last_mtd',d.earnLastMTD||0)+'</td><td class="num">'+lnk('earnings','mtd',d.commDistributedMonth||0)+'</td><td class="num">'+lnk('earnings','ytd',d.commDistributedYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">'+i18n.unclaimedEarning+'</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedLastMTD||0)+'</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedCommission||0)+'</td><td class="num">'+lnk('unclaimed','ytd',d.unclaimedCommission||0)+'</td></tr>';

    PD=d.topPerformers||PD;PL=true;switchPerf();

    var vaBase=baseUrl+'/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear+scopeParams();
    var perfRole=document.querySelector('input[name="perf-role"]:checked').value;
    document.getElementById('perf-viewall').href=vaBase+'&type=performers&role='+perfRole;
    var cdBase=baseUrl+'/admin/dashboard/chart-drilldown?month='+selMonth+'&year='+selYear+scopeParams();
    var vendorCtype=localStorage.getItem('gl_chart_type_vendor')||'bar';
    var productCtype=localStorage.getItem('gl_chart_type_product')||'bar';
    var trendCtype=localStorage.getItem('gl_chart_type_trend')||'bar';
    document.getElementById('vendor-viewall').href=cdBase+'&type=vendors&ctype='+vendorCtype;
    document.getElementById('product-viewall').href=cdBase+'&type=products&ctype='+productCtype;
    document.getElementById('trend-viewall').href=cdBase+'&type=trend&ctype='+trendCtype;
  }).catch(function(e){console.error('metrics error:',e);});
}

initPicker();
var _saved = loadScopeState();
if(_saved && _saved.sessionId === currentSessionId && (_saved.groupId || _saved.category === 'all')){
  selCategory   = _saved.category   || '';
  selGroupId    = _saved.groupId    || '';
  selGroupLabel = _saved.groupLabel || '';
  selCbeCity    = _saved.cbeCity    || '';
  if(_saved.month){ selMonth = _saved.month; }
  if(_saved.year){ selYear = _saved.year; }
  document.getElementById('category-select').value = selCategory;
  document.getElementById('month-select').value = selMonth;
  document.getElementById('year-select').value  = selYear;
  updatePeriodLabel();
  if(selGroupId){
    document.getElementById('group-selected-label').textContent = selGroupLabel;
    document.getElementById('group-selected-badge').style.display = 'flex';
    document.getElementById('group-search-wrap').style.display = 'none';
    // Restoring a saved CBE scope doesn't rebuild the exact dropdown
    // chain (which State/Branch/Affiliate was picked) — only the badge
    // and the actual data scope matter for the dashboard itself; a
    // fresh drill-down always starts clean from the Community dropdown.
    if(selCategory === 'cbe'){ document.getElementById('cbe-drill-wrap').style.display = 'none'; }
  } else if(selCategory === 'all'){
    document.getElementById('group-search-wrap').style.display = 'none';
  } else if(selCategory === 'cbe'){
    document.getElementById('group-search-wrap').style.display = 'none';
    document.getElementById('cbe-drill-wrap').style.display = 'flex';
    startCbeDrill();
  }
  scopeConfirmed = true;
  loadMetrics();
} else {
  if(_saved && _saved.sessionId !== currentSessionId){ clearScopeState(); }
  showScopePrompt();
}
@endverbatim
</script>
@endpush