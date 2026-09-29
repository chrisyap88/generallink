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

/* Chart wrapper */
.chart-wrap{position:relative;flex:1;min-height:60px;}
.chart-wrap canvas{position:absolute;inset:0;width:100%!important;height:100%!important;display:none;}

/* Performance table */
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

.perf-card{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:2px 6px;gap:5px;flex-shrink:0;}
.perf-name{font-size:7px;color:#000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.perf-sub{font-size:7px;color:#555;}
.perf-amt{font-size:8px;color:#000;flex-shrink:0;white-space:nowrap;}
</style>
@endpush

@section('content')
@php
    // NEW 24 Jul 2026 — per Chris: "Promoted" only makes sense where
    // Introducer -> Team Leader is an actual promotion pathway. A
    // An Organization Rewards Group (e.g. PVATM) has Promotion/Demotion Rules
    // Disabled — its TLs were directly appointed, never "promoted" from
    // an Introducer — so that word is dropped for those groups only.
    $me = \Illuminate\Support\Facades\Auth::guard('agent')->user();
    $isSpecialGroup = $me->group_label_id
        ? (bool) \Illuminate\Support\Facades\DB::table('group_labels')->where('group_label_id', $me->group_label_id)->value('promotion_demotion_enabled') === false
        : false;
    $tlWord = $isSpecialGroup ? '' : __('dashboard.promoted_word').' ';
@endphp
<div class="db">

{{-- Month/Year Picker --}}
<div class="month-bar">
  <span class="as-of">{{ __('dashboard.viewing_label') }}</span>
  <button onclick="changeMonth(-1)" title="Previous month">&#8592;</button>
  <span class="period-label" id="period-label"></span>
  <button onclick="changeMonth(1)" title="Next month">&#8594;</button>
  <span style="margin-left:8px;font-size:7px;color:#64748B;">|</span>
  <select id="month-select" onchange="onMonthSelect()" style="margin-left:4px;">
    <option value="1">{{ __('dashboard.month_1') }}</option><option value="2">{{ __('dashboard.month_2') }}</option>
    <option value="3">{{ __('dashboard.month_3') }}</option><option value="4">{{ __('dashboard.month_4') }}</option>
    <option value="5">{{ __('dashboard.month_5') }}</option><option value="6">{{ __('dashboard.month_6') }}</option>
    <option value="7">{{ __('dashboard.month_7') }}</option><option value="8">{{ __('dashboard.month_8') }}</option>
    <option value="9">{{ __('dashboard.month_9') }}</option><option value="10">{{ __('dashboard.month_10') }}</option>
    <option value="11">{{ __('dashboard.month_11') }}</option><option value="12">{{ __('dashboard.month_12') }}</option>
  </select>
  <select id="year-select" onchange="onMonthSelect()">
    @for($y = 2024; $y <= date('Y'); $y++)
      <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
    @endfor
  </select>
  <span class="as-of" id="mtd-note" style="margin-left:8px;"></span>
  {{-- NEW 22 Jul 2026 — per Chris: TL also needs quick access to the
       Sales and Earning Income Forecast (drill down: Introducer,
       whole team) straight from the home dashboard. --}}
  <a href="{{ route('renewal-forecast.index') }}" style="margin-left:auto; background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:2px 8px; font-size:7px; font-weight:700; line-height:1.6; white-space:nowrap;">{{ __('dashboard.sales_earning_forecast_link') }}</a>
  <a id="export-btn" href="#" style="margin-left:8px;background:#38A169;color:#fff;border-radius:5px;padding:2px 8px;font-size:7px;text-decoration:none;font-weight:600;line-height:1.6;">{{ __('dashboard.export_excel_button') }}</a>
</div>

<div class="grid">

  {{-- BOX 1: Top Performers --}}
  <div class="card" style="border-top:3px solid #7DE7E7;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_role_heading', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</span><a id="box1-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all_link') }}</a></div>
    <div class="radio-row">
      <label><input type="radio" name="perf-type" value="sales" checked onchange="switchPerf()"> {{ __('dashboard.sales_radio') }}</label>
      <label><input type="radio" name="perf-type" value="earnings" onchange="switchPerf()"> {{ __('dashboard.earnings_radio') }}</label>
      <span style="color:#ccc;font-size:7px;">|</span>
      <label><input type="radio" name="perf-role" value="intro" checked onchange="switchPerf()"> {{ \App\Services\RoleLabelService::shortLabel('INTRODUCER') }}</label>
      <label><input type="radio" name="perf-role" value="promoted_tl" onchange="switchPerf()"> {{ $tlWord }}{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }}</label>
    </div>
    <div id="perf-list" style="display:flex;flex-direction:column;gap:2px;justify-content:space-between;flex:1;min-height:0;">
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
    </div>
  </div>

  {{-- BOX 2: Network Overview --}}
  <div class="card" style="border-top:3px solid #6EE7F9;">
    <div class="ct">{{ __('dashboard.network_overview_heading') }}</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="#" id="box2-promoted-link" class="row-item">
        <div class="row-lbl">{{ $tlWord }}{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }}</div>
        <div class="row-val" id="b2-promoted">0</div>
      </a>
      <a href="#" id="box2-intros-link" class="row-item">
        <div class="row-lbl">{{ \App\Services\RoleLabelService::plural('INTRODUCER') }}</div>
        <div class="row-val" id="b2-intro"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="#" id="box2-active-link" class="row-item">
        <div class="row-lbl">{{ __('dashboard.active_role', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</div>
        <div class="row-val" id="b2-active"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="#" id="box2-inactive-link" class="row-item">
        <div class="row-lbl">{{ __('dashboard.inactive_role', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</div>
        <div class="row-val" id="b2-pend"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
    </div>
  </div>

  {{-- BOX 6: Pending Actions --}}
  <div class="card" style="border-top:3px solid #EF4444;">
    <div class="ct">{{ __('dashboard.pending_actions_heading') }}</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="#" id="b6-unclaim-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.unclaimed_earning_rm_label') }}</div><div class="row-val" id="b6-unclaim"><div class="sk" style="width:40px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.renewal_lte_30_label') }}</div><div class="row-val" id="b6-r30"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30p-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.renewal_gt_30_label') }}</div><div class="row-val" id="b6-r30p"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-redeem-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.redemption_label') }}</div><div class="row-val" id="b6-redeem"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-pts-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">{{ __('dashboard.points_purchase_approval_label') }}</div><div class="row-val" id="b6-pts"><div class="sk" style="width:24px;height:12px;"></div></div></a>
    </div>
  </div>

  {{-- BOX 4: Sales by Vendor --}}
  <div class="card" style="border-top:3px solid #5EEAD4;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_vendors_heading') }}</span><a id="vendor-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all_link') }}</a></div>
    <div class="radio-row">
      <label><input type="radio" name="vendor" value="bar" checked onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}updateVALink('vendors',this.value,'vendor-viewall');"> {{ __('dashboard.bar_option') }}</label>
      <label><input type="radio" name="vendor" value="line" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}updateVALink('vendors',this.value,'vendor-viewall');"> {{ __('dashboard.line_option') }}</label>
      <label><input type="radio" name="vendor" value="pie" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}updateVALink('vendors',this.value,'vendor-viewall');"> {{ __('dashboard.pie_option') }}</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="vendor-sk" style="position:absolute;inset:0;"></div>
      <canvas id="vendorChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_to_graph_vendor_note') }}</div>
  </div>

  {{-- BOX 5: Sales by Product --}}
  <div class="card" style="border-top:3px solid #A7F3F0;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_products_heading') }}</span><a id="product-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all_link') }}</a></div>
    <div class="radio-row">
      <label><input type="radio" name="product" value="bar" checked onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}updateVALink('products',this.value,'product-viewall');"> {{ __('dashboard.bar_option') }}</label>
      <label><input type="radio" name="product" value="line" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}updateVALink('products',this.value,'product-viewall');"> {{ __('dashboard.line_option') }}</label>
      <label><input type="radio" name="product" value="pie" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}updateVALink('products',this.value,'product-viewall');"> {{ __('dashboard.pie_option') }}</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="product-sk" style="position:absolute;inset:0;"></div>
      <canvas id="productChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_to_graph_product_note') }}</div>
  </div>

  {{-- BOX 3: Sales & Earnings Trend --}}
  <div class="card" style="border-top:3px solid #10B981;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>{{ __('dashboard.top3_months_heading') }}</span><a id="trend-viewall" href="#" style="font-size:7px;color:#1565C0;text-decoration:none;font-weight:600;">{{ __('dashboard.view_all_link') }}</a></div>
    <div class="radio-row">
      <label><input type="radio" name="trend" value="line" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}updateVALink('trend',this.value,'trend-viewall');"> {{ __('dashboard.line_option') }}</label>
      <label><input type="radio" name="trend" value="bar" checked onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}updateVALink('trend',this.value,'trend-viewall');"> {{ __('dashboard.bar_option') }}</label>
      <label><input type="radio" name="trend" value="pie" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}updateVALink('trend',this.value,'trend-viewall');"> {{ __('dashboard.pie_option') }}</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="trend-sk" style="position:absolute;inset:0;"></div>
      <canvas id="trendChart"></canvas>
    </div>
    <div style="font-size:7px;color:#718096;font-style:italic;text-align:center;margin-top:1px;font-size:6px;">{{ __('dashboard.point_to_graph_trend_note') }}</div>
  </div>

  {{-- PERFORMANCE TABLE --}}
  <div class="pc">
    <div class="ct">{{ __('dashboard.performance_overview_heading') }}</div>
    <table class="pt">
      <thead>
        <tr>
          <th class="kpi" style="width:25%;">{{ __('dashboard.th_kpi') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.th_last_mtd') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.th_mtd') }}</th>
          <th class="num" style="width:25%;">{{ __('dashboard.th_ytd') }}</th>
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
var metricsUrl='{{ route("tl.dashboard.metrics") }}';
var exportUrl='{{ route("tl.dashboard.export") }}';
var introBase='{{ route("tl.introducers") }}';
var promotedBase='{{ route("tl.promoted-tls") }}';
var pieColors=['#90CAF9','#FFF176','#F48FB1'];
var i18nNoData=@json(__('dashboard.no_data_text'));
var i18nNoSalesData=@json(__('dashboard.no_sales_data_note'));
var i18nSalesAmount=@json(__('dashboard.sales_amount_row'));
var i18nEarningIncome=@json(__('dashboard.earning_income_row'));
var i18nUnclaimedEarning=@json(__('dashboard.unclaimed_earning_row'));
var i18nCurrentMonth=@json(__('dashboard.current_month_note'));
var i18nM1=@json(__('dashboard.month_1')), i18nM2=@json(__('dashboard.month_2')), i18nM3=@json(__('dashboard.month_3')), i18nM4=@json(__('dashboard.month_4'));
var i18nM5=@json(__('dashboard.month_5')), i18nM6=@json(__('dashboard.month_6')), i18nM7=@json(__('dashboard.month_7')), i18nM8=@json(__('dashboard.month_8'));
var i18nM9=@json(__('dashboard.month_9')), i18nM10=@json(__('dashboard.month_10')), i18nM11=@json(__('dashboard.month_11')), i18nM12=@json(__('dashboard.month_12'));
@verbatim
var fRM=function(v){return 'RM '+parseFloat(v||0).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});};
var fN=function(v){return Number(v||0).toLocaleString();};
var medals=['🥇','🥈','🥉'];

