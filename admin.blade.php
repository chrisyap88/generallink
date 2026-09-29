@extends('layouts.dashboard')
@section('page-title', 'KPI Dashboard')

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
.month-bar button{background:none;border:none;cursor:pointer;font-size:14px;color:#1565C0;padding:0 4px;line-height:1;}
.month-bar .period-label{font-size:11px;font-weight:700;color:#1565C0;min-width:110px;text-align:center;}
.month-bar select{border:1px solid #b2ebf2;background:#fff;font-family:'Poppins',sans-serif;font-size:10px;font-weight:600;color:#1565C0;cursor:pointer;outline:none;border-radius:5px;padding:2px 4px;}
.month-bar .as-of{font-size:9px;color:#64748B;}

.grid{
  display:grid;
  grid-template-columns:1fr 1fr 1fr;
  grid-template-rows:1fr 1.8fr 105px;
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

.ct{font-size:10px;font-weight:700;color:#000;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:3px;flex-shrink:0;text-align:center;}

.row-item{
  background:rgba(255,255,255,0.55);
  border:1px solid rgba(125,231,231,0.25);
  border-radius:7px;
  display:flex;align-items:center;justify-content:space-between;
  padding:6px 10px;flex:1;text-decoration:none;min-height:0;
}
.row-lbl{font-size:10px;color:#000;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap;}
.row-val{font-size:12px;color:#000;font-weight:600;flex-shrink:0;}

.radio-row{display:flex;gap:5px;margin-bottom:3px;flex-shrink:0;align-items:center;flex-wrap:nowrap;white-space:nowrap;}
.radio-row label{font-size:8px;color:#000;cursor:pointer;display:flex;align-items:center;gap:2px;flex-shrink:0;}
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
.pt th{padding:2px 6px;font-size:8px;color:#000;text-transform:uppercase;background:rgba(255,255,255,0.5);border-bottom:1px solid rgba(125,231,231,0.3);}
.pt th.num,.pt td.num{text-align:center;}
.pt th.kpi,.pt td.kpi{text-align:left;width:25%;}
.pt td{padding:3px 6px;font-size:9px;color:#000;border-bottom:1px solid rgba(125,231,231,0.1);}
.pt tr:last-child td{border:none;}

.sk{background:linear-gradient(90deg,rgba(125,231,231,0.2) 25%,rgba(125,231,231,0.4) 50%,rgba(125,231,231,0.2) 75%);background-size:200% 100%;animation:sh 1.5s infinite;border-radius:6px;}
@keyframes sh{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* Performer cards — flex:1 so all 3 share space equally */
.perf-card{background:rgba(255,255,255,0.55);border:1px solid rgba(125,231,231,0.25);border-radius:7px;display:flex;align-items:center;justify-content:space-between;padding:3px 7px;gap:5px;flex:1;min-height:0;}
.perf-name{font-size:9px;color:#000;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.perf-sub{font-size:8px;color:#555;}
.perf-amt{font-size:10px;color:#000;flex-shrink:0;white-space:nowrap;}
</style>
@endpush

@section('content')
<div class="db">

{{-- Month/Year Picker --}}
<div class="month-bar">
  <span class="as-of">📅 Viewing:</span>
  <button onclick="changeMonth(-1)" title="Previous month">&#8592;</button>
  <span class="period-label" id="period-label"></span>
  <button onclick="changeMonth(1)" title="Next month">&#8594;</button>
  <span style="margin-left:8px;font-size:9px;color:#64748B;">|</span>
  <select id="month-select" onchange="onMonthSelect()" style="margin-left:4px;">
    <option value="1">January</option><option value="2">February</option>
    <option value="3">March</option><option value="4">April</option>
    <option value="5">May</option><option value="6">June</option>
    <option value="7">July</option><option value="8">August</option>
    <option value="9">September</option><option value="10">October</option>
    <option value="11">November</option><option value="12">December</option>
  </select>
  <select id="year-select" onchange="onMonthSelect()">
    @for($y = 2024; $y <= date('Y'); $y++)
      <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
    @endfor
  </select>
  <span class="as-of" id="mtd-note" style="margin-left:8px;"></span>
</div>

<div class="grid">

  {{-- BOX 1: Top Performers --}}
  <div class="card" style="border-top:3px solid #7DE7E7;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>🏆 Top 3 Performers</span><a id="perf-viewall" href="#" style="font-size:8px;color:#1565C0;text-decoration:none;font-weight:600;">View All →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="perf-type" value="sales" checked onchange="switchPerf()"> Sales</label>
      <label><input type="radio" name="perf-type" value="earnings" onchange="switchPerf()"> Earnings</label>
      <span style="color:#ccc;font-size:8px;">|</span>
      <label><input type="radio" name="perf-role" value="gl" checked onchange="switchPerf()"> GL</label>
      <label><input type="radio" name="perf-role" value="tl" onchange="switchPerf()"> TL</label>
      <label><input type="radio" name="perf-role" value="intro" onchange="switchPerf()"> Intro</label>
    </div>
    <div id="perf-list" style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
      <div class="sk" style="flex:1;"></div>
    </div>
  </div>

  {{-- BOX 2: Network Overview --}}
  <div class="card" style="border-top:3px solid #6EE7F9;">
    <div class="ct">Network Overview</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="{{ route('admin.network') }}" class="row-item">
        <div class="row-lbl">Group Leaders</div>
        <div class="row-val" id="b2-gl"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.agents.index') }}" class="row-item">
        <div class="row-lbl">Team Leaders</div>
        <div class="row-val" id="b2-tl"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.agents.index') }}" class="row-item">
        <div class="row-lbl">Introducers</div>
        <div class="row-val" id="b2-intro"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <a href="{{ route('admin.agents.pending') }}" class="row-item">
        <div class="row-lbl">Pending Assignment</div>
        <div class="row-val" id="b2-pend"><div class="sk" style="width:24px;height:12px;"></div></div>
      </a>
      <div class="row-item">
        <div class="row-lbl">Total Agents</div>
        <div class="row-val" id="b2-total"><div class="sk" style="width:24px;height:12px;"></div></div>
      </div>
    </div>
  </div>

  {{-- BOX 6: Pending Actions --}}
  <div class="card" style="border-top:3px solid #EF4444;">
    <div class="ct">⚠️ Pending Actions</div>
    <div style="display:flex;flex-direction:column;gap:3px;flex:1;min-height:0;">
      <a href="#" id="b6-unclaim-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">Unclaimed Earning (RM)</div><div class="row-val" id="b6-unclaim"><div class="sk" style="width:40px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">Renewal ≤ 30 Days</div><div class="row-val" id="b6-r30"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-r30p-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">Renewal &gt; 30 Days</div><div class="row-val" id="b6-r30p"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-redeem-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">Redemption</div><div class="row-val" id="b6-redeem"><div class="sk" style="width:24px;height:12px;"></div></div></a>
      <a href="#" id="b6-pts-row" class="row-item" style="text-decoration:none;"><div class="row-lbl">Points Purchase Approval</div><div class="row-val" id="b6-pts"><div class="sk" style="width:24px;height:12px;"></div></div></a>
    </div>
  </div>

  {{-- BOX 4: Sales by Vendor --}}
  <div class="card" style="border-top:3px solid #5EEAD4;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>Top 3 Vendors</span><a id="vendor-viewall" href="#" style="font-size:8px;color:#1565C0;text-decoration:none;font-weight:600;">View All →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="vendor" value="bar" checked onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> Bar</label>
      <label><input type="radio" name="vendor" value="line" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> Line</label>
      <label><input type="radio" name="vendor" value="pie" onchange="switchVendor(this.value);try{localStorage.setItem('gl_chart_type_vendor',this.value);}catch(e){}"> Pie</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="vendor-sk" style="position:absolute;inset:0;"></div>
      <canvas id="vendorChart"></canvas>
    </div>
    <div style="font-size:8px;color:#718096;font-style:italic;text-align:center;margin-top:2px;">Point to graph to view vendor name</div>
  </div>

  {{-- BOX 5: Sales by Product --}}
  <div class="card" style="border-top:3px solid #A7F3F0;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>Top 3 Products</span><a id="product-viewall" href="#" style="font-size:8px;color:#1565C0;text-decoration:none;font-weight:600;">View All →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="product" value="bar" checked onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> Bar</label>
      <label><input type="radio" name="product" value="line" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> Line</label>
      <label><input type="radio" name="product" value="pie" onchange="switchProduct(this.value);try{localStorage.setItem('gl_chart_type_product',this.value);}catch(e){}"> Pie</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="product-sk" style="position:absolute;inset:0;"></div>
      <canvas id="productChart"></canvas>
    </div>
    <div style="font-size:8px;color:#718096;font-style:italic;text-align:center;margin-top:2px;">Point to graph to view product type</div>
  </div>

  {{-- BOX 3: Sales & Earnings Trend --}}
  <div class="card" style="border-top:3px solid #10B981;">
    <div class="ct" style="display:flex;justify-content:space-between;align-items:center;"><span>Top 3 Months</span><a id="trend-viewall" href="#" style="font-size:8px;color:#1565C0;text-decoration:none;font-weight:600;">View All →</a></div>
    <div class="radio-row">
      <label><input type="radio" name="trend" value="line" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> Line</label>
      <label><input type="radio" name="trend" value="bar" checked onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> Bar</label>
      <label><input type="radio" name="trend" value="pie" onchange="switchChart(this.value);try{localStorage.setItem('gl_chart_type_trend',this.value);}catch(e){}"> Pie</label>
    </div>
    <div class="chart-wrap">
      <div class="sk" id="trend-sk" style="position:absolute;inset:0;"></div>
      <canvas id="trendChart"></canvas>
    </div>
    <div style="font-size:8px;color:#718096;font-style:italic;text-align:center;margin-top:2px;">Point to graph to view monthly sales &amp; earnings detail</div>
  </div>

  {{-- PERFORMANCE TABLE --}}
  <div class="pc">
    <div style="font-size:9px;color:#000;text-align:center;margin-bottom:2px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">📊 Performance Overview</div>
    <table class="pt">
      <thead>
        <tr>
          <th class="kpi" style="width:25%;">KPI</th>
          <th class="num" style="width:25%;">Last MTD</th>
          <th class="num" style="width:25%;">MTD</th>
          <th class="num" style="width:25%;">YTD</th>
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
@verbatim
var fRM=function(v){return 'RM '+parseFloat(v||0).toLocaleString('en-MY',{minimumFractionDigits:2,maximumFractionDigits:2});};
var fN=function(v){return Number(v||0).toLocaleString();};
var medals=['🥇','🥈','🥉'];

/* Month/Year picker state */
var selMonth = new Date().getMonth()+1;
var selYear  = new Date().getFullYear();
var monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

function initPicker(){
  document.getElementById('month-select').value = selMonth;
  document.getElementById('year-select').value  = selYear;
  updatePeriodLabel();
}

function updatePeriodLabel(){
  var now = new Date();
  var isCurrent = selMonth===(now.getMonth()+1) && selYear===now.getFullYear();
  document.getElementById('period-label').textContent = monthNames[selMonth]+' '+selYear;
  document.getElementById('mtd-note').textContent = isCurrent ? '(Current Month)' : '';
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
    var pieColors=['#1565C0','#1B5E20','#D97706','#7C3AED','#DB2777','#0891B2','#059669','#DC2626'];
    var isTrend=(cid==='trendChart');
    var pieLabels=isTrend?['Sales','Earnings']:lb;
    var pieData=isTrend
      ?[sa.reduce(function(a,b){return a+b;},0),ea.reduce(function(a,b){return a+b;},0)]
      :sa;
    var pieBg=isTrend?['#1565C0','#1B5E20']:lb.map(function(_,i){return pieColors[i%pieColors.length];});
    chart=new Chart(ctx,{type:'pie',
      data:{labels:pieLabels,datasets:[{data:pieData,backgroundColor:pieBg,borderWidth:2,borderColor:'#fff'}]},
      options:{responsive:true,maintainAspectRatio:false,
        plugins:{legend:{display:true,labels:{font:{size:7,family:'Poppins'},boxWidth:7,padding:3,color:'#000'}},
          tooltip:{callbacks:{label:function(c){
            var tot=c.dataset.data.reduce(function(a,b){return a+b;},0);
            var pct=tot>0?Math.round(c.raw/tot*100):0;
            return c.label+': RM '+c.raw.toLocaleString()+' ('+pct+'%)';
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

var PD={gl:{sales:[],earnings:[]},tl:{sales:[],earnings:[]},intro:{sales:[],earnings:[]}};
var PL=false;

function perfDrillUrl(role,d){
  var mp='?from=dashboard&month='+selMonth+'&year='+selYear;
  if(role==='gl')    return '/admin/network/'+d.agent_id+mp;
  if(role==='tl')    return '/admin/network/'+(d.gl_id||'0')+'/'+d.agent_id+mp;
  if(role==='intro') return '/admin/network/'+(d.gl_id||'0')+'/'+(d.tl_id||'0')+'/'+d.agent_id+mp;
  return '#';
}
function switchPerf(){
  var vaBase='/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear;
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
    html+='<a href="'+url+'" style="text-decoration:none;" class="perf-card">'
      +'<div style="display:flex;align-items:center;gap:4px;min-width:0;flex:1;">'
      +'<span style="font-size:13px;flex-shrink:0;">'+(medals[i]||'')+'</span>'
      +'<div style="min-width:0;overflow:hidden;">'
      +'<div class="perf-name">'+d.full_name+'</div>'
      +'<div class="perf-sub">'+d.agent_code+'</div>'
      +'</div></div>'
      +'<div class="perf-amt">'+fRM(d.total)+'</div>'
      +'</a>';
  }
  document.getElementById('perf-list').innerHTML=html||'<div style="text-align:center;color:#64748B;font-size:9px;padding:8px;">No data</div>';
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
  ['b2-gl','b2-tl','b2-intro','b2-pend','b2-total',
   'b6-unclaim','b6-r30','b6-r30p','b6-redeem','b6-pts'].forEach(function(id){
    var el=document.getElementById(id);
    if(el) el.innerHTML='<div class="sk" style="width:24px;height:12px;"></div>';
  });
  PL=false;
  document.getElementById('perf-list').innerHTML='<div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div><div class="sk" style="flex:1;"></div>';

  fetch(metricsUrl+'?month='+selMonth+'&year='+selYear+'&_='+Date.now())
  .then(function(r){return r.json();})
  .then(function(d){
    document.getElementById('b2-gl').textContent=fN(d.totalGL);
    document.getElementById('b2-tl').textContent=fN(d.totalTL);
    document.getElementById('b2-intro').textContent=fN(d.totalIntroducers);
    document.getElementById('b2-pend').textContent=fN(d.pendingAssignment);
    document.getElementById('b2-total').textContent=fN((d.totalGL||0)+(d.totalTL||0)+(d.totalIntroducers||0));

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

    var ddBase2='/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear;
    document.getElementById('b6-unclaim-row').href=ddBase2+'&type=unclaimed&period=mtd';
    document.getElementById('b6-unclaim').innerHTML='<span style="color:#E53E3E;font-weight:700;">'+fRM(d.unclaimedCommission||0)+'</span>';
    document.getElementById('b6-r30-row').href='/admin/dashboard/drilldown?type=renewal_lte30&month='+selMonth+'&year='+selYear;
    document.getElementById('b6-r30').textContent=fN(d.renewal_lte30||0);
    document.getElementById('b6-r30p-row').href='/admin/dashboard/drilldown?type=renewal_gt30&month='+selMonth+'&year='+selYear;
    document.getElementById('b6-r30p').textContent=fN(d.renewal_gt30||0);
    document.getElementById('b6-redeem-row').href='/admin/transactions?type=redemption';
    document.getElementById('b6-redeem').textContent=fN(d.redemption_pending||0);
    document.getElementById('b6-pts-row').href='/admin/points';
    document.getElementById('b6-pts').textContent=fN(d.pointsPurchasePending||0);

    var ddBase='/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear;
    var lnk=function(type,period,val){return '<a href="'+ddBase+'&type='+type+'&period='+period+'" style="color:#1565C0;text-decoration:none;font-weight:600;">'+fRM(val)+'</a>';};
    document.getElementById('perf-tbody').innerHTML=
      '<tr><td class="kpi">Sales Amount</td><td class="num">'+lnk('sales','last_mtd',d.salesLastMTD||0)+'</td><td class="num">'+lnk('sales','mtd',d.totalPremiumMonth||0)+'</td><td class="num">'+lnk('sales','ytd',d.totalPremiumYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">Earning Income</td><td class="num">'+lnk('earnings','last_mtd',d.earnLastMTD||0)+'</td><td class="num">'+lnk('earnings','mtd',d.commDistributedMonth||0)+'</td><td class="num">'+lnk('earnings','ytd',d.commDistributedYTD||0)+'</td></tr>'+
      '<tr><td class="kpi">Unclaimed Earning</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedLastMTD||0)+'</td><td class="num">'+lnk('unclaimed','mtd',d.unclaimedCommission||0)+'</td><td class="num">'+lnk('unclaimed','ytd',d.unclaimedCommission||0)+'</td></tr>';

    PD=d.topPerformers||PD;PL=true;switchPerf();



    // Set View All URLs
    var vaBase='/admin/dashboard/drilldown?month='+selMonth+'&year='+selYear;
    var perfRole=document.querySelector('input[name="perf-role"]:checked').value;
    document.getElementById('perf-viewall').href=vaBase+'&type=performers&role='+perfRole;
    var cdBase='/admin/dashboard/chart-drilldown?month='+selMonth+'&year='+selYear;
    document.getElementById('vendor-viewall').href=cdBase+'&type=vendors';
    document.getElementById('product-viewall').href=cdBase+'&type=products';
    document.getElementById('trend-viewall').href=cdBase+'&type=trend';
  }).catch(function(e){console.error('metrics error:',e);});
} /* end loadMetrics */

/* Init on page load */
initPicker();
loadMetrics();

// Chart type restored after metrics load (see loadMetrics)
@endverbatim
</script>
@endpush
