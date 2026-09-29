@extends('layouts.dashboard')
@section('title', 'My Dashboard')

@section('page-title')
{{ $agent->full_name }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->agent_code }} @if($group) &middot; {{ $group->group_name }} @endif @if($myTL) &middot; Under TL {{ $myTL->full_name }} @endif &middot; {{ \App\Services\RoleLabelService::label('INTRODUCER') }}</span>
@endsection

@push('styles')
<style>
.intd-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.intd-page{display:none;flex:1;flex-direction:column;gap:6px;min-height:0;}
.intd-page.active{display:flex;}
.intd-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.intd-dot{width:7px;height:7px;border-radius:50%;background:#d1d5db;cursor:pointer;}
.intd-dot.on{background:#1565C0;}
</style>
@endpush

@section('content')
<div class="intd-wrap">

    {{-- Top bar --}}
    <div style="display:flex;justify-content:flex-end;align-items:center;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="display:flex;gap:4px;">
                <div class="intd-dot on" onclick="goIntdPage(1)"></div>
                <div class="intd-dot" onclick="goIntdPage(2)"></div>
                <div class="intd-dot" onclick="goIntdPage(3)"></div>
            </div>
            <span id="intd-pg-label" style="font-size:10px;color:#6b7280;">1 / 3</span>
        </div>
    </div>

    {{-- ═══════════════ PAGE 1 — METRICS ═══════════════ --}}
    <div id="intd-pg1" class="intd-page active">

        {{-- Row 1: Premium --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;">
            <div class="intd-card" style="border-left:3px solid #0D5A8E;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Sales MTD</div>
                <div style="font-size:18px;font-weight:800;color:#0D5A8E;">RM {{ number_format($metrics['premium']->premium_mtd ?? 0,2) }}</div>
                <div style="font-size:10px;color:#38A169;font-weight:600;">Commission: RM {{ number_format($metrics['commission']->commission_mtd ?? 0,2) }}</div>
            </div>
            <div class="intd-card" style="border-left:3px solid #1B9AE4;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Sales YTD</div>
                <div style="font-size:18px;font-weight:800;color:#1B9AE4;">RM {{ number_format($metrics['premium']->premium_ytd ?? 0,2) }}</div>
                <div style="font-size:11px;color:#9ca3af;">This year</div>
            </div>
            <div class="intd-card" style="border-left:3px solid #718096;display:flex;flex-direction:column;justify-content:center;cursor:pointer;" onclick="goIntdPage(3, true)">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Total Transactions</div>
                <div style="font-size:24px;font-weight:800;color:#718096;">{{ number_format($metrics['premium']->transactions_total ?? 0) }}</div>
                <div style="font-size:11px;color:#9ca3af;">All time &#8594;</div>
            </div>
            <div class="intd-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;cursor:pointer;" onclick="goIntdPage(3, true)">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Transactions MTD</div>
                <div style="font-size:24px;font-weight:800;color:#38A169;">{{ number_format($metrics['premium']->transactions_mtd ?? 0) }}</div>
                <div style="font-size:11px;color:#9ca3af;">This month &#8594;</div>
            </div>
        </div>

        {{-- Row 2: Commission + Upline --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1.4fr;gap:8px;flex:1;">
            <div class="intd-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;cursor:pointer;" onclick="goIntdPage(3, true)">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Commission MTD</div>
                <div style="font-size:20px;font-weight:800;color:#38A169;">RM {{ number_format($metrics['commission']->commission_mtd ?? 0,2) }}</div>
                <div style="font-size:11px;color:#9ca3af;white-space:nowrap;">This month &#8594;</div>
            </div>
            <a href="{{ route('introducer.recruits') }}" class="intd-card" style="border-left:3px solid #7C3AED;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">My Recruits</div>
                <div style="font-size:20px;font-weight:800;color:#7C3AED;">{{ number_format($recruitCount ?? 0) }}</div>
                <div style="font-size:11px;color:#9ca3af;">View my downline &#8594;</div>
            </a>
            <a href="{{ route('introducer.rewards') }}" class="intd-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Reward Points</div>
                <div style="font-size:20px;font-weight:800;color:#D97706;">{{ number_format($rewardPoints,2) }} pts</div>
                <div style="font-size:11px;color:#9ca3af;">Available balance &#8594;</div>
            </a>
            <div class="intd-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:4px;">&#127979; My Upline Chain</div>
                <div style="font-size:11px;overflow:auto;flex:1;min-height:0;">
                    @if($myTL)
                    <div style="padding:3px 0;border-bottom:1px solid #F0F7FF;">
                        <span style="color:#718096;font-weight:600;">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }}:</span>
                        <span style="font-weight:600;color:#0284C7;">{{ $myTL->full_name }}</span>
                        <span style="color:#9ca3af;">({{ $myTL->agent_code }})</span>
                    </div>
                    @endif
                    @if($myGL)
                    <div style="padding:3px 0;border-bottom:1px solid #F0F7FF;">
                        <span style="color:#718096;font-weight:600;">{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER') }}:</span>
                        <span style="font-weight:600;color:#38A169;">{{ $myGL->full_name }}</span>
                        <span style="color:#9ca3af;">({{ $myGL->agent_code }})</span>
                    </div>
                    @endif
                    @if($group)
                    <div style="padding:3px 0;">
                        <span style="color:#718096;font-weight:600;">Group:</span>
                        <span style="font-weight:600;color:#0D5A8E;">{{ $group->group_name }}</span>
                        <span style="color:#9ca3af;">({{ $group->group_code }})</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAGE 2 — CHART + VENDOR OFFERS ═══════════════ --}}
    <div id="intd-pg2" class="intd-page">
        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:8px;flex:1;min-height:0;">

            {{-- Chart --}}
            <div class="intd-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                    <div style="font-size:11px;font-weight:700;color:#374151;">&#128202; My Sales &mdash; Last 6 Months</div>
                    <div style="display:flex;gap:3px;">
                        <button onclick="switchIntdChart('line')" id="intd-btn-line" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #7C3AED;background:#7C3AED;color:#fff;cursor:pointer;">Line</button>
                        <button onclick="switchIntdChart('bar')" id="intd-btn-bar" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #d1d5db;background:#fff;color:#374151;cursor:pointer;">Bar</button>
                        <button onclick="switchIntdChart('pie')" id="intd-btn-pie" style="padding:2px 8px;font-size:9px;font-weight:600;border-radius:4px;border:1px solid #d1d5db;background:#fff;color:#374151;cursor:pointer;">Pie</button>
                    </div>
                </div>
                <div style="position:relative;flex:1;min-height:0;">
                    <canvas id="intdChart"></canvas>
                </div>
            </div>

            {{-- Vendor Offers --}}
            <div class="intd-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">&#127970; Active Vendors</div>
                <div style="overflow:auto;flex:1;min-height:0;">
                    @if($vendorOffers->isEmpty())
                        <div style="text-align:center;color:#A0AEC0;padding:16px;font-size:12px;">No active vendors</div>
                    @else
                        @foreach($vendorOffers as $offer)
                        <div style="border:1px solid #E2E8F0;border-radius:6px;padding:6px 8px;background:#F7FAFC;margin-bottom:6px;">
                            <div style="font-size:11px;font-weight:600;color:#0D5A8E;">{{ $offer->vendor_name }}</div>
                            <div style="font-size:10px;color:#718096;">{{ $offer->vendor_code }}</div>
                            @if($offer->pic_name)
                            <div style="font-size:10px;color:#9ca3af;">PIC: {{ $offer->pic_name }}</div>
                            @endif
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAGE 3 — MY TRANSACTIONS ═══════════════ --}}
    <div id="intd-pg3" class="intd-page">
        <div class="intd-card" style="flex:1;overflow:hidden;padding:8px 12px;display:flex;flex-direction:column;">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;flex-shrink:0;">
                &#128203; My Transactions &mdash; {{ $transactions->total() }} transaction{{ $transactions->total()==1?'':'s' }}
            </div>
            @if($transactions->isEmpty())
                <div style="text-align:center;color:#A0AEC0;padding:20px;font-size:12px;">No transactions yet</div>
            @else
                <div style="flex:1;overflow-y:scroll;overflow-x:hidden;min-height:0;scrollbar-gutter:stable;">
                <table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;">
                    <colgroup>
                        <col style="width:14%;">
                        <col style="width:16%;">
                        <col style="width:18%;">
                        <col style="width:15%;">
                        <col style="width:8%;">
                        <col style="width:15%;">
                        <col style="width:14%;">
                    </colgroup>
                    <thead><tr style="background:#F7FAFC;position:sticky;top:0;">
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Transaction #</th>
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Vendor</th>
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Product</th>
                        <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Sales Amount (RM)</th>
                        <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">%</th>
                        <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">My Commission (RM)</th>
                        <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach($transactions as $tx)
                        <tr style="border-bottom:1px solid #F7FAFC;">
                            <td style="padding:6px 8px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                            <td style="padding:6px 8px;">{{ $tx->vendor_name }}</td>
                            <td style="padding:6px 8px;">{{ $tx->product_name }}</td>
                            <td style="padding:6px 8px;text-align:right;font-weight:700;">{{ number_format($tx->premium_amount,2) }}</td>
                            <td style="padding:6px 8px;text-align:right;color:#9ca3af;">{{ number_format($tx->my_pct,1) }}%</td>
                            <td style="padding:6px 8px;text-align:right;font-weight:700;color:#38A169;">{{ number_format($tx->my_commission,2) }}</td>
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
                </div>

                {{-- Pinned Totals --}}
                <div style="flex-shrink:0;border-top:2px solid #E2E8F0;padding-top:4px;margin-top:4px;">
                    <table style="width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed;">
                        <colgroup>
                            <col style="width:14%;">
                            <col style="width:16%;">
                            <col style="width:18%;">
                            <col style="width:15%;">
                            <col style="width:8%;">
                            <col style="width:15%;">
                            <col style="width:14%;">
                        </colgroup>
                        <tr style="background:#F7FAFC;">
                            <td colspan="3" style="text-align:right;font-weight:700;color:#374151;padding:4px 8px;">Page Total:</td>
                            <td style="text-align:right;font-weight:800;color:#0D5A8E;padding:4px 8px;">{{ number_format($transactions->sum('premium_amount'),2) }}</td>
                            <td></td>
                            <td style="text-align:right;font-weight:800;color:#38A169;padding:4px 8px;">{{ number_format($transactions->sum('my_commission'),2) }}</td>
                            <td></td>
                        </tr>
                        <tr style="background:#EBF5FB;">
                            <td colspan="3" style="text-align:right;font-weight:700;color:#374151;padding:4px 8px;">Grand Total ({{ $transactions->total() }} records):</td>
                            <td style="text-align:right;font-weight:800;color:#1D4ED8;padding:4px 8px;">{{ number_format($grandTotal,2) }}</td>
                            <td></td>
                            <td style="text-align:right;font-weight:800;color:#1D4ED8;padding:4px 8px;">{{ number_format($commissionGrandTotal,2) }}</td>
                            <td></td>
                        </tr>
                    </table>
                    @if($transactions->hasPages())
                    <div style="margin-top:4px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
                        <span style="color:#9ca3af;">Showing {{ $transactions->firstItem() }}&ndash;{{ $transactions->lastItem() }} of {{ $transactions->total() }} records</span>
                        <div style="font-size:10px;">{{ $transactions->onEachSide(1)->links() }}</div>
                    </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Prev / Next --}}
    <button id="intd-btn-prev" style="display:none;position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">&#8592; Prev</button>
    <button id="intd-btn-next" onclick="changeIntdPage(1)" style="position:fixed;bottom:16px;right:16px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">Next &#8594;</button>