/* Month/Year picker state */
var selMonth = new Date().getMonth()+1;
var selYear  = new Date().getFullYear();
var monthNames = ['',i18nM1,i18nM2,i18nM3,i18nM4,i18nM5,i18nM6,i18nM7,i18nM8,i18nM9,i18nM10,i18nM11,i18nM12];

function initPicker(){
  document.getElementById('month-select').value = selMonth;
  document.getElementById('year-select').value  = selYear;
  updatePeriodLabel();
}

function updatePeriodLabel(){
  var now = new Date();
  var isCurrent = selMonth===(now.getMonth()+1) && selYear===now.getFullYear();
  document.getElementById('period-label').textContent = monthNames[selMonth]+' '+selYear;
  document.getElementById('mtd-note').textContent = isCurrent ? i18nCurrentMonth : '';
}

function changeMonth(dir){
  selMonth += dir;
  if(selMonth < 1){ selMonth=12; selYear--; }
  if(selMonth > 12){ selMonth=1; selYear++; }
  /* Don't allow future months */
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
  loadMetrics();
}

function onMonthSelect(){
  selMonth = parseInt(document.getElementById('month-select').value);
  selYear  = parseInt(document.getElementById('year-select').value);
  updatePeriodLabel();
  loadMetrics();
}

function mkChart(cid,skid,type,lb,sa,ea,pc){
  var sk=document.getElementById(skid);
  var cv=document.getElementById(cid);
  if(sk)sk.style.display='none';
  cv.style.display='block';
  var ctx=cv.getContext('2d');
  var chart;
  if(type==='pie'){
    var vendorPieColors=['#90CAF9','#FFF176','#F48FB1'];
    var isTrend=(cid==='trendChart');
    var pieLabels=isTrend?['Sales','Earnings']:lb;
    var pieData=isTrend
      ?[sa.reduce(function(a,b){return a+b;},0),ea.reduce(function(a,b){return a+b;},0)]
      :sa;
    var pieBg=isTrend?['#1565C0','#1B5E20']:lb.map(function(_,i){return vendorPieColors[i%vendorPieColors.length];});
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
        plugins:{legend:{display:true,labels:{font:{size:7,family:'Poppins'},boxWidth:7,padding:3,color:'#000'}}},
        scales:{
          x:{grid:{display:false},ticks:{display:false}},
          yLeft:{type:'linear',position:'left',
            ticks:{font:{size:7,family:'Poppins'},color:'#1565C0',
              callback:function(v){return v>=1000?(v/1000).toFixed(0)+'K':v;}},
            grid:{color:'rgba(0,0,0,0.05)'}},
          yRight:{type:'linear',position:'right',
            min:0,max:100,
            ticks:{font:{size:7,family:'Poppins'},color:'#D97706',
              callback:function(v){return v+'%';}},
            grid:{display:false}}
        }}});
  }
  setTimeout(function(){chart.resize();},150);
  return chart;
}

