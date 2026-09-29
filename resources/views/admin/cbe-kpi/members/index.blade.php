@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_directory.members_title'))

@section('content')
{{-- REBUILT 28 Sep 2026 — per Chris (§96.23): the entity Members tab is a view
     of the ONE member file, filtered to this entity.
     Members tab: Name, Mobile, Type tick boxes (any number), Plan, Fee Status,
     Joined From–To, GO → rows # | Member Name | Mobile | Types | Plan / Fee |
     Since | View / Edit (opens the member file record).
     + Add Member Here: search the member file by name / mobile first; pick the
     person and tick his types here (already-held types greyed); not found →
     Add New Person (same duplicate check), which comes back to this entity.
     Role, Account Status, Member Code, Agent Code and the one-choice Member
     Type dropdown were removed. Event Participation / Appointment unchanged. --}}
@include('admin.member-file._style')
@php
    $selfUrl = route('admin.cbe-kpi.members', ['node' => $node->node_id]);
    $listUrl = route('admin.cbe-kpi.members', ['node' => $node->node_id, 'panel' => 'list', 'do_search' => 1]);
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
    $ownTypes = \App\Services\MemberFileService::TYPES_OWNED;
    $pickTypes = array_merge($ownTypes, ['PRACTITIONER', 'COMMITTEE']);
    $ticked = (array) request('types', []);