</div>

@push('scripts')
<script>
const chartData = @json($metrics['chartData']);
let intdChart = null;

// ───── Page navigation ─────
var intdCur = 1;
var intdReturnTo = 1; // page to return to via Prev after a direct jump (e.g. card -> page 3)
function goIntdPage(n, fromJump) {
    if (fromJump) { intdReturnTo = intdCur; }
    document.querySelectorAll('.intd-page').forEach(el => { el.classList.remove('active'); el.style.display='none'; });
    var t = document.getElementById('intd-pg'+n);
    t.classList.add('active'); t.style.display='flex';
    document.querySelectorAll('.intd-dot').forEach((d,i) => d.classList.toggle('on', i===n-1));
    var label = document.getElementById('intd-pg-label');
    if (label) label.textContent = n+' / 3';
    var prevBtn = document.getElementById('intd-btn-prev');
    if (n === 1) {
        prevBtn.style.display = 'none';
    } else {
        prevBtn.style.display = 'inline-block';
        if (fromJump) {
            var target = intdReturnTo;
            prevBtn.onclick = function(){ goIntdPage(target); };
        } else {
            prevBtn.onclick = function(){ changeIntdPage(-1); };
        }
    }
    document.getElementById('intd-btn-next').style.display = n<3?'inline-block':'none';
    intdCur = n;
    if (n===2 && intdChart) setTimeout(()=>intdChart.resize(), 50);
}
function changeIntdPage(dir) { var n = intdCur + dir; if(n>=1 && n<=3) goIntdPage(n); }