var PD={gl:{sales:[],earnings:[]},tl:{sales:[],earnings:[]},intro:{sales:[],earnings:[]},promoted_tl:{sales:[],earnings:[]}};
var PL=false;

function perfDrillUrl(role,d){
  var mp='?from=dashboard&month='+selMonth+'&year='+selYear;
  if(role==='promoted_tl') return '/tl/promoted-tls/'+d.agent_id+'/transactions'+mp;
  return '/tl/introducers/'+d.agent_id+'/transactions'+mp;
}
function updateVALink(type, ctype, elId){
  var el = document.getElementById(elId);
  if(!el) return;
  var url = new URL(el.href, window.location.origin);
  url.searchParams.set('ctype', ctype);
  el.href = url.toString();
}
function switchPerf(){
  var vaBase='/tl/dashboard/drilldown?month='+selMonth+'&year='+selYear;
  var roleEl=document.querySelector('input[name="perf-role"]:checked');
  var role=roleEl?roleEl.value:'intro';
  var el=document.getElementById('perf-viewall');
  if(!PL)return;
  var type=document.querySelector('input[name="perf-type"]:checked').value;
  var data=PD[role][type]||[];
  var html='';
  // Filter out zero amounts
  var nonZero = data.filter(function(d){ return (type==='sales'?d.sales:d.earn) > 0; });
  if(nonZero.length === 0){
    html='<div style="text-align:center;color:#9ca3af;font-size:9px;padding:10px;">'+i18nNoSalesData+'</div>';
  } else {
    for(var i=0;i<nonZero.length;i++){
      var d=nonZero[i];
      var url=perfDrillUrl(role,d);
      html+='<a href="'+url+'" style="text-decoration:none;" class="perf-card">'
        +'<div style="display:flex;align-items:center;gap:4px;min-width:0;flex:1;">'
        +'<span style="font-size:11px;flex-shrink:0;">'+(medals[i]||'')+'</span>'
        +'<div class="perf-name" style="min-width:0;overflow:hidden;">'+d.full_name+'</div>'
        +'</div>'
        +'<div class="perf-amt">'+fRM(type==="sales"?d.sales:d.earn)+'</div>'
        +'</a>';
    }
  }
  document.getElementById('perf-list').innerHTML=html||'<div style="text-align:center;color:#64748B;font-size:7px;padding:8px;">'+i18nNoData+'</div>';
}

