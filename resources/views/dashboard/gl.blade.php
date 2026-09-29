@extends('layouts.dashboard')

@section('page-title')
{{ $group->group_name ?? 'My Group' }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->full_name }} &middot; {{ $agent->agent_code }} &middot; {{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</span>
@endsection

@push('styles')
<style>
.gld-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.gld-page{display:none;flex:1;flex-direction:column;gap:6px;min-height:0;}
.gld-page.active{display:flex;}
.gld-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.gld-dot{width:7px;height:7px;border-radius:50%;background:#d1d5db;cursor:pointer;}
.gld-dot.on{background:#1565C0;}
.gld-chart-btn{background:none;border:none;padding:4px 8px;cursor:pointer;color:#718096;font-size:12px;transition:all .15s;}
.gld-chart-btn:hover,.gld-active-chart{background:#EBF5FB;color:#0D5A8E;}
.skeleton{background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%);background-size:200% 100%;animation:shimmer 1.5s infinite;border-radius:4px;min-height:18px;}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
</style>
@endpush

@section('content')
<div class="gld-wrap">

    {{-- Top bar --}}
    <div style="display:flex;justify-content:flex-end;align-items:center;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="display:flex;gap:4px;">
                <div class="gld-dot on" onclick="goGldPage(1)"></div>
                <div class="gld-dot" onclick="goGldPage(2)"></div>
                <div class="gld-dot" onclick="goGldPage(3)"></div>
            </div>
            <span id="gld-pg-label" style="font-size:10px;color:#6b7280;">1 / 3</span>
        </div>
    </div>

    {{-- ═══════════════ PAGE 1 — METRICS ═══════════════ --}}
    <div id="gld-pg1" class="gld-page active">

        {{-- Row 1: Network --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;">
            <a href="{{ route('gl.network') }}" class="gld-card" style="border-left:3px solid #1B9AE4;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ \App\Services\RoleLabelService::plural('TEAM_LEADER') }}</div>
                <div style="font-size:24px;font-weight:800;color:#1B9AE4;" id="m-tl"><div class="skeleton"></div></div>
                <div style="font-size:11px;color:#9ca3af;">In my group &#8594;</div>
            </a>
            <a href="{{ route('gl.network') }}" class="gld-card" style="border-left:3px solid #7C3AED;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ \App\Services\RoleLabelService::plural('INTRODUCER') }}</div>
                <div style="font-size:24px;font-weight:800;color:#7C3AED;" id="m-intro"><div class="skeleton"></div></div>
                <div style="font-size:11px;color:#9ca3af;">In my group &#8594;</div>
            </a>
            <a href="{{ route('gl.network') }}" class="gld-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Active Members</div>
                <div style="font-size:24px;font-weight:800;color:#38A169;" id="m-active"><div class="skeleton"></div></div>
                <div style="font-size:11px;color:#9ca3af;">Currently active &#8594;</div>
            </a>
            <a href="{{ route('gl.network') }}" class="gld-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Inactive Members</div>
                <div style="font-size:24px;font-weight:800;color:#D97706;" id="m-inactive"><div class="skeleton"></div></div>
                <div style="font-size:11px;color:#9ca3af;">Not currently active &#8594;</div>
            </a>
        </div>

        {{-- Row 2: Performance --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;">
            <a href="{{ route('gl.transactions') }}" class="gld-card" style="border-left:3px solid #0D5A8E;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Sales MTD</div>
                <div style="font-size:18px;font-weight:800;color:#0D5A8E;" id="m-premium-mtd"><div class="skeleton"></div></div>
                <div style="font-size:10px;color:#38A169;font-weight:600;" id="m-comm-mtd-sub">Commission: <span id="m-comm-mtd-inline">&hellip;</span></div>
            </a>
            <a href="{{ route('gl.transactions') }}" class="gld-card" style="border-left:3px solid #1B9AE4;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Sales YTD</div>
                <div style="font-size:18px;font-weight:800;color:#1B9AE4;" id="m-premium-ytd"><div class="skeleton"></div></div>
                <div style="font-size:10px;color:#9ca3af;">My group this year &#8594;</div>
            </a>
            <a href="{{ route('gl.commissions.index') }}" class="gld-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">My Commission MTD</div>
                <div style="font-size:20px;font-weight:800;color:#38A169;" id="m-comm-mtd"><div class="skeleton"></div></div>
                <div style="font-size:11px;color:#9ca3af;">My earnings this month &#8594;</div>
            </a>
            <a href="{{ route('gl.rewards') }}" class="gld-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Reward Points</div>
                <div style="font-size:20px;font-weight:800;color:#D97706;">{{ number_format($rewardPoints ?? 0,2) }} pts</div>
                <div style="font-size:11px;color:#9ca3af;">Available balance &#8594;</div>
            </a>
        </div>
    </div>

    {{-- ═══════════════ PAGE 2 — CHART + TOP TLs ═══════════════ --}}
    <div id="gld-pg2" class="gld-page">
        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:8px;flex:1;min-height:0;">

            {{-- Chart --}}
            <div class="gld-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <div style="font-size:11px;font-weight:700;color:#374151;">&#128202; Group Sales &mdash; Last 6 Months</div>
                    <div style="display:flex;background:#F7FAFC;border:1px solid #E2E8F0;border-radius:6px;overflow:hidden;">
                        <button onclick="setGldType('bar')" id="gld-btn-bar" class="gld-chart-btn gld-active-chart" title="Bar"><i class="ti ti-chart-bar"></i></button>
                        <button onclick="setGldType('line')" id="gld-btn-line" class="gld-chart-btn" title="Line"><i class="ti ti-chart-line"></i></button>
                        <button onclick="setGldType('pie')" id="gld-btn-pie" class="gld-chart-btn" title="Pie"><i class="ti ti-chart-pie"></i></button>
                    </div>
                </div>
                <div style="position:relative;flex:1;min-height:0;">
                    <div class="skeleton" style="height:100%;border-radius:8px;" id="gld-chart-skeleton"></div>
                    <canvas id="gldChart" style="display:none;"></canvas>
                </div>
            </div>

            {{-- Top TLs --}}
            <div class="gld-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">&#127942; Top {{ \App\Services\RoleLabelService::plural('TEAM_LEADER') }} This Month</div>
                <div id="gld-top-tls" style="overflow:auto;flex:1;min-height:0;">
                    <div class="skeleton" style="height:24px;margin-bottom:6px;border-radius:5px;"></div>
                    <div class="skeleton" style="height:24px;margin-bottom:6px;border-radius:5px;"></div>
                    <div class="skeleton" style="height:24px;border-radius:5px;"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAGE 3 — RECENT TRANSACTIONS ═══════════════ --}}
    <div id="gld-pg3" class="gld-page">
        <div class="gld-card" style="flex:1;overflow:hidden;padding:8px 12px 50px 12px;display:flex;flex-direction:column;">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;">
                &#128203; Recent Transactions &mdash; My Group
            </div>
            @if($recentTransactions->isEmpty())
                <div style="text-align:center;color:#A0AEC0;padding:20px;font-size:12px;">No transactions yet</div>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead><tr style="background:#F7FAFC;">
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Transaction #</th>
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Agent</th>
                        <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Sales Amount (RM)</th>
                        <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Date</th>
                        <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach($recentTransactions as $tx)
                        <tr style="border-bottom:1px solid #F7FAFC;">
                            <td style="padding:6px 8px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                            <td style="padding:6px 8px;">{{ $tx->agent_name }}</td>
                            <td style="padding:6px 8px;text-align:right;font-weight:700;">RM {{ number_format($tx->premium_amount,2) }}</td>
                            <td style="padding:6px 8px;text-align:center;color:#718096;font-size:11px;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}</td>
                            <td style="padding:6px 8px;text-align:center;">
                                <span style="padding:2px 8px;border-radius:20px;font-size:10px;font-weight:600;
                                    background:{{ $tx->status==='ACTIVE'?'#C8E6C9':($tx->status==='PENDING'?'#FFF9C4':'#FFCDD2') }};
                                    color:{{ $tx->status==='ACTIVE'?'#1B5E20':($tx->status==='PENDING'?'#F57F17':'#B71C1C') }};">
                                    {{ $tx->status }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($recentTransactions->hasPages())
                <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
                    <span style="color:#9ca3af;">Showing {{ $recentTransactions->firstItem() }}&ndash;{{ $recentTransactions->lastItem() }} of {{ $recentTransactions->total() }} records</span>
                    <div style="font-size:10px;">{{ $recentTransactions->onEachSide(1)->links() }}</div>
                </div>
                @endif
            @endif
        </div>
    </div>

    {{-- Prev / Next --}}
    <button id="gld-btn-prev" onclick="changeGldPage(-1)" style="display:none;position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">&#8592; Prev</button>
    <button id="gld-btn-next" onclick="changeGldPage(1)" style="position:fixed;bottom:16px;right:16px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">Next &#8594;</button>

</div>

@push('scripts')
<script>
const metricsData = @json($metrics);
let gldChart = null;
let gldCurrentType = 'bar';
const fmt = v => 'RM ' + parseFloat(v).toLocaleString('en-MY',{minimumFractionDigits:2});

// ───── Page navigation ─────
var gldCur = 1;
function goGldPage(n) {
    document.querySelectorAll('.gld-page').forEach(el => { el.classList.remove('active'); el.style.display='none'; });
    var t = document.getElementById('gld-pg'+n);
    t.classList.add('active'); t.style.display='flex';
    document.querySelectorAll('.gld-dot').forEach((d,i) => d.classList.toggle('on', i===n-1));
    var label = document.getElementById('gld-pg-label');
    if (label) label.textContent = n+' / 3';
    document.getElementById('gld-btn-prev').style.display = n>1?'inline-block':'none';
    document.getElementById('gld-btn-next').style.display = n<3?'inline-block':'none';
    gldCur = n;
    if (n===2 && gldChart) setTimeout(()=>gldChart.resize(), 50);
}
function changeGldPage(dir) { var n = gldCur + dir; if(n>=1 && n<=3) goGldPage(n); }

// If reloaded via pagination (URL has txpage param), stay on page 3
var gldInitPage = (window.location.search && window.location.search.indexOf('txpage') !== -1) ? 3 : 1;
goGldPage(gldInitPage);

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('m-tl').textContent = metricsData.counts.total_tl ?? 0;
    document.getElementById('m-intro').textContent = metricsData.counts.total_intro ?? 0;
    document.getElementById('m-active').textContent = metricsData.counts.total_active ?? 0;
    document.getElementById('m-inactive').textContent = metricsData.counts.total_inactive ?? 0;
    document.getElementById('m-premium-mtd').textContent = fmt(metricsData.premium.premium_mtd ?? 0);
    document.getElementById('m-premium-ytd').textContent = fmt(metricsData.premium.premium_ytd ?? 0);
    document.getElementById('m-comm-mtd').textContent = fmt(metricsData.commission.commission_mtd ?? 0);
    document.getElementById('m-comm-mtd-inline').textContent = fmt(metricsData.commission.commission_mtd ?? 0);

    document.getElementById('gld-chart-skeleton').style.display = 'none';
    document.getElementById('gldChart').style.display = 'block';
    buildGldChart();
    renderTopTLs(metricsData.topTLs);
});

function buildGldChart() {
    if (gldChart) gldChart.destroy();
    const ctx = document.getElementById('gldChart').getContext('2d');
    const isPie = gldCurrentType === 'pie';

    gldChart = new Chart(ctx, {
        type: gldCurrentType,
        data: isPie ? {
            labels: metricsData.chartData.labels,
            datasets: [{ data: metricsData.chartData.data, backgroundColor: ['#1B9AE4','#0D5A8E','#38A169','#7C3AED','#D97706','#E53E3E'], borderWidth:2 }]
        } : {
            labels: metricsData.chartData.labels,
            datasets: [{ label: 'Sales (RM)', data: metricsData.chartData.data, backgroundColor: '#1B9AE4', borderColor: '#0D5A8E', borderWidth: 2, borderRadius: 4, tension: 0.4, fill:false }]
        },
        options: { responsive: true, maintainAspectRatio: false,
            layout: { padding: 4 },
            plugins: {
                legend: { display: isPie, position: window.innerWidth < 900 ? 'bottom' : 'right', labels:{ font:{ size:9 }, boxWidth:10, padding:6 } },
                tooltip: { callbacks: { label: c => ' ' + fmt(isPie ? c.parsed : c.parsed.y) } }
            },
            scales: isPie ? {} : { x: { grid: { display: false }, ticks:{ font:{ size:9 } } }, y: { ticks: { font:{ size:9 }, callback: v => 'RM '+v.toLocaleString() } } }
        }
    });
}

function setGldType(type) {
    gldCurrentType = type;
    ['bar','line','pie'].forEach(t => document.getElementById('gld-btn-'+t).classList.remove('gld-active-chart'));
    document.getElementById('gld-btn-'+type).classList.add('gld-active-chart');
    buildGldChart();
}

function renderTopTLs(tls) {
    const medals = ['🥇','🥈','🥉'];
    const container = document.getElementById('gld-top-tls');
    if (!tls || tls.length === 0) {
        container.innerHTML = '<div style="text-align:center;color:#A0AEC0;padding:16px;font-size:12px;">No {{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }} data yet</div>';
        return;
    }
    let html = '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
    tls.forEach((tl,i) => {
        html += `<tr style="border-bottom:1px solid #F7FAFC;cursor:pointer;" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''" onclick="window.location='{{ route('gl.network') }}/${tl.agent_id}'">
            <td style="padding:5px 6px;">${medals[i]||'#'+(i+1)}</td>
            <td style="padding:5px 6px;font-weight:600;">${tl.full_name} &#8594;</td>
            <td style="padding:5px 6px;text-align:right;font-weight:700;color:#0D5A8E;">${fmt(tl.total_premium)} <span style="color:#9ca3af;font-weight:400;">sales</span></td>
        </tr>`;
    });
    html += '</table>';
    container.innerHTML = html;
}
</script>
@endpush
@endsection
