@extends('layouts.dashboard')
@section('title', \App\Services\RoleLabelService::label('TEAM_LEADER') . ' Dashboard')

@section('page-title')
{{ $agent->full_name }}'s Team <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->agent_code }} @if($group) &middot; {{ $group->group_name }} @endif @if($myGL) &middot; Under GL {{ $myGL->full_name }} @endif</span>
@endsection

@push('styles')
<style>
.tld-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.tld-page{display:none;flex:1;flex-direction:column;gap:6px;min-height:0;}
.tld-page.active{display:flex;}
.tld-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.tld-dot{width:7px;height:7px;border-radius:50%;background:#d1d5db;cursor:pointer;}
.tld-dot.on{background:#1565C0;}
</style>
@endpush

@section('content')
<div class="tld-wrap">

    {{-- Top bar --}}
    <div style="display:flex;justify-content:flex-end;align-items:center;">
        <div style="display:flex;align-items:center;gap:6px;">
            <div style="display:flex;gap:4px;">
                <div class="tld-dot on" onclick="goTldPage(1)"></div>
                <div class="tld-dot" onclick="goTldPage(2)"></div>
                <div class="tld-dot" onclick="goTldPage(3)"></div>
            </div>
            <span id="tld-pg-label" style="font-size:10px;color:#6b7280;">1 / 3</span>
        </div>
    </div>

    {{-- ═══════════════ PAGE 1 — METRICS ═══════════════ --}}
    <div id="tld-pg1" class="tld-page active">

        {{-- Row 1: Team --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;">
            <a href="{{ route('tl.introducers') }}" class="tld-card" style="border-left:3px solid #7C3AED;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">My {{ \App\Services\RoleLabelService::plural('INTRODUCER') }}</div>
                <div style="font-size:24px;font-weight:800;color:#7C3AED;">{{ $metrics['counts']->total_intro ?? 0 }}</div>
                <div style="font-size:11px;color:#9ca3af;">Total in my team &#8594;</div>
            </a>
            <a href="{{ route('tl.introducers', ['filter'=>'active']) }}" class="tld-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Active</div>
                <div style="font-size:24px;font-weight:800;color:#38A169;">{{ $metrics['counts']->active_intro ?? 0 }}</div>
                <div style="font-size:11px;color:#9ca3af;">Currently active &#8594;</div>
            </a>
            <a href="{{ route('tl.introducers', ['filter'=>'inactive']) }}" class="tld-card" style="border-left:3px solid #E53E3E;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Inactive</div>
                <div style="font-size:24px;font-weight:800;color:#E53E3E;">{{ $metrics['counts']->inactive_intro ?? 0 }}</div>
                <div style="font-size:11px;color:#9ca3af;">Need attention &#8594;</div>
            </a>
            <a href="{{ route('tl.introducers', ['filter'=>'new']) }}" class="tld-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">New This Month</div>
                <div style="font-size:24px;font-weight:800;color:#D97706;">{{ $metrics['counts']->new_this_month ?? 0 }}</div>
                <div style="font-size:11px;color:#9ca3af;">Joined this month &#8594;</div>
            </a>
        </div>

        {{-- Row 2: Performance --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;flex:1;">
            <a href="{{ route('tl.transactions') }}" class="tld-card" style="border-left:3px solid #0D5A8E;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Team Sales MTD</div>
                <div style="font-size:18px;font-weight:800;color:#0D5A8E;">RM {{ number_format($metrics['premium']->premium_mtd ?? 0,2) }}</div>
                <div style="font-size:10px;color:#38A169;font-weight:600;">Commission: RM {{ number_format($metrics['commission']->commission_mtd ?? 0,2) }}</div>
            </a>
            <a href="{{ route('tl.transactions') }}" class="tld-card" style="border-left:3px solid #1B9AE4;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Team Sales YTD</div>
                <div style="font-size:18px;font-weight:800;color:#1B9AE4;">RM {{ number_format($metrics['premium']->premium_ytd ?? 0,2) }}</div>
                <div style="font-size:10px;color:#9ca3af;">My team this year &#8594;</div>
            </a>
            <div class="tld-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">My Commission MTD</div>
                <div style="font-size:20px;font-weight:800;color:#38A169;">RM {{ number_format($metrics['commission']->commission_mtd ?? 0,2) }}</div>
                <div style="font-size:11px;color:#9ca3af;">My earnings this month</div>
            </div>
            <a href="{{ route('tl.rewards') }}" class="tld-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;text-decoration:none;color:inherit;cursor:pointer;">
                <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">Reward Points</div>
                <div style="font-size:20px;font-weight:800;color:#D97706;">{{ number_format($rewardPoints ?? 0,2) }} pts</div>
                <div style="font-size:11px;color:#9ca3af;">Available balance &#8594;</div>
            </a>
        </div>
    </div>

    {{-- ═══════════════ PAGE 2 — CHART + TOP INTRODUCERS ═══════════════ --}}
    <div id="tld-pg2" class="tld-page">
        <div style="display:grid;grid-template-columns:1.6fr 1fr;gap:8px;flex:1;min-height:0;">

            {{-- Chart --}}
            <div class="tld-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">&#128202; Team Sales &mdash; Last 6 Months</div>
                <div style="position:relative;flex:1;min-height:0;">
                    <canvas id="tldChart"></canvas>
                </div>
            </div>

            {{-- Top Introducers --}}
            <div class="tld-card" style="display:flex;flex-direction:column;overflow:hidden;">
                <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:6px;">&#127942; Top {{ \App\Services\RoleLabelService::plural('INTRODUCER') }} This Month</div>
                <div style="overflow:auto;flex:1;min-height:0;">
                    @if($metrics['topIntroducers']->isEmpty())
                        <div style="text-align:center;color:#A0AEC0;padding:16px;font-size:12px;">No data yet</div>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:11px;">
                            @foreach($metrics['topIntroducers'] as $i => $intro)
                            <tr style="cursor:pointer;border-bottom:1px solid #F7FAFC;" onclick="window.location='{{ route('tl.introducers.transactions', $intro->agent_id) }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                                <td style="padding:5px 6px;">{{ ['🥇','🥈','🥉'][$i] ?? '#'.($i+1) }}</td>
                                <td style="padding:5px 6px;font-weight:600;">{{ $intro->full_name }} &#8594;</td>
                                <td style="padding:5px 6px;text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($intro->total_premium,2) }} <span style="color:#9ca3af;font-weight:400;">sales</span></td>
                            </tr>
                            @endforeach
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════ PAGE 3 — RECENT TRANSACTIONS ═══════════════ --}}
    <div id="tld-pg3" class="tld-page">
        <div class="tld-card" style="flex:1;overflow:hidden;padding:8px 12px 50px 12px;display:flex;flex-direction:column;">
            <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;">
                &#128203; Recent Transactions &mdash; My Team
            </div>
            @if($recentTransactions->isEmpty())
                <div style="text-align:center;color:#A0AEC0;padding:20px;font-size:12px;">No transactions yet</div>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:12px;">
                    <thead><tr style="background:#F7FAFC;">
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Transaction #</th>
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Agent</th>
                        <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Vendor</th>
                        <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Sales Amount (RM)</th>
                        <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Date</th>
                        <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">Status</th>
                    </tr></thead>
                    <tbody>
                        @foreach($recentTransactions as $tx)
                        <tr style="border-bottom:1px solid #F7FAFC;">
                            <td style="padding:6px 8px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                            <td style="padding:6px 8px;">{{ $tx->agent_name }}</td>
                            <td style="padding:6px 8px;">{{ $tx->vendor_name }}</td>
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
    <button id="tld-btn-prev" onclick="changeTldPage(-1)" style="display:none;position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">&#8592; Prev</button>
    <button id="tld-btn-next" onclick="changeTldPage(1)" style="position:fixed;bottom:16px;right:16px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;">Next &#8594;</button>

</div>

@push('scripts')
<script>
const chartData = @json($metrics['chartData']);
let tldChart = null;

// ───── Page navigation ─────
var tldCur = 1;
function goTldPage(n) {
    document.querySelectorAll('.tld-page').forEach(el => { el.classList.remove('active'); el.style.display='none'; });
    var t = document.getElementById('tld-pg'+n);
    t.classList.add('active'); t.style.display='flex';
    document.querySelectorAll('.tld-dot').forEach((d,i) => d.classList.toggle('on', i===n-1));
    var label = document.getElementById('tld-pg-label');
    if (label) label.textContent = n+' / 3';
    document.getElementById('tld-btn-prev').style.display = n>1?'inline-block':'none';
    document.getElementById('tld-btn-next').style.display = n<3?'inline-block':'none';
    tldCur = n;
    if (n===2 && tldChart) setTimeout(()=>tldChart.resize(), 50);
}
function changeTldPage(dir) { var n = tldCur + dir; if(n>=1 && n<=3) goTldPage(n); }

// If reloaded via pagination (URL has txpage param), stay on page 3
var tldInitPage = (window.location.search && window.location.search.indexOf('txpage') !== -1) ? 3 : 1;
goTldPage(tldInitPage);

document.addEventListener('DOMContentLoaded', () => {
    tldChart = new Chart(document.getElementById('tldChart').getContext('2d'), {
        type: 'bar',
        data: { labels: chartData.labels, datasets: [{ label: 'Premium (RM)', data: chartData.data, backgroundColor: '#0284C7', borderRadius: 4 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks:{ font:{ size:9 } } },
                y: { ticks: { font:{ size:9 }, callback: v => 'RM '+v.toLocaleString() } }
            }
        }
    });
    if (tldCur === 2) setTimeout(()=>tldChart.resize(), 50);
});
</script>
@endpush
@endsection
