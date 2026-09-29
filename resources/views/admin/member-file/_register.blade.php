{{-- NEW 28 Sep 2026 — Affiliation Register (per Chris §96.21).
     One line per entity; the types held there are blue chips. Practitioner
     (Practitioner Setup) and Committee (Committee tab) are grey — read live,
     changed only in their own module. Current | History folders.
     Add Affiliation = search first: pick the entity, see what he already holds
     there (from every module), then tick only the new types.
     This panel sits inside the member form, so End / Payment / Add are posted
     through a separate form built by script (no form inside a form). --}}
@php
    $tl = $typeLabels ?? [];
    $cur = []; $hist = [];
    foreach ($register as $r) {
        $a = array_values(array_filter($r['lines'], fn ($l) => $l['status'] === 'ACTIVE'));
        $e = array_values(array_filter($r['lines'], fn ($l) => $l['status'] !== 'ACTIVE'));
        $ord = array_flip(array_keys($tl));
        usort($a, fn ($x, $y) => ($ord[$x['type']] ?? 99) <=> ($ord[$y['type']] ?? 99));
        if ($a) { $cur[] = $r + ['show' => $a]; }
        foreach ($e as $l) { $hist[] = ['group' => $r['group'], 'entity' => $r['entity']] + $l; }
    }
    // committee positions show as a grey chip on the matching CBE group's rows
    $comByGroup = $committee->where('current', true)->groupBy('group_label_id');
    $retUrl = request()->fullUrlWithQuery(['panel' => 'register']);
    $retParam = (string) request('return', '');
    $fmt = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : '';
