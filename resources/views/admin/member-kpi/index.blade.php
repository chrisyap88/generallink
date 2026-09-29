@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.kpi_title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris (member file item 35): Member KPI dashboard.
     All figures from the member file. Blue figures open the member search
     with the same filter. --}}
@include('admin.member-file._style')
@php
    $s = fn ($extra = []) => route('admin.member-file.index', array_merge(array_filter(['group' => $group->group_label_id ?? null]), ['go' => 1], $extra));
    $n = fn ($v) => number_format((int) $v);
    $tl = $typeLabels;
@endphp
<style>
.kp-grid{flex:1; min-height:0; display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); grid-template-rows:auto minmax(0,1fr); gap:8px;}
.kp-card{background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; min-width:0; overflow:hidden; display:flex; flex-direction:column; gap:4px;}
.kp-h{font-size:10px; font-weight:700; color:#1565C0; text-transform:uppercase; white-space:nowrap;}
.kp-big{font-size:22px; font-weight:700; color:#263238; line-height:1.1;}
.kp-row{display:flex; justify-content:space-between; gap:8px; font-size:11px; padding:2px 0; border-bottom:1px solid #f3f4f6; white-space:nowrap;}
.kp-row a,.kp-big a{color:#1565C0; text-decoration:none; font-weight:700;}
.kp-lbl{color:#374151; overflow:visible;}
</style>
<div class="mf-page">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-shrink:0; padding-right:58px;">
        <div class="mf-title">{{ __('member_file.kpi_title') }} — {{ $group->group_name ?? __('member_file.all_groups') }}</div>
        @if($isAdmin)
        <div class="mf-f" style="flex-direction:row; align-items:center; gap:6px;">
            <label>{{ __('member_file.group') }}</label>
            <input id="kp-group" list="kp-group-list" value="{{ $group->group_name ?? __('member_file.all_groups') }}" autocomplete="off" style="width:260px;">
            <datalist id="kp-group-list"><option value="{{ __('member_file.all_groups') }}">@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
        </div>
        @endif
    </div>
    <div class="kp-grid" id="kp-grid">
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_people') }}</div><div class="kp-big"><a href="{{ $s() }}">{{ $n($k['people']) }}</a></div><div class="mf-sub">{{ __('member_file.kpi_in_entities', ['n' => $n($k['entities'])]) }}</div></div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_new_month') }}</div><div class="kp-big">{{ $n($k['new_month']) }}</div><div class="mf-sub">{{ __('member_file.kpi_ended_month') }}: {{ $n($k['ended_month']) }}</div></div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_birthdays') }}</div><div class="kp-big"><a href="{{ $s(['birth_month' => now()->month]) }}">{{ $n($k['birthdays']) }}</a></div><div class="mf-sub">{{ now()->translatedFormat('F Y') }}</div></div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_collected') }}</div><div class="kp-big">RM {{ number_format($k['collected_month'], 2) }}</div><div class="mf-sub">{{ now()->translatedFormat('F Y') }}</div></div>

        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_by_type') }}</div>
            @foreach(['MEMBER', 'FOLLOWER', 'BELIEVER', 'DONOR', 'SPONSOR', 'VOLUNTEER', 'CONSULTANT', 'PRACTITIONER', 'COMMITTEE'] as $t)
                <div class="kp-row"><span class="kp-lbl">{{ $tl[$t] ?? $t }}</span><span>{{ $n($k['by_type'][$t] ?? 0) }}</span></div>
            @endforeach
        </div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_fees') }}</div>
            @foreach(['PAID', 'DUE', 'OVERDUE', 'FREE', 'WAIVED', 'FAMILY'] as $f)
                <div class="kp-row"><span class="kp-lbl">{{ __('member_file.fee_'.$f) }}</span><span class="mf-fee {{ $f }}">{{ $n($k['fee'][$f] ?? 0) }}</span></div>
            @endforeach
            <div class="kp-row"><span class="kp-lbl">{{ __('member_file.kpi_no_plan') }}</span><span>{{ $n($k['no_plan']) }}</span></div>
        </div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.membership_type') }}</div>
            @forelse($k['mem_type'] as $m)
                <div class="kp-row"><span class="kp-lbl">{{ $m->label }}</span><a href="{{ $s(['membership_type_id' => $m->id]) }}">{{ $n($m->n) }}</a></div>
            @empty
                <div class="mf-sub">—</div>
            @endforelse
            <div class="kp-h" style="margin-top:6px;">{{ __('member_file.kpi_how') }}</div>
            @foreach($k['how'] as $src => $cnt)
                <div class="kp-row"><span class="kp-lbl">{{ __('member_file.how_'.$src) }}</span><span>{{ $n($cnt) }}</span></div>
            @endforeach
        </div>
        <div class="kp-card"><div class="kp-h">{{ __('member_file.kpi_top') }}</div>
            @forelse($k['top'] as $i => $t)
                <div class="kp-row"><span class="kp-lbl">{{ $i + 1 }}. {{ $t->node_name }}</span><span>{{ $n($t->n) }}</span></div>
            @empty
                <div class="mf-sub">—</div>
            @endforelse
        </div>
    </div>
    <div class="mf-bar">
        <a href="{{ $isAdmin ? route('admin.cbe-kpi') : route('cbe.exec-dashboard') }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span>
        <a href="{{ route('admin.member-file.landing', array_filter(['group' => $group->group_label_id ?? null])) }}" class="mf-btn">{{ __('masterfile.next') }}</a>
    </div>
</div>
<script>
(function(){
    var gi = document.getElementById('kp-group'); if (gi) {
        var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values()), ALL = @json(__('member_file.all_groups'));
        gi.addEventListener('change', function(){ var h = groups.filter(function(g){ return g.v === gi.value; })[0];
            if (h) window.location.href = @json(route('admin.member-kpi')) + '?group=' + encodeURIComponent(h.id); else if (gi.value === ALL) window.location.href = @json(route('admin.member-kpi')); });
    }
    // no scroll: long entity names wrap; if a card is still too tall the whole grid shrinks together
    var g = document.getElementById('kp-grid'), fs = 11;
    document.querySelectorAll('.kp-lbl').forEach(function(e){ e.style.whiteSpace = 'normal'; });
    function over(){ return [].some.call(g.querySelectorAll('.kp-card'), function(c){ return c.scrollHeight > c.clientHeight + 1; }); }
    var z = 1; while (over() && z > 0.6) { z -= 0.04; g.style.zoom = z; }
})();
</script>
@endsection
