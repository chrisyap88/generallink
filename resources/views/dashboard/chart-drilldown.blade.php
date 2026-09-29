@extends('layouts.dashboard')

@section('title', $periodLabel)
@section('page-title', $periodLabel)

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.chart-box{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);flex:1;min-height:0;padding:10px;display:flex;flex-direction:column;}
.chart-wrap{position:relative;flex:1;min-height:0;}
.chart-wrap canvas{position:absolute;inset:0;width:100%!important;height:100%!important;}
.radio-row{display:flex;gap:8px;margin-bottom:6px;flex-shrink:0;align-items:center;}
.radio-row label{font-size:11px;color:#000;cursor:pointer;display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:5px;background:rgba(255,255,255,0.6);border:1px solid #B2EBF2;}
.radio-row label:hover{background:#E0F7FA;}
.radio-row input[type=radio]{accent-color:#1565C0;width:14px;height:14px;cursor:pointer;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:6px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:5px 18px;font-size:10px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:70px;display:inline-block;}
</style>
@endpush

@section('content')
@php
    $ct       = $ctype ?? 'bar';
    $isGL    = auth('agent')->check() && auth('agent')->user()->role === 'GROUP_LEADER';
    $backUrl  = $isGL ? route('gl.dashboard') : route('admin.dashboard');
    $isPage1  = $page === 1;
    $baseQ    = ['type' => $type, 'month' => $month, 'year' => $year, 'ctype' => $ct];
    $prevUrl  = $isPage1 ? $backUrl : url()->current().'?'.http_build_query(array_merge($baseQ, ['page' => $page - 1]));
    $nextUrl  = $hasMore ? url()->current().'?'.http_build_query(array_merge($baseQ, ['page' => $page + 1])) : null;
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:11px;flex-shrink:0;">
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ __('drilldown.back_to_dashboard_link') }}</a>
        <span style="color:#718096;">›</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;background:#E8F5E9;border-radius:8px;padding:4px 12px;font-size:10px;color:#1B5E20;">
            {{ __('drilldown.total_label') }} <strong>RM {{ number_format($total, 2) }}</strong>
        </span>
    </div>

    {{-- Chart --}}
    <div class="chart-box">
        <div class="radio-row">
            <label><input type="radio" name="ctype" value="bar" {{ $ct==='bar'?'checked':'' }} onchange="switchType('bar')"> {{ __('drilldown.chart_type_bar') }}</label>
            <label><input type="radio" name="ctype" value="line" {{ $ct==='line'?'checked':'' }} onchange="switchType('line')"> {{ __('drilldown.chart_type_line') }}</label>
            <label><input type="radio" name="ctype" value="pie" {{ $ct==='pie'?'checked':'' }} onchange="switchType('pie')"> {{ __('drilldown.chart_type_pie') }}</label>
            <span style="margin-left:auto;font-size:9px;color:#718096;font-style:italic;">{{ __('drilldown.point_to_graph_hint') }}</span>
        </div>
        <div class="chart-wrap">
            <canvas id="drillChart"></canvas>
        </div>
    </div>

    {{-- Bottom navigation --}}
    <div class="nav-bar">
        <a href="{{ $prevUrl }}" class="nav-btn">{{ __('network.prev') }}</a>
        <span style="font-size:10px;color:#374151;font-weight:600;">{{ __('drilldown.page_x_of_y_slash', ['current' => $page, 'last' => $lastPage]) }}</span>
        @if($nextUrl)
            <a href="{{ $nextUrl }}" class="nav-btn">{{ __('network.next') }}</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>

</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js"></script>
<script>
Chart.register(ChartDataLabels);

var rawLabels = @json($rawLabels);
var labels    = @json($labels);
var sales     = @json($sales);
var links     = @json($links);
var startRank = {{ $startRank }};
var chart     = null;
var colors    = ['#1565C0','#1976D2','#1E88E5','#2196F3','#42A5F5','#1B5E20','#2E7D32','#388E3C','#43A047','#4CAF50'];

function switchType(type) {
    if (chart) chart.destroy();

    // Save to localStorage using type-specific key
    var typeKeyMap = {vendors:'vendor', products:'product', trend:'trend'};
    var lsKey = 'gl_chart_type_' + (typeKeyMap['{{ $type }}'] || '{{ $type }}');
    try { localStorage.setItem(lsKey, type); } catch(e) {}

    // Update URL without reload
    var url = new URL(window.location.href);
    url.searchParams.set('ctype', type);
    window.history.replaceState({}, '', url);

    // Update Prev/Next links to include new ctype
    document.querySelectorAll('.nav-btn').forEach(function(btn) {
        var href = btn.getAttribute('href');
        if (href && href !== '#') {
            var u = new URL(href, window.location.origin);
            u.searchParams.set('ctype', type);
            btn.setAttribute('href', u.toString());
        }
    });

    var ctx = document.getElementById('drillChart').getContext('2d');

    if (type === 'pie') {
        chart = new Chart(ctx, {
            type: 'pie',
            data: { labels: rawLabels, datasets: [{ data: sales, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
            options: {
                responsive: true, maintainAspectRatio: false,
                onClick: function(evt, el) { if (el.length && links[el[0].index]) window.location.href = links[el[0].index]; },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) {
                        var t = c.dataset.data.reduce(function(a,b){return a+b;},0);
                        var pct = Math.round(c.raw/t*100);
                        return '#'+(startRank+c.dataIndex)+' '+c.label+': RM '+c.raw.toLocaleString('en-MY',{minimumFractionDigits:2})+' ('+pct+'%)';
                    }}},
                    datalabels: {
                        color: '#fff', font: { weight: 'bold', size: 10 }, textAlign: 'center',
                        formatter: function(v, ctx) {
                            var n = rawLabels[ctx.dataIndex];
                            return '#'+(startRank+ctx.dataIndex)+'\n'+(n.length>10?n.substring(0,9)+'...':n);
                        },
                        display: function(ctx) {
                            var t = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            return ctx.dataset.data[ctx.dataIndex]/t > 0.06;
                        }
                    }
                }
            }
        });
    } else {
        chart = new Chart(ctx, {
            type: type,
            data: { labels: labels, datasets: [{
                label: @json(__('drilldown.sales_amount_dataset_label_js')), data: sales,
                backgroundColor: type==='bar' ? colors : 'rgba(21,101,192,0.1)',
                borderColor: '#1565C0', borderWidth: type==='bar' ? 0 : 2,
                borderRadius: type==='bar' ? 6 : 0,
                barThickness: type==='bar' ? 40 : undefined,
                tension: 0.4, fill: type==='line',
                pointRadius: type==='line' ? 5 : 0,
                pointBackgroundColor: '#1565C0', pointBorderColor: '#fff', pointBorderWidth: 2,
            }]},
            options: {
                responsive: true, maintainAspectRatio: false,
                onClick: function(evt, el) { if (el.length && links[el[0].index]) window.location.href = links[el[0].index]; },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) {
                        return '#'+(startRank+c.dataIndex)+' '+rawLabels[c.dataIndex]+': RM '+c.raw.toLocaleString('en-MY',{minimumFractionDigits:2});
                    }}},
                    datalabels: {
                        color: '#000', font: { weight: 'bold', size: 10 },
                        anchor: 'end', align: 'top',
                        formatter: function(v, ctx) { return '#'+(startRank+ctx.dataIndex); }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 9, family: 'Poppins' }, color: '#000' } },
                    y: { ticks: { font: { size: 9, family: 'Poppins' }, color: '#000',
                        callback: function(v) { return v>=1000?(v/1000).toFixed(0)+'K':v; }
                    }, grid: { color: 'rgba(0,0,0,0.05)' } }
                }
            }
        });
    }
}

// Read from localStorage using type-specific key
var lsKey = 'gl_chart_type_' + '{{ $type }}';
var savedType = '';
try { savedType = localStorage.getItem(lsKey) || ''; } catch(e) {}
var initType = '{{ $ct }}' !== 'bar' ? '{{ $ct }}' : (savedType || 'bar');
// Update radio to match
document.querySelectorAll('input[name="ctype"]').forEach(function(r){ r.checked = r.value === initType; });
switchType(initType);
</script>
@endpush
@endsection