@endphp
<style>
.em-row{display:flex; gap:8px 10px; align-items:flex-end; flex-wrap:wrap;}
.em-row .mf-f input,.em-row .mf-f select{padding:5px 7px;}
.em-types{display:flex; flex-wrap:wrap; gap:3px 10px;}
.em-types label{font-size:10.5px; color:#263238; display:flex; align-items:center; gap:4px; white-space:nowrap; text-transform:none; font-weight:600;}
.em-go{background:#1565C0; color:#fff; border:none; border-radius:20px; padding:7px 22px; font-size:11px; font-weight:700; cursor:pointer;}
</style>
<div class="mf-page">
    <div style="flex-shrink:0; padding-right:58px;">
        <div class="mf-title">{{ __('admin_cbe_directory.members_title') }} — {{ $nodePrimary }}{{ $nodeSecondary ? ' ('.$nodeSecondary.')' : '' }}</div>
    </div>

    @include('admin.cbe-kpi.partials.persistent-tabs', [
        'activeTab' => 'members',
        'primaryTabKey' => 'members',
        'primaryTabLabel' => __('admin_cbe_directory.members_title'),
        'primaryTabRoute' => 'admin.cbe-kpi.members',
    ])
    @if(session('member_saved'))<div class="mf-ok">✓ {{ session('member_saved') }}</div>@endif

    {{-- ===== first display: Add | Search / View / Edit (per Chris 28 Sep 2026) ===== --}}
    <div id="cbd-panel-home" style="display:{{ $panel === 'home' ? 'flex' : 'none' }}; flex-direction:column; flex:1; min-height:0; gap:7px;">
        <div class="mf-sub">{{ __('member_file.entity_landing_prompt') }}</div>
        <div class="mf-box" style="flex:0 0 auto;">
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $node->node_id, 'panel' => 'add']) }}" class="mf-btn-sq">{{ __('member_file.add_here') }}</a>
                <a href="{{ route('admin.cbe-kpi.members', ['node' => $node->node_id, 'panel' => 'list']) }}" class="mf-btn-sq">{{ __('member_file.search_view_edit') }}</a>
                <a href="{{ route('admin.member-file.join-qr', $node->node_id) }}" class="mf-btn-sq">{{ __('member_file.join_qr') }}</a>
            </div>
        </div>
    </div>

    {{-- ===== Search / View / Edit (this entity) ===== --}}
    <div id="cbd-panel-list" style="display:{{ $panel === 'list' ? 'flex' : 'none' }}; flex-direction:column; flex:1; min-height:0; gap:7px;">
        <form method="GET" action="{{ route('admin.cbe-kpi.members') }}" class="mf-box" style="flex:0 0 auto; margin:0; padding:8px 10px;" autocomplete="off">
            <input type="hidden" name="node" value="{{ $node->node_id }}">
            <input type="hidden" name="do_search" value="1">
            <input type="hidden" name="panel" value="list">
            <div class="em-row">
                <div class="mf-f" style="flex:2 1 160px;"><label>{{ __('member_file.name_search') }}</label><input name="name" value="{{ request('name') }}" list="em-dl-name" autocomplete="off" data-ta="name"><datalist id="em-dl-name"></datalist></div>
                <div class="mf-f" style="flex:1 1 110px;"><label>{{ __('member_file.mobile') }}</label><input name="mobile" value="{{ request('mobile') }}" list="em-dl-mobile" autocomplete="off" data-ta="mobile"><datalist id="em-dl-mobile"></datalist></div>
                @if($plans->isNotEmpty())
                <div class="mf-f" style="flex:1 1 110px;"><label>{{ __('member_file.plan') }}</label><select name="plan_id"><option value="">{{ __('member_file.any') }}</option>@foreach($plans as $pl)<option value="{{ $pl->id }}" @selected(request('plan_id') === $pl->id)>{{ $pl->plan_name }}</option>@endforeach</select></div>
                @endif
                {{-- CHANGED 28 Sep 2026 — per Chris: Fee Status → Membership Type, with + Add --}}
                <div class="mf-f" style="flex:1 1 150px;"><label>{{ __('member_file.membership_type') }}</label>
                    <div style="display:flex; gap:6px; align-items:center;"><select name="membership_type_id"><option value="">{{ __('member_file.any') }}</option>@foreach($memTypes as $mt)<option value="{{ $mt->id }}" @selected(request('membership_type_id') === $mt->id)>{{ $mt->label }}</option>@endforeach</select>
                    <a href="{{ route('admin.member-pick-lists.index', ['list' => 'MEMTYPE', 'return' => request()->fullUrl()]) }}" class="mf-btn" style="padding:5px 12px;">{{ __('member_file.add_type') }}</a></div></div>
                <div class="mf-f" style="flex:1 1 115px;"><label>{{ __('member_file.joined_from') }}</label><input type="date" name="joined_from" value="{{ request('joined_from') }}"></div>
                <div class="mf-f" style="flex:1 1 115px;"><label>{{ __('member_file.joined_to') }}</label><input type="date" name="joined_to" value="{{ request('joined_to') }}"></div>
            </div>
            <div class="em-row" style="margin-top:6px; justify-content:space-between; flex-wrap:nowrap;">
                <div class="mf-f" style="min-width:0;"><label>{{ __('member_file.types') }}</label>
                    <div class="em-types">@foreach($pickTypes as $t)<label><input type="checkbox" name="types[]" value="{{ $t }}" @checked(in_array($t, $ticked, true))> {{ $typeLabels[$t] ?? $t }}</label>@endforeach</div>
                </div>
                <button type="submit" class="em-go">GO</button>
            </div>
        </form>

        <div id="em-wrap" style="flex:1; min-height:0; overflow:hidden;">
            @if(! $doSearch)
                <div class="mf-box" style="text-align:center; color:#94A3B8; font-size:10px; padding:24px; flex:0 0 auto;">{{ __('member_file.search_first') }}</div>
            @else
            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
                <table class="mf-table" id="em-table" style="font-size:12px;">
                    <thead><tr><th>#</th><th>{{ __('member_file.col_name') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.types') }}</th><th>{{ __('member_file.plan_fee') }}</th><th>{{ __('member_file.since') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td style="color:#9ca3af;">{{ $rows->firstItem() + $loop->index }}</td>
                            <td style="font-weight:600; color:#1565C0;">{{ $r->full_name }}@if($r->second_name) <span style="color:#6b7280; font-weight:400;">{{ $r->second_name }}</span>@endif @if($r->nick_name)<span style="color:#6b7280; font-weight:400;">({{ $r->nick_name }})</span>@endif</td>
                            <td>{{ $r->phone ?: '—' }}</td>
                            <td>@foreach($r->lines as $l)<span class="mf-tag {{ ! empty($l['module']) ? 'pr' : '' }}" style="cursor:default;">{{ $l['type'] === 'COMMITTEE' ? ($l['label'] ?? $typeLabels['COMMITTEE']) : ($typeLabels[$l['type']] ?? $l['type']) }}</span>@endforeach</td>
                            <td>@if($r->member && $r->member['plan']){{ $r->member['plan'] }} · <span class="mf-fee {{ $r->member['fee_status'] }}">{{ __('member_file.fee_'.$r->member['fee_status']) }}</span>@else — @endif</td>
                            <td>{{ $fmt($r->since) }}</td>
                            <td><a href="{{ route('admin.member-file.edit', ['id' => $r->agent_id, 'return' => request()->fullUrl()]) }}" style="color:#1565C0; font-weight:700; text-decoration:none;">{{ __('member_file.view_edit') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_cbe_directory.no_results') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- ===== + Add Member Here ===== --}}
    <div id="cbd-panel-add" style="display:{{ $panel === 'add' ? 'flex' : 'none' }}; flex-direction:column; flex:1; min-height:0; gap:7px;">
        <form method="GET" action="{{ route('admin.cbe-kpi.members') }}" class="mf-box" style="flex:0 0 auto; margin:0; padding:8px 10px;" autocomplete="off">
            <input type="hidden" name="node" value="{{ $node->node_id }}">
            <input type="hidden" name="panel" value="add">
            <div class="mf-sub" style="margin-bottom:5px;">{{ __('member_file.add_here_help') }}</div>
            <div class="em-row">
                <div class="mf-f" style="flex:2 1 180px;"><label>{{ __('member_file.name_search') }}</label><input name="find_name" value="{{ request('find_name') }}" list="em-dl-find_name" autocomplete="off" data-ta="name"><datalist id="em-dl-find_name"></datalist></div>
                <div class="mf-f" style="flex:1 1 130px;"><label>{{ __('member_file.mobile') }}</label><input name="find_mobile" value="{{ request('find_mobile') }}" list="em-dl-find_mobile" autocomplete="off" data-ta="mobile"><datalist id="em-dl-find_mobile"></datalist></div>
                <button type="submit" class="em-go">GO</button>
            </div>
        </form>
        <div class="mf-box" style="position:relative; display:flex; flex-direction:column; gap:6px;">
            @if($found === null)
                <div class="mf-sub" style="text-align:center; padding:18px;">{{ __('member_file.search_first') }}</div>
            @else
                <div id="em-found-wrap" style="flex:1; min-height:0; overflow:hidden;">
                <table class="mf-table" id="em-found">
                    <thead><tr><th>#</th><th>{{ __('member_file.col_name') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.col_affiliated') }}</th><th>{{ rtrim(__('member_file.already_here'), ':：') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($found as $i => $a)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td style="font-weight:600; color:#1565C0;">{{ $a->full_name }}@if($a->nick_name) ({{ $a->nick_name }})@endif</td>
                            <td>{{ $a->phone ?: '—' }}</td>
                            <td>{{ $a->affiliated ? implode(', ', $a->affiliated) : __('member_file.not_affiliated') }}</td>
                            <td>{{ $a->here ? implode(', ', $a->here) : '—' }}</td>
                            <td><a href="#" style="color:#1565C0; font-weight:700; text-decoration:none;" onclick="emPick({{ json_encode(['id' => $a->agent_id, 'name' => $a->full_name, 'have' => $a->hereCodes]) }}); return false;">{{ __('member_file.select_person') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:14px; text-align:center; color:#9ca3af;">{{ __('member_file.not_found_here') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
                <div style="flex-shrink:0; display:flex; align-items:center; gap:10px;">
                    <span class="mf-sub">{{ __('member_file.not_in_list') }}</span>
                    <a href="{{ route('admin.member-file.create', ['node' => $node->node_id, 'return' => $listUrl]) }}" class="mf-btn">+ {{ __('member_file.add_new_person') }}</a>
                </div>

                {{-- tick the types for the picked person --}}
                <form id="em-add-box" method="POST" action="" style="display:none; position:absolute; top:8px; right:8px; width:min(480px, 96%); background:#fff; border:1px solid #93c5fd; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,.15); padding:10px 12px; box-sizing:border-box; z-index:20;">
                    @csrf
                    <input type="hidden" name="node_id" value="{{ $node->node_id }}">
                    <input type="hidden" name="back" value="{{ $listUrl }}">
                    <div style="font-weight:700; font-size:11px;" id="em-add-name"></div>
                    <div class="mf-sub">{{ $nodePrimary }}</div>
                    <div style="font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-top:6px;">{{ __('member_file.tick_new') }}</div>
                    <div class="em-types" style="margin-top:3px;">@foreach($ownTypes as $t)<label><input type="checkbox" name="types[]" value="{{ $t }}" class="em-t"> {{ $typeLabels[$t] ?? $t }}</label>@endforeach</div>
                    @if($plans->isNotEmpty())
                    <div class="mf-f" id="em-planrow" style="display:none; margin-top:6px; max-width:60%;"><label>{{ __('member_file.plan') }}</label>
                        <select name="plan_id"><option value="">{{ __('member_file.select') }}</option>@foreach($plans as $pl)<option value="{{ $pl->id }}">{{ $pl->plan_name }} — {{ __('member_file.period_'.$pl->period) }}{{ (float) $pl->fee > 0 ? ' RM '.number_format((float) $pl->fee, 2) : '' }}</option>@endforeach</select></div>
                    @endif
                    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
                        <button type="button" class="mf-btn" style="background:#6b7280;" onclick="document.getElementById('em-add-box').style.display='none'">{{ __('member_file.done') }}</button>
                        <button type="submit" class="mf-btn">{{ __('member_file.save') }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    <div class="mf-bar" id="em-bar">
        @php($prevUrl = ($rows && ! $rows->onFirstPage()) ? $rows->previousPageUrl() : ($doSearch ? route('admin.cbe-kpi.members', ['node' => $node->node_id, 'panel' => 'list']) : ($panel === 'home' ? route('admin.cbe-kpi', ['node' => $node->node_id]) : $selfUrl)))
        <a href="{{ $prevUrl }}" class="mf-btn">{{ __('masterfile.prev') }}</a>
        <span style="font-size:10.5px; color:#4b5563;">@if($rows && $rows->total() > 0){{ __('masterfile.showing_records', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}@endif</span>
        @if($rows && $rows->hasMorePages())
            <a href="{{ $rows->nextPageUrl() }}" class="mf-btn">{{ __('masterfile.next') }}</a>
        @else
            <span class="mf-btn" style="cursor:default;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
</div>

<script>
(function(){
    // item 8: Name / Mobile type-ahead from the member file
    var url = @json(route('admin.member-file.person-lookup')), t = null;
    document.querySelectorAll('[data-ta]').forEach(function(inp){
        var dl = document.getElementById(inp.getAttribute('list'));
        inp.addEventListener('input', function(){
            clearTimeout(t); var v = inp.value.trim(); if (!v) return;
            t = setTimeout(function(){
                fetch(url + '?by=' + inp.dataset.ta + '&q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(rows){
                    dl.innerHTML = '';
                    rows.forEach(function(x){ var o = document.createElement('option'); o.value = inp.dataset.ta === 'mobile' ? x.phone : x.name; o.label = x.code + ' · ' + x.name + ' · ' + (x.phone || ''); dl.appendChild(o); });
                });
            }, 200);
        });
    });
})();
</script>
<script>
(function(){
    window.cbdSwitchPanel = function(p){
        document.getElementById('cbd-panel-list').style.display = p === 'list' ? 'flex' : 'none';
        document.getElementById('cbd-panel-add').style.display = p === 'add' ? 'flex' : 'none';
        document.getElementById('cbd-panel-home').style.display = p === 'home' ? 'flex' : 'none';
        Array.prototype.forEach.call(document.querySelectorAll('.cbd-tabtoggle'), function(el){
            var on = el.getAttribute('data-p') === p;
            el.classList.toggle('active', on);
            el.style.background = on ? 'var(--gl-blue)' : '#EEF2F7'; el.style.color = on ? '#fff' : '#475569';
        });
    };
    var addUrl = @json(route('admin.member-file.affiliation.add', '__ID__'));
    window.emPick = function(p){
        var f = document.getElementById('em-add-box'); if (!f) return;
        f.action = addUrl.replace('__ID__', p.id);
        document.getElementById('em-add-name').textContent = p.name;
        f.querySelectorAll('.em-t').forEach(function(c){ var h = p.have.indexOf(c.value) !== -1; c.checked = false; c.disabled = h; c.parentNode.style.opacity = h ? .45 : 1; });
        var pr = document.getElementById('em-planrow'); if (pr) pr.style.display = 'none';
        f.style.display = '';
    };
    document.querySelectorAll('.em-t').forEach(function(c){ c.addEventListener('change', function(){
        var pr = document.getElementById('em-planrow'); if (pr && c.value === 'MEMBER') pr.style.display = c.checked ? '' : 'none';
    }); });
    var af = document.getElementById('em-add-box');
    if (af) af.addEventListener('submit', function(e){ if (!af.querySelector('.em-t:checked')) e.preventDefault(); });
    // no scroll: one line per row (shrink together), then as many rows as fit
    function shrink(t, box){ if (!t || !box) return; var fs = parseFloat(getComputedStyle(t).fontSize); while ((t.scrollWidth > box.clientWidth + 1) && fs > 6.5) { fs -= 0.25; t.style.fontSize = fs + 'px'; } }
    // + Add Member Here: found people shown as many as fit; the bottom Prev / Next pages through them
    var fw = document.getElementById('em-found-wrap'), ft = document.getElementById('em-found');
    if (ft && fw && document.getElementById('cbd-panel-add').style.display !== 'none') {
        shrink(ft, fw);
        var fr = [].slice.call(ft.querySelectorAll('tbody tr')), fh = 0;
        fr.forEach(function(r){ fh = Math.max(fh, r.offsetHeight); });
        var fit = Math.max(1, Math.floor((fw.clientHeight - ft.querySelector('thead').offsetHeight - 2) / (fh || 1))), pg = 0;
        var bar = document.getElementById('em-bar'), bp = bar.children[0], bl = bar.children[1], bn = bar.children[2];
        var rng = {!! json_encode(__('masterfile.showing_records', ['first' => '__A__', 'last' => '__B__', 'total' => '__T__']), JSON_HEX_TAG) !!};
        var nb = document.createElement('a'); nb.className = 'mf-btn'; nb.href = '#'; nb.textContent = bn.textContent; bar.replaceChild(nb, bn);
        var pb = document.createElement('a'); pb.className = 'mf-btn'; pb.href = '#'; pb.textContent = bp.textContent; bar.replaceChild(pb, bp);
        function showPg(){
            fr.forEach(function(r, i){ r.style.display = (i >= pg * fit && i < (pg + 1) * fit) ? '' : 'none'; });
            bl.textContent = fr.length > 1 ? rng.replace('__A__', pg * fit + 1).replace('__B__', Math.min(fr.length, (pg + 1) * fit)).replace('__T__', fr.length) : '';
        }
        nb.onclick = function(e){ e.preventDefault(); if ((pg + 1) * fit < fr.length) { pg++; showPg(); } };
        pb.onclick = function(e){ e.preventDefault(); if (pg > 0) { pg--; showPg(); } else { window.location.href = @json($selfUrl); } };
        showPg();
    }
    var wrap = document.getElementById('em-wrap'), table = document.getElementById('em-table');
    @if($rows && $rows->total() > 0)
    shrink(table, wrap);
    var perPage = {{ $rows->perPage() }}, first = {{ $rows->firstItem() ?? 1 }}, total = {{ $rows->total() }};
    (function(){
        var rows = table.querySelectorAll('tbody tr'), head = table.querySelector('thead'), maxH = 0;
        rows.forEach(function(r){ maxH = Math.max(maxH, r.offsetHeight); });
        var fit = Math.max(3, Math.min(50, Math.floor((wrap.clientHeight - head.offsetHeight - 4) / maxH)));
        if (fit === perPage || (table.offsetHeight <= wrap.clientHeight && total <= perPage)) return;
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', fit); url.searchParams.set('page', Math.floor((first - 1) / fit) + 1);
        window.location.replace(url.toString());
    })();
    @endif
})();
</script>
@endsection