// If reloaded via pagination (URL has txpage param), stay on page 3
var intdInitPage = (window.location.search && window.location.search.indexOf('txpage') !== -1) ? 3 : 1;
goIntdPage(intdInitPage);

function switchIntdChart(type) {
    ['line','bar','pie'].forEach(function(t){
        var b = document.getElementById('intd-btn-'+t);
        if(t===type){ b.style.background='#7C3AED'; b.style.color='#fff'; b.style.borderColor='#7C3AED'; }
        else { b.style.background='#fff'; b.style.color='#374151'; b.style.borderColor='#d1d5db'; }
    });

    if (intdChart) intdChart.destroy();

    var isPie = type === 'pie';
    var palette = ['#7C3AED','#1B9AE4','#38A169','#D97706','#0891b2','#dc2626'];
    var colors = chartData.data.map((_, i) => palette[i % palette.length]);

    intdChart = new Chart(document.getElementById('intdChart').getContext('2d'), {
        type: type,
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'My Sales (RM)',
                data: chartData.data,
                backgroundColor: isPie ? colors : (type === 'bar' ? '#7C3AED' : 'rgba(124,58,237,0.1)'),
                borderColor: type === 'bar' ? colors : '#7C3AED',
                borderWidth: type === 'line' ? 2 : 1,
                borderRadius: type === 'bar' ? 3 : 0,
                fill: type === 'line',
                pointRadius: type === 'line' ? 4 : 0,
                tension: 0.4
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: isPie, position: 'bottom', labels: { font: { size: 9 }, boxWidth: 10 } },
                tooltip: { callbacks: { label: ctx => 'RM ' + ctx.parsed.toFixed(2) } }
            },
            scales: isPie ? {} : {
                x: { grid: { display: false }, ticks:{ font:{ size:9 } } },
                y: { ticks: { font:{ size:9 }, callback: v => 'RM '+v.toLocaleString() }, grid: { color: '#f3f4f6' } }
            }
        }
    });
    if (intdCur === 2) setTimeout(()=>intdChart.resize(), 50);
}

document.addEventListener('DOMContentLoaded', () => {
    switchIntdChart('line');
});
</script>
@endpush
@endsection