var tC=null,tL=[],tS=[],tE=[],tP=[];
function switchChart(type){if(tC)tC.destroy();tC=mkChart('trendChart','trend-sk',type,tL,tS,tE,tP);}
var vC=null,vL=[],vS=[],vE=[],vP=[];
function switchVendor(type){
  if(vC)vC.destroy();
  vC=mkChart('vendorChart','vendor-sk',type,vL,vS,vE,vP);
  var h=document.getElementById('vendor-pie-hint');
  if(h)h.style.display=type==='pie'?'inline':'none';
}
var pC=null,pL=[],pS=[],pE=[],pP=[];
function switchProduct(type){
  if(pC)pC.destroy();
  pC=mkChart('productChart','product-sk',type,pL,pS,pE,pP);
  var h=document.getElementById('product-pie-hint');
  if(h)h.style.display=type==='pie'?'inline':'none';
}

function loadMetrics(){
  /* Reset skeletons */
  ['b2-intro','b2-active','b2-pend',
   'b6-unclaim','b6-r30','b6-r30p','b6-redeem','b6-pts'].forEach(function(id){
    var el=document.getElementById(id);
    if(el) el.innerHTML='<div class="sk" style="width:24px;height:12px;"></div>';
  });
  PL=false;
  document.getElementById('perf-list').innerHTML='<div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div>';

  fetch(metricsUrl+'?month='+selMonth+'&year='+selYear+'&_='+Date.now())
  .then(function(r){return r.json();})
  .then(function(d){
    document.getElementById('b2-intro').textContent=fN(d.totalIntroducers||0);
    if(document.getElementById('b2-promoted')) document.getElementById('b2-promoted').textContent=fN(d.promotedTLCount||0);
    if(document.getElementById('box2-promoted-link')) document.getElementById('box2-promoted-link').href=promotedBase+'?month='+selMonth+'&year='+selYear;
    document.getElementById('export-btn').href=exportUrl+'?month='+selMonth+'&year='+selYear;
    // Update Box 2 and Box 1 links with correct month/year
    if(document.getElementById('box2-intros-link')) document.getElementById('box2-intros-link').href=introBase+'?month='+selMonth+'&year='+selYear;
    if(document.getElementById('box2-active-link')) document.getElementById('box2-active-link').href=introBase+'?filter=active&month='+selMonth+'&year='+selYear;
    if(document.getElementById('box2-inactive-link')) document.getElementById('box2-inactive-link').href=introBase+'?show_all=1&filter=inactive&month='+selMonth+'&year='+selYear;
    if(document.getElementById('box1-viewall')) document.getElementById('box1-viewall').href=introBase+'?month='+selMonth+'&year='+selYear;
    document.getElementById('b2-active').textContent=fN(d.totalIntroducers||0);
    document.getElementById('b2-pend').textContent=fN(d.inactiveIntro||0);

    tL=d.trend_labels||[];tS=d.trend_sales||[];tE=d.trend_earn||[];
    tP=tS.map(function(s,i){return s>0?parseFloat((tE[i]/s*100).toFixed(1)):0;});
    /* Use bar if only 1 data point — line needs 2+ points to draw */
    var savedTrend = '';
    try { savedTrend = localStorage.getItem('gl_chart_type_trend') || ''; } catch(e) {}
    var defaultTrend = savedTrend || 'bar';
    document.querySelector('input[name="trend"][value="'+defaultTrend+'"]').checked = true;
    switchChart(defaultTrend);

    vL=(d.vendors||[]).map(function(v){return v.name;});
    vS=(d.vendors||[]).map(function(v){return v.sales;});
    vE=(d.vendors||[]).map(function(v){return v.earn;});
    vP=vS.map(function(s,i){return s>0?parseFloat((vE[i]/s*100).toFixed(1)):0;});
    var savedVendor = '';
    try { savedVendor = localStorage.getItem('gl_chart_type_vendor') || 'bar'; } catch(e) {}
    document.querySelector('input[name="vendor"][value="'+savedVendor+'"]').checked = true;
    switchVendor(savedVendor);

    pL=(d.products||[]).map(function(p){return p.name;});
    pS=(d.products||[]).map(function(p){return p.sales;});
    pE=(d.products||[]).map(function(p){return p.earn;});
    pP=pS.map(function(s,i){return s>0?parseFloat((pE[i]/s*100).toFixed(1)):0;});
    var savedProduct = '';
    try { savedProduct = localStorage.getItem('gl_chart_type_product') || 'bar'; } catch(e) {}
    document.querySelector('input[name="product"][value="'+savedProduct+'"]').checked = true;
    switchProduct(savedProduct);

    var ddBase2='/tl/dashboard/drilldown?month='+selMonth+'&year='+selYear;
    document.getElementById('b6-unclaim-row').href=ddBase2+'&type=unclaimed&period=mtd';
    document.getElementById('b6-unclaim').innerHTML='<span style="color:#E53E3E;font-weight:700;">'+fRM(d.unclaimedCommission||0)+'</span>';
    document.getElementById('b6-r30-row').href='/tl/dashboard/drilldown?type=renewal_lte30&month='+selMonth+'&year='+selYear;
    document.getElementById('b6-r30').textContent=fN(d.renewal_lte30||0);
    document.getElementById('b6-r30p-row').href='/tl/dashboard/drilldown?type=renewal_gt30&month='+selMonth+'&year='+selYear;
    document.getElementById('b6-r30p').textContent=fN(d.renewal_gt30||0);
    document.getElementById('b6-redeem-row').href='/admin/transactions?type=redemption';
    document.getElementById('b6-redeem').textContent=fN(d.redemption_pending||0);
    document.getElementById('b6-pts-row').href='/admin/points';
    document.getElementById('b6-pts').textContent=fN(d.pointsPurchasePending||0);

    var ddBase='/tl/dashboard/drilldown?month='+selMonth+'&year='+selYear;
    var lnk=function(type,period,val){return '<a href="'+ddBase+'&type='+type+'&period='+period+'" style="color:#1565C0;text-decoration:none;font-weight:600;">'+fRM(val)+'</a>';};
    document.getElementById('perf-tbody').innerHTML=
      '<tr><td class="kpi">'+i18nSalesAmount+'</td><td class="num">'+lnk('sales','last_mtd',d.salesLastMTD||0)+'</td><td class="num">'+lnk('sales','mtd',d.totalPremiumMonth||0)+'</td><td class="num">'+lnk('sales','ytd',d.totalPremiumYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">'+i18nEarningIncome+'</td><td class="num">'+lnk('earnings','last_mtd',d.earnLastMTD||0)+'</td><td class="num">'+lnk('earnings','mtd',d.commDistributedMonth||0)+'</td><td class="num">'+lnk('earnings','ytd',d.commDistributedYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">'+i18nUnclaimedEarning+'</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedLastMTD||0)+'</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedCommission||0)+'</td><td class="num">'+lnk('unclaimed','ytd',d.unclaimedCommission||0)+'</td></tr>';

    PD=d.topPerformers||PD;PL=true;switchPerf();

    var cdBase='/tl/dashboard/chart-drilldown?month='+selMonth+'&year='+selYear;
    var vendorCtype=localStorage.getItem('gl_chart_type_vendor')||'bar';
    var productCtype=localStorage.getItem('gl_chart_type_product')||'bar';
    var trendCtype=localStorage.getItem('gl_chart_type_trend')||'line';
    document.getElementById('vendor-viewall').href=cdBase+'&type=vendors&ctype='+vendorCtype;
    document.getElementById('product-viewall').href=cdBase+'&type=products&ctype='+productCtype;
    document.getElementById('trend-viewall').href=cdBase+'&type=trend&ctype='+trendCtype;
  }).catch(function(e){console.error('metrics error:',e);});
} /* end loadMetrics */

/* Init on page load */
initPicker();
loadMetrics();

// Chart type restored after metrics load (see loadMetrics)
@endverbatim
</script>
@endpush