@endphp
<div style="display:flex; flex-direction:column; gap:6px; height:100%; min-height:0; position:relative;">
    <div style="display:flex; align-items:center; gap:14px; border-bottom:1px solid #e5e7eb; padding-bottom:4px;">
        <span class="mf-fold on" data-fold="cur" onclick="mfFold('cur')">{{ __('member_file.current') }} ({{ count($cur) }})</span>
        <span class="mf-fold" data-fold="hist" onclick="mfFold('hist')">{{ __('member_file.history') }} ({{ count($hist) }})</span>
        <span style="flex:1;"></span>
        @if($cur && ($practitionerTypes ?? collect())->isNotEmpty())
        <button type="button" class="mf-btn" onclick="mfPopClose(); document.getElementById('mf-prac-box').style.display='';">{{ __('member_file.add_practitioner') }}</button>
        @endif
        <button type="button" class="mf-btn" onclick="mfAffOpen()">+ {{ __('member_file.add_affiliation') }}</button>
    </div>

    <div id="mf-fold-cur" style="flex:1; min-height:0; overflow:hidden;">
        @if(! $cur && $comByGroup->isEmpty())
            <div class="mf-sub" style="padding:8px 0;">{{ __('member_file.no_register') }}</div>
        @else
        <table class="mf-table" id="mf-reg-table">
            <thead><tr><th>#</th><th>{{ __('member_file.group') }}</th><th>{{ __('member_file.entity') }}</th><th>{{ __('member_file.types') }}</th><th>{{ __('member_file.plan_fee') }}</th><th>{{ __('member_file.since') }}</th></tr></thead>
            <tbody>
            @foreach($cur as $i => $r)
                @php
                    $mem = collect($r['show'])->firstWhere('type', 'MEMBER');
                    $since = collect($r['show'])->pluck('from')->filter()->min();
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $r['group'] }}</td>
                    <td>{{ $r['entity'] }}@if($r['entity_zh'] && $r['entity_zh'] !== $r['entity']) {{ $r['entity_zh'] }}@endif</td>
                    <td>
                        @foreach($r['show'] as $l)
                            @if($l['module'] || (isset($scopeIds) && $scopeIds !== null && ! in_array($r['node_id'], $scopeIds, true)))
                                @if(($l['module'] ?? null) === 'practitioner' && ! empty($l['profile_id']) && auth('agent')->user()->role === 'ADMIN')
                                <a href="{{ route('admin.practitioners.edit', $l['profile_id']) }}" class="mf-tag pr" style="text-decoration:none; cursor:pointer;" title="{{ __('member_file.open_practitioner_setup') }}">{{ $tl[$l['type']] ?? $l['type'] }}@if(! empty($l['label'])) · {{ $l['label'] }}@endif ›</a>
                                @else
                                <span class="mf-tag pr" title="{{ __('member_file.set_in', ['where' => __('member_file.how_'.$l['how'])]) }}">{{ $tl[$l['type']] ?? $l['type'] }}@if(! empty($l['label'])) · {{ $l['label'] }}@endif</span>
                                @endif
                            @else
                                @php $lj = ['id' => $l['tag_id'], 'type' => $tl[$l['type']] ?? $l['type'], 'entity' => $r['entity'], 'member' => $l['type'] === 'MEMBER' && ! empty($l['plan_id']), 'how' => __('member_file.how_'.$l['how']), 'from' => $fmt($l['from'])]; @endphp
                                <span class="mf-tag" onclick="mfLineOpen({{ json_encode($lj) }})">{{ $tl[$l['type']] ?? $l['type'] }}</span>
                            @endif
                        @endforeach
                        @foreach($comByGroup->get($r['group_id'], collect()) as $c)
                            <span class="mf-tag pr" title="{{ __('member_file.set_in', ['where' => __('member_file.how_COMMITTEE_TAB')]) }}">{{ $c->position_label }}</span>
                        @endforeach
                    </td>
                    <td>@if($mem && $mem['plan']){{ $mem['plan'] }}@if(! empty($mem['mt_label'])) ({{ $mem['mt_label'] }})@endif @if(empty($mem['family_of']))· <span class="mf-fee {{ $mem['fee_status'] }}">{{ __('member_file.fee_'.$mem['fee_status']) }}</span>@endif @if($mem['paid_until'] && $mem['paid_until'] < '2099-01-01') <span class="mf-sub">{{ __('member_file.paid_until') }} {{ $fmt($mem['paid_until']) }}</span>@endif
                        {{-- item 29: Family membership / company of an SBE or Corporate membership --}}
                        @if(($mem['mt_code'] ?? null) === 'FAMILY' && ($mem['how'] ?? null) !== 'FAMILY')
                            · <a href="#" class="mf-link" onclick="mfFamilyOpen('{{ $mem['tag_id'] }}'); return false;">{{ __('member_file.family') }} ({{ $mem['family_count'] }})</a>
                        @endif
                        @if(! empty($mem['family_of']))<span class="mf-sub">· {{ __('member_file.covered_by', ['name' => $mem['family_of']]) }}</span>@endif
                        @if(in_array($mem['mt_code'] ?? null, ['SBE', 'CORPORATE'], true))
                            · <a href="#" class="mf-link" onclick="mfCompanyOpen('{{ $mem['tag_id'] }}'); return false;">{{ $mem['company'] ?: __('member_file.set_company') }}</a>
                        @endif
                    @else — @endif</td>
                    <td>{{ $fmt($since) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div id="mf-fold-hist" style="flex:1; min-height:0; overflow:hidden; display:none;">
        @if(! $hist)
            <div class="mf-sub" style="padding:8px 0;">—</div>
        @else
        <table class="mf-table">
            <thead><tr><th>#</th><th>{{ __('member_file.group') }}</th><th>{{ __('member_file.entity') }}</th><th>{{ __('member_file.types') }}</th><th>{{ __('member_file.from') }}</th><th>{{ __('member_file.to') }}</th><th>{{ __('member_file.how') }}</th></tr></thead>
            <tbody>
            @foreach($hist as $i => $h)
                <tr><td>{{ $i + 1 }}</td><td>{{ $h['group'] }}</td><td>{{ $h['entity'] }}</td><td><span class="mf-tag pr">{{ $tl[$h['type']] ?? $h['type'] }}</span></td><td>{{ $fmt($h['from']) }}</td><td>{{ $fmt($h['to']) }}</td><td>{{ __('member_file.how_'.$h['how']) }}</td></tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- chip box: End / Record Payment --}}
    <div id="mf-line-box" class="mf-pop" style="display:none;">
        <div style="font-weight:700; font-size:11px;" id="mf-line-title"></div>
        <div class="mf-sub" id="mf-line-sub"></div>
        <div id="mf-pay" style="display:none; margin-top:6px;">
            <div style="font-size:9.5px; font-weight:700; color:#263238; margin-bottom:4px;">{{ __('member_file.record_payment') }}</div>
            <div style="display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:6px;">
                <div class="mf-f"><label>{{ __('member_file.amount') }}</label><input id="mf-pay-amt" type="number" step="0.01" min="0"></div>
                <div class="mf-f"><label>{{ __('member_file.paid_on') }}</label><input id="mf-pay-on" type="date" value="{{ now()->toDateString() }}"></div>
                <div class="mf-f"><label>{{ __('member_file.receipt_no') }}</label><input id="mf-pay-rc"></div>
            </div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
            <button type="button" class="mf-btn" style="background:#c62828;" onclick="mfLineEnd()">{{ __('member_file.end') }}</button>
            <button type="button" class="mf-btn" id="mf-pay-btn" style="display:none;" onclick="mfLinePay()">{{ __('member_file.record_payment') }}</button>
            <button type="button" class="mf-btn" style="background:#6b7280;" onclick="mfPopClose()">{{ __('member_file.done') }}</button>
        </div>
    </div>

    {{-- ===== item 25: + Practitioner ===== --}}
    <div id="mf-prac-box" class="mf-pop" style="display:none;">
        <div style="font-weight:700; font-size:11px;">{{ __('member_file.add_practitioner') }}</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-top:6px;">
            <div class="mf-f"><label>{{ __('member_file.entity') }}</label><select id="mf-prac-node">@foreach($cur as $r)@if(! isset($scopeIds) || $scopeIds === null || in_array($r['node_id'], $scopeIds, true))<option value="{{ $r['node_id'] }}">{{ $r['entity'] }}</option>@endif @endforeach</select></div>
            <div class="mf-f"><label>{{ __('member_file.practitioner_type') }}</label><select id="mf-prac-type">@foreach(($practitionerTypes ?? collect()) as $pt)<option value="{{ $pt->id }}">{{ $pt->type_label }}</option>@endforeach</select></div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
            <button type="button" class="mf-btn" style="background:#6b7280;" onclick="mfPopClose()">{{ __('member_file.done') }}</button>
            <button type="button" class="mf-btn" onclick="mfPracSave()">{{ __('member_file.save') }}</button>
        </div>
    </div>
    {{-- ===== item 29: Family members ===== --}}
    @foreach($cur as $r)
        @php $fm = collect($r['show'])->first(fn ($l) => $l['type'] === 'MEMBER' && ($l['mt_code'] ?? null) === 'FAMILY' && ($l['how'] ?? null) !== 'FAMILY'); @endphp
        @if($fm)
        <div id="mf-fam-{{ $fm['tag_id'] }}" class="mf-pop" style="display:none;">
            <div style="font-weight:700; font-size:11px;">{{ __('member_file.family_members') }} — {{ $r['entity'] }}</div>
            <table class="mf-table" style="margin-top:4px;">
                <thead><tr><th>#</th><th>{{ __('member_file.col_name') }}</th><th>{{ __('member_file.relationship') }}</th><th>{{ __('member_file.col_mobile') }}</th><th></th></tr></thead>
                <tbody>
                @forelse(\App\Services\MemberFileService::familyOf($fm['tag_id']) as $k => $f)
                    <tr><td>{{ $k + 1 }}</td><td>{{ $f->full_name }} <span class="mf-sub">{{ $f->agent_code }}</span></td><td>{{ $f->relationship ?: '—' }}</td><td>{{ $f->phone }}</td>
                        <td><a href="#" class="mf-link" onclick="mfFamilyRemove('{{ $f->id }}'); return false;">{{ __('member_file.remove') }}</a></td></tr>
                @empty
                    <tr><td colspan="5" class="mf-sub">—</td></tr>
                @endforelse
                </tbody>
            </table>
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:6px; margin-top:6px;">
                <div class="mf-f"><label>{{ __('member_file.col_name') }}</label><input class="mf-fam-who" list="mf-fam-dl-{{ $fm['tag_id'] }}" placeholder="{{ __('member_file.pick_person') }}" autocomplete="off"><datalist id="mf-fam-dl-{{ $fm['tag_id'] }}"></datalist></div>
                <div class="mf-f"><label>{{ __('member_file.relationship') }}</label><select class="mf-fam-rel"><option value="">{{ __('member_file.select') }}</option>@foreach(($relations ?? collect()) as $rl)<option value="{{ $rl->id }}">{{ $rl->label }}</option>@endforeach</select></div>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
                <button type="button" class="mf-btn" style="background:#6b7280;" onclick="mfPopClose()">{{ __('member_file.done') }}</button>
                <button type="button" class="mf-btn" onclick="mfFamilyAdd('{{ $fm['tag_id'] }}')">{{ __('member_file.add_family') }}</button>
            </div>
        </div>
        @endif
    @endforeach
    {{-- ===== item 29: company of an SBE / Corporate membership ===== --}}
    <div id="mf-co-box" class="mf-pop" style="display:none;">
        <div style="font-weight:700; font-size:11px;">{{ __('member_file.set_company') }}</div>
        <div class="mf-sub">{{ __('member_file.new_company_hint') }}</div>
        <div style="display:grid; grid-template-columns:2fr 1fr; gap:6px; margin-top:6px;">
            <div class="mf-f"><label>{{ __('member_file.company_name') }}</label><input id="mf-co-name" list="mf-co-dl" autocomplete="off"><datalist id="mf-co-dl"></datalist></div>
            <div class="mf-f"><label>{{ __('member_file.registration_no') }}</label><input id="mf-co-reg"></div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
            <button type="button" class="mf-btn" style="background:#6b7280;" onclick="mfPopClose()">{{ __('member_file.done') }}</button>
            <button type="button" class="mf-btn" onclick="mfCompanySave()">{{ __('member_file.save') }}</button>
        </div>
    </div>

    {{-- Add Affiliation: search first --}}
    <div id="mf-aff-box" class="mf-pop" style="display:none;">
        <div style="font-weight:700; font-size:11px;">{{ __('member_file.add_affiliation') }}</div>
        <div class="mf-sub">{{ __('member_file.aff_search_first') }}</div>
        <div class="mf-f" style="margin-top:6px; position:relative;">
            <label>{{ __('member_file.entity') }}</label>
            <input id="mf-aff-q" autocomplete="off" placeholder="{{ __('member_file.entity') }} / {{ __('member_file.group') }}">
            <div id="mf-aff-list" class="mf-dd" style="display:none;"></div>
        </div>
        <div id="mf-aff-step2" style="display:none; margin-top:6px;">
            <div style="font-size:10px; font-weight:700; color:#1565C0;" id="mf-aff-picked"></div>
            <div id="mf-aff-have" style="font-size:9.5px; color:#374151; margin-top:3px;"></div>
            <div style="font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; margin-top:6px;">{{ __('member_file.tick_new') }}</div>
            <div id="mf-aff-types" style="display:flex; flex-wrap:wrap; gap:4px 12px; margin-top:3px;">
                @foreach($affTypes as $t)
                    <label style="font-size:10.5px; display:flex; align-items:center; gap:4px; white-space:nowrap;"><input type="checkbox" value="{{ $t->code }}" class="mf-aff-t"> {{ $t->label }}</label>
                @endforeach
            </div>
            <div class="mf-f" id="mf-aff-planrow" style="display:none; margin-top:6px; max-width:60%;">
                <label>{{ __('member_file.plan') }}</label><select id="mf-aff-plan"></select>
            </div>
        </div>
        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;">
            <button type="button" class="mf-btn" style="background:#6b7280;" onclick="mfPopClose()">{{ __('member_file.done') }}</button>
            <button type="button" class="mf-btn" id="mf-aff-save" style="display:none;" onclick="mfAffSave()">{{ __('member_file.save') }}</button>
        </div>
    </div>
