@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.tab_affiliate_group'))

@section('content')
{{-- CHANGED 27 Sep 2026 — per Chris: "Affiliate Group" of a CBE.
     No search boxes — the list appears at once. Entities are found
     automatically (the HQ's entities in this CBE's district) and saved
     automatically ("New"). Every tick / untick AUTO-SAVES. Compact rows in
     2 columns (☑ | # | Code | Name | Postcode | City), no truncation, no
     scroll. Bottom bar: Prev (left) · "Showing a–b of N records" · Next
     (right), bold blue. Clicking a name opens its View / Edit; Prev there
     returns here. --}}
<style>
.ag-grid{display:grid; grid-template-columns:1fr 1fr; column-gap:14px; align-content:start;}
/* FIXED 27 Sep 2026 — Name always gets room; the other columns take only what they need. */
.ag-head,.ag-row{display:grid; grid-template-columns:18px 2.6em minmax(0,1fr) var(--ag-cityw, 8em) var(--ag-pcw, 4.2em); /* 27 Sep 2026 — City then Postcode */ column-gap:6px; align-items:center; font-size:10.5px; color:#263238; padding:3px 6px; border-bottom:1px solid #f1f5f9;}
.ag-head{font-size:9px; font-weight:700; color:#374151; background:#f0f9ff; border-bottom:1px solid #d1d5db;}
.ag-row input{width:13px; height:13px; margin:0; cursor:pointer; accent-color:var(--gl-blue);}
.ag-row .nm{color:#1565C0; font-weight:600; text-decoration:none; line-height:1.25;}
.ag-row > span{white-space:nowrap; overflow:hidden;}
.ag-row .zh{font-size:inherit;}
.ag-row .note{display:inline; margin-left:6px;}
.ag-row .zh{color:#6b7280; font-weight:400;}
.ag-row .tag{font-size:8.5px; font-weight:700; color:#fff; background:#2e7d32; border-radius:8px; padding:0 5px; margin-left:4px;}
.ag-row .note{font-size:8.5px; color:#94A3B8;}
.ag-row.off{color:#94A3B8;} .ag-row.off .nm{color:#94A3B8;}
.ag-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700; cursor:pointer;}
</style>

<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; box-sizing:border-box; display:flex; flex-direction:column; gap:6px;">

    <div style="flex-shrink:0; display:flex; align-items:baseline; justify-content:space-between; gap:12px; padding-right:58px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $group->group_name }} — {{ __('masterfile.tab_affiliate_group') }} (<span id="ag-total">{{ number_format($total) }}</span>)</div>
        <div style="font-size:9.5px; font-weight:700; white-space:nowrap;">
            @if($newCount > 0)<span style="color:#2e7d32;">{{ __('cbe_masterfile.ag_new_found', ['count' => $newCount]) }}</span>&nbsp;&nbsp;@endif
            <span id="ag-status" style="color:#2e7d32;"></span>
        </div>
    </div>

    <div id="ag-wrap" style="flex:1; min-height:0; overflow:hidden; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:4px 6px;">
        @if($rows->isEmpty())
        <div style="padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ __('cbe_masterfile.ag_none') }}</div>
        @else
        <div class="ag-grid" id="ag-grid">
            @foreach([0, 1] as $col)
            <div class="ag-col" id="ag-col{{ $col }}" style="min-width:0;">
                <div class="ag-head"><span></span><span>#</span><span>{{ __('cbe_masterfile.cov_col_entity') }}</span><span>{{ __('cbe_masterfile.form_city') }}</span><span>{{ __('cbe_masterfile.form_postcode') }}</span></div>
            </div>
            @endforeach
        </div>
        <div id="ag-rows" style="display:none;">
            @foreach($rows as $r)
            @php
                $on = in_array($r->kind, ['own', 'up', 'aff'], true);
                $locked = $r->kind === 'own' || $r->kind === 'elsewhere';
            @endphp
            <div class="ag-row{{ $on ? '' : ' off' }}" data-node="{{ $r->node_id }}" data-anchor="{{ $r->anchor }}" data-kind="{{ $r->kind }}">
                <input type="checkbox" {{ $on ? 'checked' : '' }} {{ $locked ? 'disabled' : '' }} title="{{ $r->kind === 'own' ? __('cbe_masterfile.group_entities_own_hint') : '' }}">
                <span style="color:#9ca3af;">{{ $loop->iteration }}</span>
                <span>
                    <a class="nm" href="{{ route('admin.cbe-kpi.hierarchy-nodes.edit', ['node' => $r->node_id, 'return' => request()->fullUrl()]) }}">{{ $r->node_name }}@if($r->node_name_zh) <span class="zh">{{ $r->node_name_zh }}</span>@endif</a>@if($r->is_new)<span class="tag">{{ __('cbe_masterfile.ag_new') }}</span>@endif
                    @if($r->kind === 'elsewhere')<span class="note">{{ __('cbe_masterfile.hier_link_already', ['name' => $r->aff_name]) }}</span>@elseif($r->kind !== 'aff' && $r->level_name && $r->level_name !== ($rows->first()->level_name ?? null))<span class="note">{{ $r->level_name }}</span>@endif
                </span>
                <span>{{ $r->city ?: '—' }}</span>
                <span>{{ $r->postcode ?: '—' }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        <button type="button" class="ag-btn" onclick="agPrev()">{{ __('masterfile.prev') }}</button>
        <span id="ag-range" style="font-size:10px; color:#4b5563; font-weight:600;"></span>
        <button type="button" class="ag-btn" onclick="agNext()">{{ __('masterfile.next') }}</button>
    </div>
</div>

<script>
(function(){
    var backUrl = @json($backUrl);
    var toggleUrl = @json(route('admin.cbe-kpi.group-entities.toggle'));
    var groupId = @json($group->group_label_id);
    var csrf = @json(csrf_token());
    var T = { range: @json(__('masterfile.showing_records')), saved: @json(__('cbe_masterfile.ag_saved')), fail: @json(__('cbe_masterfile.ag_save_failed')) };
    var grid = document.getElementById('ag-grid'), wrap = document.getElementById('ag-wrap');
    var rows = Array.prototype.slice.call(document.querySelectorAll('#ag-rows .ag-row'));
    var page = 0, pages = [], starts = [];

    var colEls = [document.getElementById('ag-col0'), document.getElementById('ag-col1')];
    var nCols = 2;
    function build(){
        if (!grid) { document.getElementById('ag-range').textContent = ''; return; }
        // FIXED 27 Sep 2026 — 2 columns only when each column is wide enough
        // (zoomed-in / small screens get 1 column), so names never squeeze.
        nCols = 1; // CHANGED 27 Sep 2026 — per Chris: one column, one line per entity
        grid.style.gridTemplateColumns = nCols === 2 ? '1fr 1fr' : '1fr';
        colEls[1].style.display = nCols === 2 ? '' : 'none';
        // how many rows fit in ONE column (measured in the real column
        // width) -> 2 columns per screen, never taller than the box.
        var avail = wrap.clientHeight - colEls[0].querySelector('.ag-head').offsetHeight - 10;
        rows.forEach(function(r){ r.style.display = ''; colEls[0].appendChild(r); });
        // one line per entity, never cut, ONE font size for every row:
        // shrink the whole list's font together until the longest name fits.
        // (If a name still cannot fit at the smallest size, that row wraps —
        // never cut off.)
        // CHANGED 27 Sep 2026 — per Chris: no Entity Code; every row uses the
        // SAME fixed column widths so Postcode and City line up exactly.
        function widest(idx){
            var w = 0, cells = rows.map(function(r){ return r.children[idx]; });
            colEls.forEach(function(ce){ var hd = ce.querySelector('.ag-head'); if (hd) { hd.style.fontSize = ''; cells.push(hd.children[idx]); } });
            cells.forEach(function(c){ c.style.display = 'inline-block'; c.style.width = 'auto'; w = Math.max(w, c.scrollWidth); c.style.display = ''; c.style.width = ''; });
            return w + 4;
        }
        rows.forEach(function(r){ r.style.display = ''; r.style.fontSize = ''; });
        grid.style.setProperty('--ag-cityw', widest(3) + 'px');
        grid.style.setProperty('--ag-pcw', widest(4) + 'px');
        var listEl = colEls[0];
        listEl.style.fontSize = '';
        rows.forEach(function(r){ r.style.fontSize = ''; r.children[2].style.whiteSpace = ''; });
        var fs = parseFloat(getComputedStyle(rows[0]).fontSize);
        function overflowing(){ return rows.some(function(r){ var c = r.children[2]; return c.scrollWidth > c.clientWidth + 1; }); }
        while (overflowing() && fs > 7.5) { fs -= 0.25; rows.forEach(function(r){ r.style.fontSize = fs + 'px'; }); }
        rows.forEach(function(r){ var c = r.children[2]; if (c.scrollWidth > c.clientWidth + 1) { c.style.whiteSpace = 'normal'; } });
        colEls.forEach(function(ce){ var hd = ce.querySelector('.ag-head'); if (hd) hd.style.fontSize = fs + 'px'; });
        // keep Carolyn's bubble (fixed, top-right) off the list header
        var bub = document.getElementById('aiAssistantBubble');
        wrap.style.marginTop = '';
        if (bub) { var gap = bub.getBoundingClientRect().bottom - wrap.getBoundingClientRect().top; if (gap > 0) wrap.style.marginTop = (gap + 2) + 'px'; }
        avail = wrap.clientHeight - colEls[0].querySelector('.ag-head').offsetHeight - 10;
        var hs = rows.map(function(r){ return r.offsetHeight; });
        pages = []; starts = [];
        var i = 0;
        while (i < rows.length) {
            var start = i, cols = [[], []];
            for (var c = 0; c < nCols && i < rows.length; c++) {
                var used = 0;
                while (i < rows.length && (used + hs[i] <= avail || !cols[c].length)) { cols[c].push(rows[i]); used += hs[i]; i++; }
            }
            pages.push(cols); starts.push(start);
        }
        if (page > pages.length - 1) page = Math.max(0, pages.length - 1);
        show();
    }
    function show(){
        rows.forEach(function(r){ r.style.display = 'none'; });
        var cols = pages[page] || [[], []];
        cols.forEach(function(list, c){ list.forEach(function(r){ colEls[c].appendChild(r); r.style.display = ''; }); });
        var n = cols[0].length + cols[1].length;
        document.getElementById('ag-range').textContent = rows.length ? T.range.replace(':first', starts[page] + 1).replace(':last', starts[page] + n).replace(':total', rows.length) : '';
    }
    window.agPrev = function(){ if (page > 0) { page--; show(); return; } window.location.href = backUrl; };
    window.agNext = function(){ if (page < pages.length - 1) { page++; show(); } };

    // AUTO-SAVE every tick / untick.
    rows.forEach(function(r){
        var cb = r.querySelector('input[type=checkbox]');
        if (!cb || cb.disabled) return;
        cb.addEventListener('change', function(){
            var on = cb.checked, st = document.getElementById('ag-status');
            r.classList.toggle('off', !on);
            var body = new URLSearchParams({ group: groupId, node: r.dataset.node, anchor: r.dataset.anchor, kind: r.dataset.kind, on: on ? '1' : '0', _token: csrf });
            fetch(toggleUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
                .then(function(res){ return res.json().then(function(j){ return { ok: res.ok && j.ok, j: j }; }); })
                .then(function(x){
                    if (!x.ok) { cb.checked = !on; r.classList.toggle('off', on); st.style.color = '#c62828'; st.textContent = (x.j && x.j.message) || T.fail; return; }
                    if (r.dataset.kind === 'released' && on) r.dataset.kind = 'aff';
                    document.getElementById('ag-total').textContent = Number(x.j.total).toLocaleString();
                    st.style.color = '#2e7d32'; st.textContent = '✓ ' + T.saved;
                })
                .catch(function(){ cb.checked = !on; r.classList.toggle('off', on); st.style.color = '#c62828'; st.textContent = T.fail; });
        });
    });

    build();
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(build, 300); });
})();
</script>
@endsection
