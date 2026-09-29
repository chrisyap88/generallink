{{-- NEW 26 Sep 2026 — per Chris: one box per search type, never one box
     for everything. City [type-ahead pick list] | Postcode From / To |
     Entity Code From / To | Entity Name [type-ahead pick list] | GO.
     Shared by Entity Maintenance (Search / View / Edit) and Entity
     Hierarchy Link. Expects $cityList, $nameList; the including form
     supplies its own hidden fields. --}}
<style>
.esb-row{display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;}
.esb-field{display:flex; flex-direction:column; gap:3px; min-width:0;}
.esb-field label{font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; white-space:nowrap;}
.esb-field input{font-size:11px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; box-sizing:border-box; width:100%;}
.esb-pair{display:flex; align-items:center; gap:4px;}
.esb-pair span{font-size:9px; color:#6b7280; font-weight:700;}
.esb-go{background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:7px 22px; font-size:11px; font-weight:700; cursor:pointer;}
</style>
<input type="hidden" name="go" value="1">
{{-- NEW 26 Sep 2026 — per Chris: every search box has type-ahead. --}}
<datalist id="esb-pc-list">@foreach(($postcodeList ?? []) as $pc)<option value="{{ $pc['v'] }}" label="{{ $pc['v'] }} · {{ $pc['l'] }}">@endforeach</datalist>
<datalist id="esb-code-list">@foreach(($codeList ?? []) as $cd)<option value="{{ $cd['v'] }}" label="{{ $cd['v'] }} · {{ $cd['l'] }}">@endforeach</datalist>
<div class="esb-row">
    {{-- NEW 27 Sep 2026 — Entity Maintenance: CBE Group → HQ → State → Branch first. --}}
    @if($tierBoxes ?? false)
    @include('admin.cbe-kpi.hierarchy-nodes._tier-boxes')
    @endif
    @unless(($tierBoxes ?? false) && ! ($group ?? null))
    {{-- NEW 27 Sep 2026 — per Chris: District box first — picks every
         town / postcode of the district (e.g. Klang, Selangor). --}}
    @if($showDistrictBox ?? false)
    <div class="esb-field" style="flex:1 1 150px;">
        <label>{{ __('cbe_masterfile.form_district') }}</label>
        <input type="text" name="district" value="{{ request('district') }}" list="esb-district-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.district_type_hint') }}">
        <datalist id="esb-district-list">@foreach(($districtList ?? []) as $dt)<option value="{{ $dt['v'] }}" label="{{ $dt['v'] }} · {{ $dt['l'] }}">@endforeach</datalist>
    </div>
    @endif
    {{-- NEW 26 Sep 2026 — per Chris: State parent adds a Branch box; HQ
         parent adds State + Branch boxes (type-ahead pick lists). --}}
    @foreach(($filterLevels ?? collect()) as $fl)
    <div class="esb-field" style="flex:1 1 140px;">
        <label>{{ $fl->level_name }}</label>
        <input type="text" name="lv[{{ $fl->level_id }}]" value="{{ request('lv.'.$fl->level_id) }}" list="esb-lv-list-{{ $loop->index }}" autocomplete="off">
        <datalist id="esb-lv-list-{{ $loop->index }}">@foreach(($levelLists[$fl->level_id] ?? []) as $lvName)<option value="{{ $lvName['v'] }}" label="{{ $lvName['l'] !== '' ? $lvName['l'].' · ' : '' }}{{ $lvName['v'] }}">@endforeach</datalist>
    </div>
    @endforeach
    @if($showCityBox ?? true)
    <div class="esb-field" style="flex:1 1 160px; position:relative;">
        <label>{{ __('cbe_masterfile.form_city') }}</label>
        <input type="text" name="city" id="esb-city" value="{{ request('city') }}" autocomplete="off" placeholder="{{ __('cbe_masterfile.city_type_hint') }}">
        <div id="esb-city-picked" style="font-size:8.5px; color:var(--gl-blue); font-weight:700; min-height:10px;"></div>
        <div id="esb-city-hidden">@foreach((array) request('city_pc', []) as $cpc)<input type="hidden" name="city_pc[]" value="{{ $cpc }}">@endforeach</div>
    </div>
    @endif
    <div class="esb-field" style="flex:0 0 200px;">
        <label>{{ __('cbe_masterfile.form_postcode') }}</label>
        <div class="esb-pair">
            <input type="text" name="pc_from" value="{{ request('pc_from') }}" maxlength="5" list="esb-pc-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.form_coverage_postcode_from') }}">
            <span>–</span>
            <input type="text" name="pc_to" value="{{ request('pc_to') }}" maxlength="5" list="esb-pc-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.form_coverage_postcode_to') }}">
        </div>
    </div>
    @unless($hideCodeBox ?? false)
    <div class="esb-field" style="flex:0 0 220px;">
        <label>{{ __('cbe_masterfile.search_entity_code') }}</label>
        <div class="esb-pair">
            <input type="text" name="code_from" value="{{ request('code_from') }}" list="esb-code-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.form_coverage_postcode_from') }}">
            <span>–</span>
            <input type="text" name="code_to" value="{{ request('code_to') }}" list="esb-code-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.form_coverage_postcode_to') }}">
        </div>
    </div>
    @endunless
    <div class="esb-field" style="flex:2 1 200px;">
        <label>{{ __('cbe_masterfile.cov_col_entity') }}</label>
        <input type="text" name="name" value="{{ request('name') }}" list="esb-name-list" autocomplete="off">
        <datalist id="esb-name-list">@foreach($nameList as $nm)<option value="{{ $nm['v'] }}" label="{{ $nm['l'] !== '' ? $nm['l'].' · ' : '' }}{{ $nm['v'] }}">@endforeach</datalist>
    </div>
    <button type="submit" class="esb-go">{{ __('cbe_masterfile.search_go') }}</button>
    @endunless
</div>

{{-- NEW 27 Sep 2026 — per Chris: City search results on ONE screen: every
     matching town / area with a tick box (all ticked by default), [All] /
     [None], untick any to leave it out. Compact rows, no scrolling — Prev /
     Next pages inside the panel. [OK] keeps the ticked ones for GO. --}}
<style>
#esb-city-panel{display:none; position:fixed; left:50%; top:50%; transform:translate(-50%,-50%); width:min(1100px, 94vw); height:min(560px, 86vh); background:#fff; border:1px solid var(--gl-cyan2); border-radius:10px; box-shadow:0 10px 40px rgba(0,0,0,.25); z-index:500; flex-direction:column; padding:10px 12px; box-sizing:border-box; gap:6px;}
#esb-city-grid{flex:1; min-height:0; overflow:hidden; display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); grid-auto-rows:min-content; column-gap:14px; row-gap:0; align-content:start;}
.esb-cr{display:flex; align-items:center; gap:5px; font-size:10px; line-height:1.2; padding:1px 0; color:#263238; white-space:nowrap; cursor:pointer;}
.esb-cr input{width:12px; height:12px; margin:0; accent-color:var(--gl-blue); flex-shrink:0;}
.esb-cr .pc{color:var(--gl-blue); font-weight:700;}
.esb-cr .tw{color:#94A3B8;}
.esb-cr.off{color:#b0b8c4;}
.esb-pbtn{background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:5px 16px; font-size:10px; font-weight:700; cursor:pointer;}
.esb-obtn{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;}
</style>
<div id="esb-city-panel">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; flex-shrink:0;">
        <div style="font-size:11.5px; font-weight:700; color:#263238;">{{ __('cbe_masterfile.city_panel_title') }} "<span id="esb-city-q"></span>"</div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span id="esb-city-count" style="font-size:9.5px; font-weight:700; color:var(--gl-blue);"></span>
            <button type="button" class="esb-obtn" onclick="esbCityAll(true)">{{ __('cbe_masterfile.link_all') }}</button>
            <button type="button" class="esb-obtn" onclick="esbCityAll(false)">{{ __('cbe_masterfile.cov_untick_all') }}</button>
        </div>
    </div>
    <div id="esb-city-grid"></div>
    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <button type="button" class="esb-pbtn" onclick="esbCityPage(-1)">{{ __('masterfile.prev') }}</button>
        <span id="esb-city-page" style="font-size:9.5px; color:#4b5563; font-weight:600;"></span>
        <span style="display:flex; gap:8px;">
            <button type="button" class="esb-pbtn" onclick="esbCityPage(1)">{{ __('masterfile.next') }}</button>
            <button type="button" class="esb-pbtn" onclick="esbCityDone()">{{ __('cbe_masterfile.city_panel_ok') }}</button>
        </span>
    </div>
</div>
<script>
(function(){
    var box = document.getElementById('esb-city');
    if (!box) return;
    var panel = document.getElementById('esb-city-panel'), grid = document.getElementById('esb-city-grid');
    var hidden = document.getElementById('esb-city-hidden'), picked = document.getElementById('esb-city-picked');
    var url = @json(route('admin.cbe-kpi.place-lookup'));
    var T = { page: @json(__('cbe_masterfile.cov_page')), sel: @json(__('cbe_masterfile.cov_selected')), pcs: @json(__('cbe_masterfile.city_panel_pcs')) };
    var rows = [], page = 0, per = 60, t;

    function esc(x){ var d = document.createElement('div'); d.textContent = x == null ? '' : x; return d.innerHTML; }
    function count(){
        var n = rows.filter(function(r){ return r.on; }).length;
        document.getElementById('esb-city-count').textContent = T.sel.replace(':count', n).replace(':total', rows.length);
    }
    function render(){
        var pages = Math.max(1, Math.ceil(rows.length / per));
        if (page > pages - 1) page = pages - 1;
        grid.innerHTML = '';
        rows.slice(page * per, (page + 1) * per).forEach(function(r){
            var el = document.createElement('label');
            el.className = 'esb-cr' + (r.on ? '' : ' off');
            el.innerHTML = '<input type="checkbox"' + (r.on ? ' checked' : '') + '><span class="pc">' + esc(r.pc) + '</span><span>' + esc(r.v) + '</span><span class="tw">' + esc(r.town ? r.town : r.state) + '</span>';
            el.querySelector('input').addEventListener('change', function(){ r.on = this.checked; el.classList.toggle('off', !r.on); count(); });
            grid.appendChild(el);
        });
        document.getElementById('esb-city-page').textContent = T.page.replace(':page', page + 1).replace(':pages', pages);
        count();
    }
    function fit(){
        // rows per page = what fits the panel (3 columns), no scrolling
        panel.style.display = 'flex';
        var probe = document.createElement('label'); probe.className = 'esb-cr'; probe.innerHTML = '<input type="checkbox"><span>X</span>';
        grid.appendChild(probe);
        var h = probe.offsetHeight || 16; grid.removeChild(probe);
        per = Math.max(3, Math.floor(grid.clientHeight / h)) * 3;
    }
    window.esbCityPage = function(d){ var pages = Math.ceil(rows.length / per); var n = page + d; if (n < 0 || n > pages - 1) return; page = n; render(); };
    window.esbCityAll = function(on){ rows.forEach(function(r){ r.on = on; }); render(); };
    window.esbCityDone = function(){
        var set = {};
        rows.forEach(function(r){ if (r.on) r.pcs.forEach(function(p){ set[p] = 1; }); });
        var pcs = Object.keys(set).sort();
        hidden.innerHTML = '';
        pcs.forEach(function(p){ var i = document.createElement('input'); i.type = 'hidden'; i.name = 'city_pc[]'; i.value = p; hidden.appendChild(i); });
        if (!pcs.length) { var i = document.createElement('input'); i.type = 'hidden'; i.name = 'city_pc[]'; i.value = 'none'; hidden.appendChild(i); }
        picked.textContent = T.pcs.replace(':count', pcs.length) + (pcs.length ? ': ' + pcs.join(', ') : '');
        panel.style.display = 'none';
    };
    function open(q){
        fetch(url + '?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(list){
                if (!list.length) { panel.style.display = 'none'; return; }
                rows = list.map(function(r){ r.on = true; return r; });
                page = 0;
                document.getElementById('esb-city-q').textContent = q;
                fit(); render();
            }).catch(function(){});
    }
    box.addEventListener('input', function(){
        clearTimeout(t);
        hidden.innerHTML = ''; picked.textContent = '';
        var q = box.value.trim();
        if (q.length < 2) { panel.style.display = 'none'; return; }
        t = setTimeout(function(){ open(q); }, 400);
    });
    var cur = hidden.querySelectorAll('input');
    if (cur.length) { picked.textContent = T.pcs.replace(':count', cur.length); }
})();
</script>
