@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.hier_link_title'))

@section('content')
{{-- NEW 26 Sep 2026 — per Chris: separate program from Entity
     Maintenance. 1) Link To (Parent) — which City / Branch / State / HQ
     (type-ahead pick list). 2) Search boxes: City, Postcode From-To,
     Entity Code From-To, Entity Name, then GO. 3) Every result is TICKED
     by default; untick row by row any that must not link. Rows fit the
     screen (no scroll, no truncation) — Prev bottom-left, Next
     bottom-right (Save on the last page), bold blue. --}}
<style>
.hl-table{width:100%; border-collapse:collapse; font-size:11px; color:#263238;}
.hl-table th{background:#f0f9ff; color:#374151; font-size:9.5px; font-weight:700; text-align:left; padding:5px 8px; border-bottom:1px solid #d1d5db;}
.hl-table td{padding:5px 8px; border-bottom:1px solid #f3f4f6;}
.hl-table tr.hl-off td{color:#94A3B8;}
.hl-table input[type=checkbox]{width:14px; height:14px; margin:0; cursor:pointer; accent-color:var(--gl-blue);}
.hl-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.hl-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;}
.hl-lbl{font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; white-space:nowrap;}
.hl-in{font-size:11px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; box-sizing:border-box; width:100%;}
</style>

<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; box-sizing:border-box; display:flex; flex-direction:column; gap:7px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_masterfile.hier_link_title') }}</div>
        <div style="font-size:9px; color:#6b7280;">{{ __('cbe_masterfile.hier_link_intro') }}</div>
    </div>

    @if(session('cbe_link_saved'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700;">✓ {{ session('cbe_link_saved') }}</div>
    @endif

    <form id="hl-search" method="GET" action="{{ route('admin.cbe-kpi.hierarchy-link') }}" style="flex-shrink:0; margin:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; display:flex; flex-direction:column; gap:8px;">
        <div style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
            @if(! $isOfficer && $groups->count() > 1)
            <div style="display:flex; flex-direction:column; gap:3px; flex:1 1 220px;">
                <span class="hl-lbl">{{ __('cbe_masterfile.hier_link_group') }}</span>
                <select name="group" class="hl-in" onchange="document.getElementById('hl-parent-id').value=''; document.getElementById('hl-parent-text').value=''; this.form.submit();">
                    <option value="">—</option>
                    @foreach($groups as $g)
                    <option value="{{ $g->group_label_id }}" @selected($group && $group->group_label_id === $g->group_label_id)>{{ $g->group_name }}</option>
                    @endforeach
                </select>
            </div>
            @elseif($group)
            <input type="hidden" name="group" value="{{ $group->group_label_id }}">
            @endif
            @if($group)
            <div style="display:flex; flex-direction:column; gap:3px; flex:2 1 300px;">
                <span class="hl-lbl" style="color:var(--gl-blue);">{{ __('cbe_masterfile.hier_link_parent') }}</span>
                <input type="hidden" name="parent_id" id="hl-parent-id" value="{{ $parent->node_id ?? '' }}">
                <input type="text" id="hl-parent-text" class="hl-in" list="hl-parent-list" autocomplete="off" value="{{ $parent ? $parent->node_name.' ('.$parentLevel->level_name.')' : '' }}" placeholder="{{ __('cbe_masterfile.hier_link_parent_placeholder') }}">
                <datalist id="hl-parent-list">
                    @foreach($parents as $pn)<option value="{{ $pn->node_name }} ({{ $pn->level_name }})" label="{{ $pn->node_code ? $pn->node_code.' · ' : '' }}{{ $pn->node_name }} ({{ $pn->level_name }})">@endforeach
                </datalist>
            </div>
            @endif
        </div>
        @if($parent && ! ($standalone ?? false))
        @include('admin.cbe-kpi.hierarchy-nodes._search-boxes')
        @endif
    </form>

    <form id="hl-form" method="POST" action="{{ route('admin.cbe-kpi.hierarchy-link.store') }}" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:6px; margin:0;">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $parent->node_id ?? '' }}">
        <input type="hidden" name="return_query" value="{{ http_build_query(request()->except(['page'])) }}">

        @if($searched && $rows->isNotEmpty())
        <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
            <span id="hl-count" style="font-size:9.5px; font-weight:700; color:var(--gl-blue);"></span>
            <span style="display:flex; gap:8px;">
                <button type="button" class="hl-btn-outline" onclick="hlTickAll(true)">{{ __('cbe_masterfile.cov_tick_all') }}</button>
                <button type="button" class="hl-btn-outline" onclick="hlTickAll(false)">{{ __('cbe_masterfile.cov_untick_all') }}</button>
            </span>
        </div>
        @endif

        <div id="hl-wrap" style="flex:1; min-height:0; overflow:hidden; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
            @if(! $group)
            <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.hier_link_pick_group') }}</div>
            @elseif(! $parent)
            <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.hier_link_pick_parent') }}</div>
            @elseif($standalone ?? false)
            <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.hier_link_standalone') }}</div>
            @elseif(! $searched)
            <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.search_first_go') }}</div>
            @else
            <table class="hl-table" id="hl-table">
                <thead><tr>
                    <th style="width:50px; text-align:center;">{{ __('cbe_masterfile.link_col_link') }}</th>
                    <th style="width:40px;">#</th>
                    <th>{{ __('cbe_masterfile.search_entity_code') }}</th>
                    <th>{{ __('cbe_masterfile.cov_col_entity') }}</th>
                    <th>{{ __('cbe_masterfile.form_level') }}</th>
                    <th>{{ __('cbe_masterfile.form_postcode') }}</th>
                    <th>{{ __('cbe_masterfile.form_city') }}</th>
                    <th>{{ __('cbe_masterfile.link_col_current') }}</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    @php
                        $isFam = ($r->kind ?? 'own') === 'fam';
                        $elsewhere = $isFam && $r->affiliated_node_id && $r->affiliated_node_id !== $parent->node_id;
                    @endphp
                    <tr class="hl-row{{ $elsewhere ? ' hl-off' : '' }}">
                        <td style="text-align:center;">
                            @if($isFam)
                                @if($elsewhere)
                                <input type="checkbox" disabled>
                                @else
                                <input type="hidden" name="aff_shown[]" value="{{ $r->node_id }}">
                                <input type="checkbox" name="aff_ticked[]" value="{{ $r->node_id }}" checked>
                                @endif
                            @else
                            <input type="hidden" name="shown_ids[]" value="{{ $r->node_id }}">
                            <input type="checkbox" name="linked_ids[]" value="{{ $r->node_id }}" checked>
                            @endif
                        </td>
                        <td style="color:#9ca3af;">{{ $loop->iteration }}</td>
                        <td style="white-space:nowrap;">{{ $r->node_code ?: '—' }}</td>
                        <td style="font-weight:600; color:#1565C0;">{{ $r->node_name }}@if($r->node_name_zh) <span style="color:#6b7280; font-weight:400;">{{ $r->node_name_zh }}</span>@endif</td>
                        <td style="white-space:nowrap;">{{ $r->level_name }}</td>
                        <td style="white-space:nowrap;">{{ $r->postcode ?: '—' }}</td>
                        <td>{{ $r->city ?: '—' }}</td>
                        <td>
                            @if($isFam)
                                {{ $r->affiliated_node_id === $parent->node_id ? $parent->node_name : ($elsewhere ? __('cbe_masterfile.hier_link_already', ['name' => $r->aff_name]) : __('cbe_masterfile.link_not_linked')) }}
                            @else
                                {{ $r->parent_node_id === $parent->node_id ? $parent->node_name : ($r->parent_name ?: __('cbe_masterfile.link_not_linked')) }}
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_cbe_directory.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            @endif
        </div>

        <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            <button type="button" class="hl-btn" onclick="hlPrev()">{{ __('masterfile.prev') }}</button>
            <span id="hl-page" style="font-size:9.5px; color:#4b5563; font-weight:600;"></span>
            <button type="button" class="hl-btn" id="hl-next" onclick="hlNext()" @if(! $searched || $rows->isEmpty()) style="visibility:hidden;" @endif>{{ __('masterfile.next') }}</button>
        </div>
    </form>
</div>

@php
    $hlParentMap = $parents->mapWithKeys(fn ($pn) => [$pn->node_name.' ('.$pn->level_name.')' => $pn->node_id]);
    $hlPrevUrl = $searched
        ? route('admin.cbe-kpi.hierarchy-link', array_filter(['group' => $group->group_label_id ?? null, 'parent_id' => $parent->node_id ?? null]))
        : route('admin.cbe-kpi');
@endphp
<script>
(function(){
    // Link To (Parent): picking a name from the type-ahead list selects it.
    var parentMap = @json($hlParentMap);
    var pText = document.getElementById('hl-parent-text');
    if (pText) {
        pText.addEventListener('change', function(){
            var id = parentMap[this.value];
            if (id) {
                document.getElementById('hl-parent-id').value = id;
                var f = document.getElementById('hl-search');
                f.querySelectorAll('input[name=go]').forEach(function(g){ g.disabled = true; });
                f.submit();
            }
        });
    }

    var prevUrl = @json($hlPrevUrl);
    var T = { next: @json(__('masterfile.next')), save: @json(__('cbe_masterfile.btn_save_node')), range: @json(__('masterfile.showing_records')), sel: @json(__('cbe_masterfile.cov_selected')) };
    var wrap = document.getElementById('hl-wrap');
    var table = document.getElementById('hl-table');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.hl-row'));
    var page = 0, pages = [[]], starts = [0];

    function count(){
        var n = 0;
        rows.forEach(function(r){ var cb = r.querySelector('input[type=checkbox]'); if (cb.checked) n++; r.classList.toggle('hl-off', !cb.checked || cb.disabled); });
        var c = document.getElementById('hl-count');
        if (c) c.textContent = T.sel.replace(':count', n).replace(':total', rows.length);
    }
    function show(){
        rows.forEach(function(r){ r.style.display = 'none'; });
        (pages[page] || []).forEach(function(r){ r.style.display = ''; });
        // CHANGED 27 Sep 2026 — per Chris: record range, not page numbers.
        document.getElementById('hl-page').textContent = rows.length ? T.range.replace(':first', starts[page] + 1).replace(':last', starts[page] + (pages[page] || []).length).replace(':total', rows.length) : '';
        document.getElementById('hl-next').textContent = (page >= pages.length - 1) ? T.save : T.next;
    }
    function build(){
        if (!table || !rows.length) return;
        rows.forEach(function(r){ r.style.display = ''; });
        var avail = wrap.clientHeight - table.querySelector('thead').offsetHeight - 4;
        pages = [[]]; starts = [0]; var used = 0, idx = 0;
        rows.forEach(function(r){
            var h = r.offsetHeight;
            if (used + h > avail && pages[pages.length - 1].length) { pages.push([]); starts.push(idx); used = 0; }
            pages[pages.length - 1].push(r); used += h; idx++;
        });
        if (page > pages.length - 1) page = pages.length - 1;
        show();
    }
    window.hlPrev = function(){ if (page > 0) { page--; show(); return; } window.location.href = prevUrl; };
    window.hlNext = function(){ if (page < pages.length - 1) { page++; show(); return; } document.getElementById('hl-form').submit(); };
    window.hlTickAll = function(on){ rows.forEach(function(r){ var cb = r.querySelector('input[type=checkbox]'); if (!cb.disabled) cb.checked = on; }); count(); };
    rows.forEach(function(r){ r.querySelector('input[type=checkbox]').addEventListener('change', count); });
    count(); build();
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(build, 300); });
})();
</script>
@endsection
