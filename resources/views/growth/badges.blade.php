@extends('layouts.dashboard')

@section('page-title', __('growth.my_badges_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #212), REDESIGNED multiple times per Chris's
     feedback. LATEST FIX 25 Jul 2026 per Chris:
     - "TL/GL/Network in 3 equal vertical box same row" -> Recruiting is
       its own full-width row (5 tiers), TL/GL/Network sit side by side
       as 3 equal columns underneath.
     - "what is the lock for, how to unlock, so confusing" -> badges are
       AUTOMATIC, never manually unlocked. Replaced the lock icon with a
       plain "in progress" hourglass + an explicit "earns automatically"
       caption. Also fixed a real bug: Admin's company-wide aggregate
       view was showing 100% progress next to a locked icon because the
       lock was driven by Admin's OWN (nonexistent) personal award row —
       now driven by $b->achieved (current >= threshold), which is
       correct for both personal and aggregate views.
     - "why prev on top AND prev on bottom, blue fill always bottom-left"
       -> removed the top Prev pill entirely; single Prev button, bottom
       left, blue fill, matching the standard used everywhere else. --}}

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

<style>
    .bdg-wrap{height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; font-family:'Poppins',sans-serif; background:linear-gradient(135deg,#DFF8F7 0%,#CFF4F1 100%);}
    .bdg-card{background:rgba(255,255,255,0.65); backdrop-filter:blur(18px); border:1px solid rgba(255,255,255,0.4); border-radius:12px; box-shadow:0 4px 16px rgba(120,180,190,0.15); padding:8px 12px; display:flex; flex-direction:column; min-height:0;}
    .bdg-ct{font-size:10px; font-weight:700; color:#0f5c5a; text-transform:uppercase; letter-spacing:0.5px;}
    .bdg-sub{font-size:8px; color:#607d8b; font-weight:400; text-transform:none; letter-spacing:0;}
    .bdg-toggle{border:1px solid #0f9c96; background:#fff; color:#0f9c96; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600; cursor:pointer; font-family:'Poppins',sans-serif;}
    .bdg-toggle.active{background:#0f9c96; color:#fff;}
    .radio-row label{font-size:9px; color:#374151; margin-right:8px; cursor:pointer;}
    .radio-row input{accent-color:#1565C0; margin-right:2px;}
    .bdg-tile{flex:1; border-radius:8px; padding:6px 8px; text-align:center; display:flex; flex-direction:column; justify-content:center; min-width:0;}
    .bdg-empty{flex:1; display:flex; align-items:center; justify-content:center; font-size:9px; color:#94a3b8; text-align:center; padding:10px;}
</style>

<div class="bdg-wrap">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:6px;">
        <div>
            <h4 style="font-weight:700; margin:0; font-size:14px; color:#0f5c5a;">{{ __('growth.milestone_badges_heading') }}</h4>
            <div style="font-size:9.5px; color:#4b6e6c; margin-top:2px;">{{ __('growth.badges_earned_summary', ['earned' => $earnedCount, 'total' => $totalCount]) }}</div>
        </div>

        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            @if($isAdmin)
            <form method="GET" action="{{ route('growth-badges.index') }}">
                <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #b2dfdb; border-radius:20px; padding:5px 10px; font-size:9.5px; background:#fff; font-family:'Poppins',sans-serif; color:#0f5c5a;">
                    <option value="ALL" {{ $groupLabelId === 'ALL' ? 'selected' : '' }}>{{ __('growth.all_groups_company_wide') }}</option>
                    <option value="SYSTEM_DEFAULT" {{ $groupLabelId === 'SYSTEM_DEFAULT' ? 'selected' : '' }}>{{ __('growth.system_default_option') }}</option>
                    @foreach($groupLabels as $g)
                    <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
                    @endforeach
                </select>
            </form>
            @endif

            <div id="viewToggle" style="display:flex; gap:4px;">
                <button type="button" class="bdg-toggle active" data-view="dashboard" onclick="setView('dashboard')">{{ __('growth.dashboard_toggle') }}</button>
                <button type="button" class="bdg-toggle" data-view="graph" onclick="setView('graph')">{{ __('growth.graph_toggle') }}</button>
            </div>

            <div id="chartTypeRow" class="radio-row" style="display:none;">
                <label><input type="radio" name="ctype" value="bar" checked onchange="setChartType('bar')"> {{ __('growth.bar_option') }}</label>
                <label><input type="radio" name="ctype" value="pie" onchange="setChartType('pie')"> {{ __('growth.pie_option') }}</label>
                <label><input type="radio" name="ctype" value="line" onchange="setChartType('line')"> {{ __('growth.line_option') }}</label>
            </div>
        </div>
    </div>

    {{-- ===================== DASHBOARD VIEW ===================== --}}
    <div id="dashboardView" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">

        {{-- Recruiting — full width, its own row (5 tiers). --}}
        @php($rc = $groups['RECRUIT_COUNT'])
        <div class="bdg-card" style="flex:1.15;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-shrink:0;">
                <div class="bdg-ct">{{ $rc['icon'] }} {{ $rc['label'] }} <span class="bdg-sub">{{ __('growth.current_colon', ['value' => (int) $rc['current']]) }}</span></div>
                <a href="{{ route('growth-badges.detail', 'RECRUIT_COUNT') }}{{ $isAdmin ? '?group_label_id='.$groupLabelId : '' }}" style="font-size:9px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_detail_link') }}</a>
            </div>
            <div style="display:flex; gap:8px; flex:1; min-height:0;">
                @forelse($rc['badges'] as $b)
                    @include('growth.partials.badge-tile', ['b' => $b])
                @empty
                    <div class="bdg-empty">{{ __('growth.no_recruiting_badges') }}</div>
                @endforelse
            </div>
        </div>

        {{-- TL Promotions / GL Promotions / Network Size — 3 equal columns, same row. --}}
        <div style="display:flex; gap:8px; flex:1;">
            @foreach(['TL_PROMOTIONS','GL_PROMOTIONS','NETWORK_SIZE'] as $type)
            @php($group = $groups[$type])
            <div class="bdg-card" style="flex:1; min-width:0;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; flex-shrink:0; gap:4px;">
                    <div class="bdg-ct" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $group['icon'] }} {{ $group['label'] }}</div>
                    <a href="{{ route('growth-badges.detail', $type) }}{{ $isAdmin ? '?group_label_id='.$groupLabelId : '' }}" style="font-size:8.5px; color:#1565C0; font-weight:600; text-decoration:none; white-space:nowrap;">{{ __('growth.detail_link') }}</a>
                </div>
                <div class="bdg-sub" style="margin-bottom:4px;">{{ __('growth.current_label_short', ['value' => (int) $group['current']]) }}</div>
                <div style="display:flex; gap:6px; flex:1; min-height:0;">
                    @forelse($group['badges'] as $b)
                        @include('growth.partials.badge-tile', ['b' => $b])
                    @empty
                        <div class="bdg-empty">{{ __('growth.not_yet_configured') }}</div>
                    @endforelse
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ===================== GRAPH VIEW ===================== --}}
    <div id="graphView" style="flex:1; min-height:0; display:none; gap:8px;">
        <div class="bdg-card" style="flex:1;">
            <div class="bdg-ct" style="margin-bottom:4px;">{{ __('growth.progress_by_category_heading') }} <span class="bdg-sub">{{ __('growth.progress_by_category_sub') }}</span></div>
            <div style="flex:1; min-height:0; position:relative;">
                <canvas id="badgeChart"></canvas>
            </div>
        </div>
    </div>

    <div style="flex-shrink:0; padding-top:8px;">
        <a href="{{ route($agent->role === 'ADMIN' ? 'admin.dashboard' : ($agent->role === 'GROUP_LEADER' ? 'gl.dashboard' : ($agent->role === 'TEAM_LEADER' ? 'tl.dashboard' : 'introducer.dashboard'))) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 16px; font-size:10px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
    </div>

</div>

<script>
(function(){
    const i18nCurrent = @json(__('growth.chart_current_label'));
    const i18nNextMilestone = @json(__('growth.chart_next_milestone_label'));
    const labels = [@foreach($groups as $group){{ json_encode($group['label']) }},@endforeach];
    const current = [@foreach($groups as $group){{ (float) $group['current'] }},@endforeach];
    // "Next milestone" = the smallest not-yet-achieved threshold in that
    // category (or the top tier's threshold if every tier is achieved,
    // or 1 if the category has no badges configured yet).
    const targets = [
        @foreach($groups as $group)
            {{ (float) (($group['badges']->first(fn($b)=>!$b->achieved)->threshold_value ?? $group['badges']->last()->threshold_value ?? 1)) }},
        @endforeach
    ];
    const colors = ['#1565C0','#F9A825','#8E24AA','#00897B'];

    let chart = null;
    let currentType = 'bar';

    function buildChart(type){
        const ctx = document.getElementById('badgeChart').getContext('2d');
        if (chart) { chart.destroy(); }

        let cfg;
        if (type === 'pie') {
            cfg = {
                type: 'pie',
                data: { labels, datasets: [{ data: current, backgroundColor: colors, borderWidth: 2, borderColor:'#fff' }] },
                options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ font:{ family:"'Poppins',sans-serif", size:10 } } } } }
            };
        } else if (type === 'line') {
            cfg = {
                type: 'line',
                data: { labels, datasets: [
                    { label: i18nCurrent, data: current, borderColor:'#0f9c96', backgroundColor:'rgba(15,156,150,0.15)', fill:true, tension:0.3, pointRadius:4 },
                    { label: i18nNextMilestone, data: targets, borderColor:'#F9A825', borderDash:[5,4], fill:false, tension:0.3, pointRadius:3 }
                ] },
                options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ labels:{ font:{ family:"'Poppins',sans-serif", size:10 } } } } }
            };
        } else {
            cfg = {
                type: 'bar',
                data: { labels, datasets: [
                    { label: i18nCurrent, data: current, backgroundColor:'#0f9c96', borderRadius:4 },
                    { label: i18nNextMilestone, data: targets, backgroundColor:'rgba(249,168,37,0.55)', borderRadius:4 }
                ] },
                options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ labels:{ font:{ family:"'Poppins',sans-serif", size:10 } } } } }
            };
        }
        chart = new Chart(ctx, cfg);
    }

    window.setChartType = function(type){
        currentType = type;
        localStorage.setItem('badges_chart_type', type);
        buildChart(type);
    };

    window.setView = function(view){
        document.querySelectorAll('#viewToggle .bdg-toggle').forEach(b => b.classList.toggle('active', b.dataset.view === view));
        document.getElementById('dashboardView').style.display = view === 'dashboard' ? 'flex' : 'none';
        document.getElementById('graphView').style.display = view === 'graph' ? 'flex' : 'none';
        document.getElementById('chartTypeRow').style.display = view === 'graph' ? 'flex' : 'none';
        localStorage.setItem('badges_view', view);
        if (view === 'graph' && !chart) { buildChart(currentType); }
    };

    const savedType = localStorage.getItem('badges_chart_type');
    if (savedType) {
        currentType = savedType;
        const el = document.querySelector('input[name="ctype"][value="'+savedType+'"]');
        if (el) { el.checked = true; }
    }
    const savedView = localStorage.getItem('badges_view') || 'dashboard';
    setView(savedView);
})();
</script>
@endsection
