@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.node_title'))

@section('content')
{{-- NEW 26 Sep 2026 — per Chris: link existing entities under a City /
     Branch / State / HQ. Search (filter-first) -> results with a tick box
     per row -> [All] ticks every row, untick any row that must NOT link ->
     Save. Rows fit the screen (no scroll); Prev bottom-left / Next (Save
     on the last page) bottom-right, bold blue. --}}
<style>
.le-field{display:flex; flex-direction:column; gap:3px; min-width:0;}
.le-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase; white-space:nowrap;}
.le-field input{font-size:10px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.le-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.le-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;}
.le-table{width:100%; border-collapse:collapse; font-size:11px; color:#263238;}
.le-table th{background:#f0f9ff; color:#374151; font-size:9.5px; font-weight:700; text-align:left; padding:5px 8px; border-bottom:1px solid #d1d5db;}
.le-table td{padding:5px 8px; border-bottom:1px solid #f3f4f6;}
.le-table tr.le-off td{color:#94A3B8;}
.le-table input[type=checkbox]{width:14px; height:14px; margin:0; cursor:pointer; accent-color:var(--gl-blue);}
</style>

<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; box-sizing:border-box; display:flex; flex-direction:column; gap:7px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_masterfile.link_entities_title') }} — {{ $parent->node_name }}</div>
        <div style="font-size:9px; color:#6b7280;">{{ $group->group_name ?? '' }} &nbsp;›&nbsp; {{ $parentLevel->level_name }} &nbsp;·&nbsp; {{ __('cbe_masterfile.link_entities_hint') }}</div>
    </div>

    @if(session('cbe_node_saved'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.node_saved') }} ({{ session('cbe_node_saved') }})</div>
    @endif
    @if(session('cbe_link_saved'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700;">✓ {{ session('cbe_link_saved') }}</div>
    @endif

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.cbe-kpi.hierarchy-nodes.link-entities', $parent->node_id) }}" style="flex-shrink:0; margin:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px;">
        <input type="hidden" name="searched" value="1">
        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            @foreach($filterLevels as $fl)
            <div class="le-field" style="flex:1 1 150px;">
                <label>{{ $fl->level_name }}</label>
                <input type="text" name="lv[{{ $fl->level_id }}]" value="{{ request('lv.'.$fl->level_id) }}" list="le-list-{{ $loop->index }}" autocomplete="off">
                <datalist id="le-list-{{ $loop->index }}">
                    @foreach(($levelLists[$fl->level_id] ?? []) as $nm)<option value="{{ $nm }}">@endforeach
                </datalist>
            </div>
            @endforeach
            <div class="le-field" style="flex:0 0 95px;">
                <label>{{ __('cbe_masterfile.link_pc_from') }}</label>
                <input type="text" name="pc_from" value="{{ request('pc_from') }}" maxlength="5" placeholder="40000">
            </div>
            <div class="le-field" style="flex:0 0 95px;">
                <label>{{ __('cbe_masterfile.link_pc_to') }}</label>
                <input type="text" name="pc_to" value="{{ request('pc_to') }}" maxlength="5" placeholder="49999">
            </div>
            <div class="le-field" style="flex:2 1 200px;">
                <label>{{ __('cbe_masterfile.cov_col_entity') }}</label>
                <input type="text" name="name" id="le-name" value="{{ request('name') }}" list="le-list-name" autocomplete="off">
                <datalist id="le-list-name">
                    @foreach($nameList as $nm)<option value="{{ $nm }}">@endforeach
                </datalist>
            </div>
            <button type="submit" class="le-btn">{{ __('masterfile.search') }}</button>
        </div>
    </form>

    {{-- Results --}}
    <form id="le-form" method="POST" action="{{ route('admin.cbe-kpi.hierarchy-nodes.link-entities.store', $parent->node_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:6px; margin:0;">
        @csrf
        <input type="hidden" name="return_query" value="{{ http_build_query(request()->except(['page'])) }}">

        @if($searched)
        <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <span id="le-count" style="font-size:9px; font-weight:700; color:var(--gl-blue);"></span>
            <span style="display:flex; gap:8px;">
                <button type="button" class="le-btn-outline" onclick="leTickAll(true)">{{ __('cbe_masterfile.link_all') }}</button>
                <button type="button" class="le-btn-outline" onclick="leTickAll(false)">{{ __('cbe_masterfile.cov_untick_all') }}</button>
            </span>
        </div>
        @endif

        <div id="le-wrap" style="flex:1; min-height:0; overflow:hidden; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
            @if(! $searched)
            <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.link_search_first') }}</div>
            @else
            <table class="le-table" id="le-table">
                <thead><tr>
                    <th style="width:50px; text-align:center;">{{ __('cbe_masterfile.link_col_link') }}</th>
                    <th>{{ __('cbe_masterfile.cov_col_entity') }}</th>
                    <th>{{ __('cbe_masterfile.form_level') }}</th>
                    <th>{{ __('cbe_masterfile.form_postcode') }}</th>
                    <th>{{ __('cbe_masterfile.form_city') }}</th>
                    <th>{{ __('cbe_masterfile.link_col_current') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr class="le-row">
                        <td style="text-align:center;">
                            <input type="hidden" name="shown_ids[]" value="{{ $r->node_id }}">
                            <input type="checkbox" name="linked_ids[]" value="{{ $r->node_id }}" @checked($r->parent_node_id === $parent->node_id)>
                        </td>
                        <td style="font-weight:600; color:#1565C0;">{{ $r->node_name }}@if($r->node_name_zh) <span style="color:#6b7280; font-weight:400;">{{ $r->node_name_zh }}</span>@endif</td>
                        <td style="white-space:nowrap;">{{ $r->level_name }}</td>
                        <td style="white-space:nowrap;">{{ $r->postcode ?: '—' }}</td>
                        <td>{{ $r->city ?: '—' }}</td>
                        <td>{{ $r->parent_node_id === $parent->node_id ? $parent->node_name : ($r->parent_name ?: __('cbe_masterfile.link_not_linked')) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_cbe_directory.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div>

        <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            <button type="button" class="le-btn" onclick="lePrev()">{{ __('masterfile.prev') }}</button>
            <span id="le-page" style="font-size:9.5px; color:#4b5563; font-weight:600;"></span>
            <button type="button" class="le-btn" id="le-next" onclick="leNext()" @if(! $searched || $rows->isEmpty()) style="visibility:hidden;" @endif>{{ __('masterfile.next') }}</button>
        </div>
    </form>
</div>

<script>
(function(){
    var prevUrl = @json(route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $parent->group_label_id]));
    var T = { next: @json(__('masterfile.next')), save: @json(__('cbe_masterfile.btn_save_node')), page: @json(__('cbe_masterfile.cov_page')), sel: @json(__('cbe_masterfile.cov_selected')) };
    var wrap = document.getElementById('le-wrap');
    var table = document.getElementById('le-table');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.le-row'));
    var page = 0, pages = [[]];

    function count(){
        var n = 0;
        rows.forEach(function(r){ var cb = r.querySelector('input[type=checkbox]'); if (cb.checked) n++; r.classList.toggle('le-off', !cb.checked); });
        var c = document.getElementById('le-count');
        if (c) c.textContent = T.sel.replace(':count', n).replace(':total', rows.length);
    }
    function show(){
        rows.forEach(function(r){ r.style.display = 'none'; });
        (pages[page] || []).forEach(function(r){ r.style.display = ''; });
        var lbl = document.getElementById('le-page');
        lbl.textContent = rows.length ? T.page.replace(':page', page + 1).replace(':pages', pages.length) : '';
        document.getElementById('le-next').textContent = (page >= pages.length - 1) ? T.save : T.next;
    }
    function build(){
        if (!table || !rows.length) { return; }
        rows.forEach(function(r){ r.style.display = ''; });
        var avail = wrap.clientHeight - table.querySelector('thead').offsetHeight - 4;
        pages = [[]]; var used = 0;
        rows.forEach(function(r){
            var h = r.offsetHeight;
            if (used + h > avail && pages[pages.length - 1].length) { pages.push([]); used = 0; }
            pages[pages.length - 1].push(r); used += h;
        });
        if (page > pages.length - 1) page = pages.length - 1;
        show();
    }
    window.lePrev = function(){ if (page > 0) { page--; show(); return; } window.location.href = prevUrl; };
    window.leNext = function(){ if (page < pages.length - 1) { page++; show(); return; } document.getElementById('le-form').submit(); };
    window.leTickAll = function(on){ rows.forEach(function(r){ r.querySelector('input[type=checkbox]').checked = on; }); count(); };
    rows.forEach(function(r){ r.querySelector('input[type=checkbox]').addEventListener('change', count); });
    count(); build();
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(build, 300); });
})();
</script>
@endsection
