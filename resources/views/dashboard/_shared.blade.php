{{-- ===================== METRIC CARDS ===================== --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px">

    <div class="metric-card">
        <div class="metric-label">Commission this month</div>
        <div class="metric-value">RM {{ number_format($metrics['commThisMonth'], 2) }}</div>
        <div class="metric-sub">
            @if($metrics['commGrowth'] >= 0)
                ▲ {{ $metrics['commGrowth'] }}% vs last month
            @else
                ▼ {{ abs($metrics['commGrowth']) }}% vs last month
            @endif
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Active agents</div>
        <div class="metric-value">{{ number_format($metrics['activeAgents']) }}</div>
        <div class="metric-sub">of {{ number_format($metrics['totalAgents']) }} total</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Policies this month</div>
        <div class="metric-value">{{ number_format($metrics['policiesThisMonth']) }}</div>
        <div class="metric-sub">Active policies submitted</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Renewals due (30 days)</div>
        <div class="metric-value" style="{{ $metrics['renewalsDue'] > 0 ? 'color:#E53E3E' : '' }}">
            {{ number_format($metrics['renewalsDue']) }}
        </div>
        <div class="metric-sub" style="{{ $metrics['renewalsDue'] > 0 ? 'color:#E53E3E' : '' }}">
            {{ $metrics['renewalsDue'] > 0 ? 'Action required' : 'All clear' }}
        </div>
    </div>

    <div class="metric-card">
        <div class="metric-label">My reward points</div>
        <div class="metric-value" style="color:#7C3AED">{{ number_format($metrics['pointsBalance'], 0) }}</div>
        <div class="metric-sub">Available balance</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">My commission wallet</div>
        <div class="metric-value" style="color:#0D5A8E">RM {{ number_format($agent->commission_balance, 2) }}</div>
        <div class="metric-sub">Available for payout</div>
    </div>

</div>

{{-- ===================== INCOME CHART + ORG CHART ROW ===================== --}}
<div style="display:grid;grid-template-columns:1fr 380px;gap:16px;margin-bottom:24px">

    {{-- Income Performance --}}
    <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
            <div class="card-title" style="margin-bottom:0">
                <i class="ti ti-chart-bar" style="color:#0D5A8E"></i> Income Performance
            </div>
            <div style="display:flex;gap:6px">
                <button onclick="setChartMode('chart')" id="btn-chart" class="dash-tab active-tab">
                    <i class="ti ti-chart-bar"></i> Chart
                </button>
                <button onclick="setChartMode('table')" id="btn-table" class="dash-tab">
                    <i class="ti ti-table"></i> Table
                </button>
                <button onclick="downloadExcel()" class="dash-tab">
                    <i class="ti ti-download"></i> Excel
                </button>
                <select id="period-select" onchange="changePeriod(this.value)"
                    style="font-size:12px;padding:4px 8px;border:1px solid #E2E8F0;border-radius:6px;background:#F7FAFC">
                    <option value="6months">Last 6 months</option>
                    <option value="year">This year</option>
                    <option value="quarter">By quarter</option>
                </select>
            </div>
        </div>

        {{-- Legend --}}
        <div style="display:flex;gap:14px;margin-bottom:10px;font-size:11px;color:#718096">
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#185FA5;margin-right:4px"></span>Introducer</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#854F0B;margin-right:4px"></span>Team Leader</span>
            <span><span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#534AB7;margin-right:4px"></span>Group Leader</span>
        </div>

        {{-- Chart view --}}
        <div id="income-chart-view" style="position:relative;height:220px">
            <canvas id="incomeChart" role="img" aria-label="Income by month grouped by agent role"></canvas>
        </div>

        {{-- Table view --}}
        <div id="income-table-view" style="display:none;overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:#F7FAFC">
                        <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;font-weight:600;color:#4A5568">Period</th>
                        <th style="padding:8px;text-align:right;border-bottom:1px solid #E2E8F0;font-weight:600;color:#185FA5">Introducer (RM)</th>
                        <th style="padding:8px;text-align:right;border-bottom:1px solid #E2E8F0;font-weight:600;color:#854F0B">Team Leader (RM)</th>
                        <th style="padding:8px;text-align:right;border-bottom:1px solid #E2E8F0;font-weight:600;color:#534AB7">Group Leader (RM)</th>
                        <th style="padding:8px;text-align:right;border-bottom:1px solid #E2E8F0;font-weight:600;color:#1A202C">Total (RM)</th>
                    </tr>
                </thead>
                <tbody id="income-tbody"></tbody>
            </table>
        </div>
    </div>

    {{-- Org Chart --}}
    <div class="card" style="overflow:hidden">
        <div class="card-title">
            <i class="ti ti-hierarchy-2" style="color:#0D5A8E"></i> Organisation Chart
        </div>
        <div id="org-chart-container" style="overflow:auto;max-height:260px;font-size:12px">
            @foreach($orgTree as $root)
                @include('components.org-node', ['node' => $root, 'depth' => 0])
            @endforeach
        </div>
    </div>
</div>

{{-- ===================== RENEWAL ALERTS + RECENT POLICIES ===================== --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">

    {{-- Renewal Alerts --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-calendar-due" style="color:#E53E3E"></i> Upcoming Renewals
            @if(count($renewalAlerts) > 0)
                <span style="margin-left:auto;background:#FED7D7;color:#742A2A;font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600">
                    {{ count($renewalAlerts) }}
                </span>
            @endif
        </div>
        @if(count($renewalAlerts) === 0)
            <div style="text-align:center;color:#A0AEC0;padding:24px 0;font-size:13px">
                <i class="ti ti-circle-check" style="font-size:32px;display:block;margin-bottom:8px;color:#48BB78"></i>
                No renewals due in 30 days
            </div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:#FFF5F5">
                            <th style="padding:6px 8px;text-align:left;color:#742A2A;font-weight:600;border-bottom:1px solid #FED7D7">Policy</th>
                            <th style="padding:6px 8px;text-align:left;color:#742A2A;font-weight:600;border-bottom:1px solid #FED7D7">Customer</th>
                            <th style="padding:6px 8px;text-align:right;color:#742A2A;font-weight:600;border-bottom:1px solid #FED7D7">Due</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($renewalAlerts as $r)
                        <tr style="border-bottom:1px solid #F7FAFC">
                            <td style="padding:6px 8px;font-weight:500">{{ $r->policy_number }}</td>
                            <td style="padding:6px 8px;color:#4A5568">{{ $r->customer_name }}</td>
                            <td style="padding:6px 8px;text-align:right;color:#E53E3E;font-weight:600">
                                {{ \Carbon\Carbon::parse($r->renewal_date)->format('d M Y') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Recent Policies --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-file-invoice" style="color:#0D5A8E"></i> Recent Policies
        </div>
        @if(count($recentPolicies) === 0)
            <div style="text-align:center;color:#A0AEC0;padding:24px 0;font-size:13px">
                <i class="ti ti-file-off" style="font-size:32px;display:block;margin-bottom:8px"></i>
                No policies yet
            </div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:#F7FAFC">
                            <th style="padding:6px 8px;text-align:left;color:#4A5568;font-weight:600;border-bottom:1px solid #E2E8F0">Policy</th>
                            <th style="padding:6px 8px;text-align:left;color:#4A5568;font-weight:600;border-bottom:1px solid #E2E8F0">Product</th>
                            <th style="padding:6px 8px;text-align:right;color:#4A5568;font-weight:600;border-bottom:1px solid #E2E8F0">Premium</th>
                            <th style="padding:6px 8px;text-align:center;color:#4A5568;font-weight:600;border-bottom:1px solid #E2E8F0">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPolicies as $p)
                        <tr style="border-bottom:1px solid #F7FAFC">
                            <td style="padding:6px 8px;font-weight:500">{{ $p->policy_number }}</td>
                            <td style="padding:6px 8px;color:#4A5568">{{ $p->product_name }}</td>
                            <td style="padding:6px 8px;text-align:right;font-weight:600">RM {{ number_format($p->premium_amount, 2) }}</td>
                            <td style="padding:6px 8px;text-align:center">
                                <span class="status-badge status-{{ strtolower($p->status) }}">{{ $p->status }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
.dash-tab {
    background: #F7FAFC; border: 1px solid #E2E8F0; border-radius: 6px;
    padding: 4px 10px; font-size: 12px; cursor: pointer; color: #4A5568;
    display: inline-flex; align-items: center; gap: 4px;
}
.dash-tab:hover { background: #EBF5FB; color: #0D5A8E; border-color: #1B9AE4; }
.active-tab { background: #EBF5FB; color: #0D5A8E; border-color: #1B9AE4; font-weight: 600; }
</style>

@push('scripts')
<script>
const incomeData = @json($incomeMonthly);
let incomeChart = null;
let currentMode = 'chart';

function buildChart() {
    if (incomeChart) incomeChart.destroy();
    const ctx = document.getElementById('incomeChart').getContext('2d');
    incomeChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: incomeData.labels,
            datasets: [
                { label: 'Introducer',   data: incomeData.introducer,   backgroundColor: '#185FA5', borderRadius: 3, borderWidth: {top:2,left:0,right:0,bottom:0}, borderColor: '#0C447C' },
                { label: 'Team Leader',  data: incomeData.team_leader,  backgroundColor: '#854F0B', borderRadius: 3, borderWidth: {top:2,left:0,right:0,bottom:0}, borderColor: '#633806' },
                { label: 'Group Leader', data: incomeData.group_leader, backgroundColor: '#534AB7', borderRadius: 3, borderWidth: {top:2,left:0,right:0,bottom:0}, borderColor: '#3C3489' },
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false },
                tooltip: { callbacks: {
                    label: c => ' RM ' + c.parsed.y.toLocaleString('en-MY', {minimumFractionDigits:2}),
                    footer: items => 'Total: RM ' + items.reduce((a,i)=>a+i.parsed.y,0).toLocaleString('en-MY', {minimumFractionDigits:2})
                }}
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: { grid: { color: 'rgba(0,0,0,0.05)' },
                     ticks: { font: { size: 11 }, callback: v => 'RM ' + v.toLocaleString() } }
            }
        }
    });
}

function buildTable() {
    const tbody = document.getElementById('income-tbody');
    tbody.innerHTML = '';
    incomeData.labels.forEach((lbl, i) => {
        const i_val  = incomeData.introducer[i] || 0;
        const tl_val = incomeData.team_leader[i] || 0;
        const gl_val = incomeData.group_leader[i] || 0;
        const tot    = i_val + tl_val + gl_val;
        const fmt = v => 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits:2});
        const tr = document.createElement('tr');
        tr.style.borderBottom = '1px solid #F7FAFC';
        tr.innerHTML = `<td style="padding:7px 8px;font-weight:500">${lbl}</td>
            <td style="padding:7px 8px;text-align:right;color:#185FA5">${fmt(i_val)}</td>
            <td style="padding:7px 8px;text-align:right;color:#854F0B">${fmt(tl_val)}</td>
            <td style="padding:7px 8px;text-align:right;color:#534AB7">${fmt(gl_val)}</td>
            <td style="padding:7px 8px;text-align:right;font-weight:600">${fmt(tot)}</td>`;
        tbody.appendChild(tr);
    });
}

function setChartMode(mode) {
    currentMode = mode;
    document.getElementById('income-chart-view').style.display = mode === 'chart' ? 'block' : 'none';
    document.getElementById('income-table-view').style.display = mode === 'table' ? 'block' : 'none';
    document.getElementById('btn-chart').className = 'dash-tab' + (mode === 'chart' ? ' active-tab' : '');
    document.getElementById('btn-table').className = 'dash-tab' + (mode === 'table' ? ' active-tab' : '');
}

function changePeriod(val) {
    // In production: fetch from /api/income?period=val
    // For now rebuilds with existing data
    buildChart(); buildTable();
}

function downloadExcel() {
    const rows = [['Period','Introducer (RM)','Team Leader (RM)','Group Leader (RM)','Total (RM)']];
    let gtI=0, gtTL=0, gtGL=0;
    incomeData.labels.forEach((lbl, i) => {
        const iv = incomeData.introducer[i]||0, tv = incomeData.team_leader[i]||0, gv = incomeData.group_leader[i]||0;
        rows.push([lbl, iv, tv, gv, iv+tv+gv]);
        gtI+=iv; gtTL+=tv; gtGL+=gv;
    });
    rows.push(['TOTAL', gtI, gtTL, gtGL, gtI+gtTL+gtGL]);
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = [{wch:14},{wch:18},{wch:18},{wch:18},{wch:14}];
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, 'Income', ws);
    XLSX.writeFile(wb, 'generallink_income_' + new Date().toISOString().slice(0,10) + '.xlsx');
}

document.addEventListener('DOMContentLoaded', () => { buildChart(); buildTable(); });
</script>
@endpush
