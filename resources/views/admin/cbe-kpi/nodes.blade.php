@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $levelName)

@section('content')

<style>
.cbe-node-row{display:flex; align-items:center; justify-content:space-between; padding:3.5px 10px; border-bottom:1px solid #eef2f7; font-size:8.5px; line-height:1.25;}
.cbe-node-row:last-child{border-bottom:none;}
.cbe-node-name{color:#263238; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cbe-node-city{color:#94A3B8; font-size:7.5px; margin-left:6px;}
.cbe-view-btn{background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:4px; padding:3px 10px; font-size:7.5px; font-weight:700; white-space:nowrap; flex-shrink:0;}
.cbe-pg-btn{background:#0D5A8E; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbe-pg-btn-disabled{background:#f3f4f6; color:#9ca3af; border-radius:5px; padding:4px 12px; font-size:9px; font-weight:700;}
.cbe-city-tabs{display:flex; gap:4px; overflow-x:auto; overflow-y:hidden; white-space:nowrap; flex-shrink:0;}
.cbe-city-tabs::-webkit-scrollbar{height:5px;}
.cbe-city-tab{flex-shrink:0; display:inline-block; padding:4px 11px; font-size:8px; font-weight:700; color:var(--gl-blue); background:var(--gl-light); border:1px solid var(--gl-cyan2); border-bottom:none; border-radius:6px 6px 0 0; text-decoration:none; white-space:nowrap;}
.cbe-city-tab.active{background:var(--gl-blue); color:#fff; border-color:var(--gl-blue);}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238; white-space:nowrap;">{{ $levelName }}</div>
            <div style="font-size:8.5px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $group->group_name }}</div>
        </div>
        <a href="{{ route('admin.cbe-kpi', ['node' => $groupRootNodeId]) }}" style="font-size:8.5px; color:var(--gl-blue); text-decoration:none; font-weight:700; white-space:nowrap;">← {{ __('admin_cbe_kpi.back_to_dashboard') }}</a>
    </div>

    {{-- NEW 26 Aug 2026, 13th pass — per Chris: "can you create folder
    tap for group. Klang, Pelabuhan Klang, Pulau Ketam, Kapar, Meru.
    dont hard code. cater for future other branch that have many sub
    group (post code group)" — $cities is read fresh from whatever city
    values actually exist in this scope's data (never a fixed list), so
    it automatically covers any future district/branch. If there are
    more tabs than fit on screen, ONLY this strip scrolls horizontally
    — the rest of the page never scrolls. --}}
    @if(count($cities) > 1)
    <div class="cbe-city-tabs">
        <a href="{{ route('admin.cbe-kpi.nodes', request()->except(['city', 'page'])) }}" class="cbe-city-tab{{ ! $activeCity ? ' active' : '' }}">{{ __('admin_cbe_kpi.filter_all') }}</a>
        @foreach($cities as $c)
        <a href="{{ route('admin.cbe-kpi.nodes', array_merge(request()->except(['city', 'page']), ['city' => $c])) }}" class="cbe-city-tab{{ $activeCity === $c ? ' active' : '' }}">{{ $c }}</a>
        @endforeach
    </div>
    @endif

    <div style="flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($nodes as $node)
            @php
                // NEW 26 Aug 2026 — per Chris: show the Chinese name as
                // primary when the interface language is Chinese.
                $isZhLocale = app()->getLocale() === 'zh' && $node->node_name_zh;
                $primaryName = $isZhLocale ? $node->node_name_zh : $node->node_name;
                $secondaryName = $isZhLocale ? $node->node_name : $node->node_name_zh;
            @endphp
            <div class="cbe-node-row">
                <div style="min-width:0;">
                    <span class="cbe-node-name">{{ $primaryName }}</span>
                    @if($secondaryName)<span class="cbe-node-city">{{ $secondaryName }}</span>@endif
                    @if($node->city)<span class="cbe-node-city">— {{ $node->city }}</span>@endif
                </div>
                <a href="{{ route('admin.cbe-kpi', ['node' => $node->node_id]) }}" class="cbe-view-btn">{{ __('admin_cbe_kpi.view_details') }}</a>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('dashboard.admin_no_matches') }}</div>
            @endforelse
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:5px 12px; border-top:1px solid #eef2f7;">
            <span style="font-size:8px; color:#718096;">{{ $nodes->firstItem() ?? 0 }}–{{ $nodes->lastItem() ?? 0 }} {{ __('admin_cbe_kpi.of_total', ['total' => $nodes->total()]) }}</span>
            <div style="display:flex; gap:5px;">
                @if($nodes->onFirstPage())
                    <span class="cbe-pg-btn-disabled">← {{ __('admin_cbe_kpi.prev') }}</span>
                @else
                    <a href="{{ $nodes->previousPageUrl() }}" class="cbe-pg-btn">← {{ __('admin_cbe_kpi.prev') }}</a>
                @endif
                @if($nodes->hasMorePages())
                    <a href="{{ $nodes->nextPageUrl() }}" class="cbe-pg-btn">{{ __('admin_cbe_kpi.next') }} →</a>
                @else
                    <span class="cbe-pg-btn-disabled">{{ __('admin_cbe_kpi.next') }} →</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