</div>
<style>
.mf-link{color:#1565C0; font-weight:700; text-decoration:none;}
#mf-reg-table td:nth-child(2),#mf-reg-table td:nth-child(3){white-space:normal;}
.mf-fold{font-size:10.5px; font-weight:700; color:#1565C0; cursor:pointer; padding-bottom:2px;}
.mf-fold.on{color:#263238; border-bottom:2px solid #263238;}
.mf-pop{position:absolute; top:0; right:0; width:min(520px, 100%); background:#fff; border:1px solid #93c5fd; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,.15); padding:10px 12px; box-sizing:border-box; z-index:20;}
.mf-dd{position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:5px; max-height:150px; overflow-y:auto; z-index:30; font-size:10.5px;}
.mf-dd div{padding:4px 8px; cursor:pointer; white-space:normal;}
.mf-dd div:hover{background:#e3f2fd;}
</style>
<script>
(function(){
    var csrf = @json(csrf_token()), ret = @json($retParam), memberId = @json($p->agent_id);
    var R = {
        end: @json(route('admin.member-file.line.end', '__ID__')),
        pay: @json(route('admin.member-file.line.payment', '__ID__')),
        add: @json(route('admin.member-file.affiliation.add', $p->agent_id)),
        look: @json(route('admin.member-file.entity-lookup')),
        have: @json(route('admin.member-file.existing', $p->agent_id)),
    };
    var T = { endConfirm: @json(__('member_file.end_confirm')), already: @json(__('member_file.already_here')),
              select: @json(__('member_file.select')), since: @json(__('member_file.since')), typeLabels: @json($tl), setIn: @json(__('member_file.set_in', ['where' => '__W__'])),
              how: { STAFF: @json(__('member_file.how_STAFF')), QR: @json(__('member_file.how_QR')), REGISTRATION: @json(__('member_file.how_REGISTRATION')),
                     IMPORT: @json(__('member_file.how_IMPORT')), PRACTITIONER_SETUP: @json(__('member_file.how_PRACTITIONER_SETUP')), COMMITTEE_TAB: @json(__('member_file.how_COMMITTEE_TAB')) },
              period: { FREE: @json(__('member_file.period_FREE')), YEARLY: @json(__('member_file.period_YEARLY')), ONE_TIME: @json(__('member_file.period_ONE_TIME')), LIFETIME: @json(__('member_file.period_LIFETIME')) } };
    function post(url, fields){
        var f = document.createElement('form'); f.method = 'POST'; f.action = url; f.style.display = 'none';
        fields._token = csrf; fields['return'] = ret;
        Object.keys(fields).forEach(function(k){
            [].concat(fields[k]).forEach(function(v){ var i = document.createElement('input'); i.type = 'hidden'; i.name = k; i.value = v == null ? '' : v; f.appendChild(i); });
        });
        document.body.appendChild(f); f.submit();
    }
    window.mfFold = function(k){
        document.querySelectorAll('.mf-fold').forEach(function(s){ s.classList.toggle('on', s.dataset.fold === k); });
        document.getElementById('mf-fold-cur').style.display = k === 'cur' ? '' : 'none';
        document.getElementById('mf-fold-hist').style.display = k === 'hist' ? '' : 'none';
        if (window.mfShowPanel) window.mfShowPanel();
    };
    // the box always fits the screen (no scroll): shrink it together when the screen is short
    function fitPop(el){
        el.style.zoom = 1; var z = 1, pr = (document.getElementById('mf-form') || el.parentNode).getBoundingClientRect();
        while (el.getBoundingClientRect().bottom > pr.bottom - 4 && z > 0.6) { z -= 0.03; el.style.zoom = z; }
    }
    window.mfPopClose = function(){ document.querySelectorAll('.mf-pop').forEach(function(b){ b.style.display = 'none'; }); };
    var R2 = { prac: @json(route('admin.member-file.practitioner.add', $p->agent_id)), famAdd: @json(route('admin.member-file.family.add', '__ID__')),
               famRm: @json(route('admin.member-file.family.remove', '__ID__')), coSet: @json(route('admin.member-file.company.set', '__ID__')),
               coLook: @json(route('admin.member-file.company-lookup')), person: @json(route('admin.member-file.person-lookup')) };
    window.mfPracSave = function(){ post(R2.prac, { node_id: document.getElementById('mf-prac-node').value, practitioner_type_id: document.getElementById('mf-prac-type').value }); };
    window.mfFamilyOpen = function(tag){ mfPopClose(); var b = document.getElementById('mf-fam-' + tag); b.style.display = ''; fitPop(b); };
    window.mfFamilyAdd = function(tag){
        var b = document.getElementById('mf-fam-' + tag), who = b.querySelector('.mf-fam-who').value.trim(), code = (who.match(/CBE-[A-Z0-9]+/) || [who])[0];
        if (!who) return; post(R2.famAdd.replace('__ID__', tag), { member: code, relationship: b.querySelector('.mf-fam-rel').value, family: tag });
    };
    window.mfFamilyRemove = function(id){ if (confirm(T.endConfirm)) post(R2.famRm.replace('__ID__', id), {}); };
    document.querySelectorAll('.mf-fam-who').forEach(function(inp){
        var dl = document.getElementById(inp.getAttribute('list')), tm = null;
        inp.addEventListener('input', function(){ clearTimeout(tm); var v = inp.value.trim(); if (!v || /CBE-/.test(v)) return;
            tm = setTimeout(function(){ fetch(R2.person + '?by=name&q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(rows){
                dl.innerHTML = ''; rows.forEach(function(x){ var o = document.createElement('option'); o.value = x.code + ' · ' + x.name; o.label = x.phone || ''; dl.appendChild(o); }); }); }, 200); });
    });
    var coTag = null, coPicked = {};
    window.mfCompanyOpen = function(tag){ mfPopClose(); coTag = tag; var b = document.getElementById('mf-co-box'); b.style.display = ''; fitPop(b); document.getElementById('mf-co-name').focus(); };
    (function(){ var inp = document.getElementById('mf-co-name'), dl = document.getElementById('mf-co-dl'), tm = null; if (!inp) return;
        inp.addEventListener('input', function(){ clearTimeout(tm); var v = inp.value.trim(); if (!v) return;
            tm = setTimeout(function(){ fetch(R2.coLook + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(rows){
                dl.innerHTML = ''; coPicked = {}; rows.forEach(function(x){ var o = document.createElement('option'); o.value = x.company_name; o.label = x.registration_no || ''; dl.appendChild(o); coPicked[x.company_name] = x.company_id; }); }); }, 200); });
    })();
    window.mfCompanySave = function(){
        var n = document.getElementById('mf-co-name').value.trim(); if (!n || !coTag) return;
        post(R2.coSet.replace('__ID__', coTag), { company_id: coPicked[n] || '', company_name: n, registration_no: document.getElementById('mf-co-reg').value });
    };

    var line = null;
    window.mfLineOpen = function(l){
        mfPopClose(); line = l;
        document.getElementById('mf-line-title').textContent = l.type + ' — ' + l.entity;
        document.getElementById('mf-line-sub').textContent = (l.from ? l.from + ' · ' : '') + l.how;
        document.getElementById('mf-pay').style.display = l.member ? '' : 'none';
        document.getElementById('mf-pay-btn').style.display = l.member ? '' : 'none';
        document.getElementById('mf-line-box').style.display = ''; fitPop(document.getElementById('mf-line-box'));
    };
    window.mfLineEnd = function(){ if (line && confirm(T.endConfirm)) post(R.end.replace('__ID__', line.id), {}); };
    window.mfLinePay = function(){
        var amt = document.getElementById('mf-pay-amt').value, on = document.getElementById('mf-pay-on').value;
        if (amt === '' || !on) { document.getElementById('mf-pay-amt').focus(); return; }
        post(R.pay.replace('__ID__', line.id), { amount: amt, paid_on: on, receipt_no: document.getElementById('mf-pay-rc').value });
    };
    // ---- Add Affiliation (search first) ----
    var lookGroup = @json((string) request('group', ''));   // CBE chosen on the first screen
    var picked = null, q = document.getElementById('mf-aff-q'), list = document.getElementById('mf-aff-list'), timer = null;
    window.mfAffOpen = function(){
        mfPopClose(); picked = null; q.value = ''; list.style.display = 'none';
        document.getElementById('mf-aff-step2').style.display = 'none'; document.getElementById('mf-aff-save').style.display = 'none';
        document.getElementById('mf-aff-box').style.display = ''; q.focus();
    };
    q.addEventListener('input', function(){
        clearTimeout(timer); var v = q.value.trim();
        if (!v) { list.style.display = 'none'; return; }
        timer = setTimeout(function(){
            fetch(R.look + '?q=' + encodeURIComponent(v) + (lookGroup ? '&group=' + encodeURIComponent(lookGroup) : ''), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(rows){
                list.innerHTML = '';
                rows.forEach(function(n){
                    var d = document.createElement('div');
                    d.textContent = n.node_name + (n.node_name_zh && n.node_name_zh !== n.node_name ? ' ' + n.node_name_zh : '') + ' — ' + n.group_name + (n.level_name ? ' · ' + n.level_name : '') + (n.city ? ' · ' + n.city : '');
                    d.onclick = function(){ pick(n); };
                    list.appendChild(d);
                });
                list.style.display = rows.length ? '' : 'none';
            });
        }, 250);
    });
    function pick(n){
        picked = n; list.style.display = 'none'; q.value = n.node_name;
        document.getElementById('mf-aff-picked').textContent = n.node_name + ' — ' + n.group_name;
        fetch(R.have + '?node=' + encodeURIComponent(n.node_id), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(d){
            var have = {}, parts = [];
            (d.existing || []).forEach(function(l){
                have[l.type] = true;
                var lbl = l.type === 'COMMITTEE' ? (l.plan || T.typeLabels.COMMITTEE) : (T.typeLabels[l.type] || l.type);
                var note = (l.type === 'COMMITTEE' || l.type === 'PRACTITIONER') ? T.setIn.replace('__W__', T.how[l.how] || l.how) : (T.since + ' ' + fmtD(l.from));
                parts.push('<b>' + esc(lbl) + '</b> (' + esc(note) + ')');
            });
            document.getElementById('mf-aff-have').innerHTML = parts.length ? esc(T.already) + ' ' + parts.join(', ') : '';
            document.querySelectorAll('.mf-aff-t').forEach(function(c){ c.checked = false; c.disabled = !!have[c.value]; c.parentNode.style.opacity = have[c.value] ? .45 : 1; });
            var sel = document.getElementById('mf-aff-plan'); sel.innerHTML = '<option value="">' + esc(T.select) + '</option>';
            (d.plans || []).forEach(function(p){ var o = document.createElement('option'); o.value = p.id; o.textContent = p.plan_name + ' — ' + (T.period[p.period] || p.period) + (parseFloat(p.fee) > 0 ? ' RM ' + parseFloat(p.fee).toFixed(2) : ''); sel.appendChild(o); });
            document.getElementById('mf-aff-planrow').style.display = 'none';
            document.getElementById('mf-aff-step2').style.display = ''; document.getElementById('mf-aff-save').style.display = ''; fitPop(document.getElementById('mf-aff-box'));
        });
    }
    document.querySelectorAll('.mf-aff-t').forEach(function(c){ c.addEventListener('change', function(){
        if (c.value === 'MEMBER') { document.getElementById('mf-aff-planrow').style.display = c.checked && document.getElementById('mf-aff-plan').options.length > 1 ? '' : 'none'; fitPop(document.getElementById('mf-aff-box')); }
    }); });
    window.mfAffSave = function(){
        var types = [].map.call(document.querySelectorAll('.mf-aff-t:checked'), function(c){ return c.value; });
        if (!picked || !types.length) return;
        post(R.add, { node_id: picked.node_id, 'types[]': types, plan_id: document.getElementById('mf-aff-plan').value, back: @json((string) request('back', '')) });
    };
    // NEW 28 Sep 2026 — came from an entity's "+ Add Member Here": that entity is already picked
    var famOpen = @json((string) request('family', ''));
    if (famOpen && document.getElementById('mf-fam-' + famOpen)) setTimeout(function(){ mfFamilyOpen(famOpen); }, 50);
    var addNode = @json($addNode ?? null);
    if (addNode) {
        if (window.mfShowPanel) { /* panel 4 is already open (panel=register) */ }
        mfAffOpen(); pick(addNode);
    }
    function fmtD(d){ if (!d) return ''; var p = String(d).substr(0, 10).split('-'); return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d; }
    function esc(s){ return String(s == null ? '' : s).replace(/[&<>"]/g, function(c){ return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
})();
</script>
