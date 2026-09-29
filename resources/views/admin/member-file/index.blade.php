@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris: Member Maintenance Search / View / Edit for
     ALL CBEs, same flow as Entity Maintenance. Search boxes first; nothing
     listed until GO. CBE Group is optional (blank = all CBEs); picking one
     adds HQ / State / Branch and its Membership Plans. --}}
@include('admin.member-file._style')
<style>
.esb-row{display:flex; gap:8px 10px; align-items:flex-end; flex-wrap:wrap;}
.esb-field{display:flex; flex-direction:column; gap:3px; min-width:0;}
.esb-field label{font-size:8.5px; font-weight:700; color:#6b7280; text-transform:uppercase; white-space:nowrap;}
.esb-field input,.esb-field select{font-family:inherit; font-size:11px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%; background:#fff;}
.esb-pair{display:flex; align-items:center; gap:4px;}
.esb-pair span{font-size:9px; color:#6b7280; font-weight:700;}
.esb-go{background:#1565C0; color:#fff; border:none; border-radius:20px; padding:7px 22px; font-size:11px; font-weight:700; cursor:pointer;}
</style>
@php
    $optSel = function ($name, $list, $labelKey, $flex = '1 1 110px') {
        $cur = (string) request($name);
        $h = '<div class="esb-field" style="flex:'.$flex.';"><label>'.e(__('member_file.'.$labelKey)).'</label><select name="'.$name.'"><option value="">'.e(__('member_file.any')).'</option>';
        foreach ($list as $o) { $h .= '<option value="'.e($o->id).'"'.((string) $o->id === $cur ? ' selected' : '').'>'.e($o->label).'</option>'; }
        return $h.'</select></div>';
    };
    $months = collect(range(1, 12))->map(fn ($m) => (object) ['id' => $m, 'label' => \Illuminate\Support\Carbon::create(2000, $m, 1)->translatedFormat('F')]);
    $fees = collect(['PAID', 'DUE', 'OVERDUE', 'FREE', 'WAIVED'])->map(fn ($f) => (object) ['id' => $f, 'label' => __('member_file.fee_'.$f)]);
    $planOpts = $plans->map(fn ($pl) => (object) ['id' => $pl->id, 'label' => $pl->plan_name]);
@endphp
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.title') }} — {{ __('member_file.search_view_edit') }}</div>
    @if(session('member_saved'))<div class="mf-ok">✓ {{ __('member_file.saved_msg', ['name' => session('member_saved')]) }}</div>@endif

    @php
        // after GO the search boxes fold into one line so the results get the screen (no scroll)
        $critLabels = [];
        if ($group) { $critLabels[] = $group->group_name; }
        foreach (['city' => 'city', 'name' => 'name_search', 'mobile' => 'mobile', 'position' => 'position'] as $f => $lk) { if (request($f)) { $critLabels[] = __('member_file.'.$lk).': '.request($f); } }
        if (request('pc_from') || request('pc_to')) { $critLabels[] = __('member_file.postcode').': '.request('pc_from').'–'.request('pc_to'); }
        if (request('birth_month')) { $critLabels[] = __('member_file.birth_month').': '.request('birth_month'); }
        if (request('membership_type_id')) { $critLabels[] = __('member_file.membership_type').': '.optional($memTypes->firstWhere('id', request('membership_type_id')))->label; }
    @endphp
    @if($searched)
    <div id="mfs-summary" style="flex-shrink:0; display:flex; align-items:center; gap:10px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; font-size:10.5px;">
        <span style="flex:1; min-width:0; white-space:normal;"><b>{{ __('member_file.criteria') }}:</b> {{ $critLabels ? implode(' · ', $critLabels) : __('member_file.all_groups') }}</span>
        <button type="button" class="mf-btn" onclick="document.getElementById('mfs-form').style.display=''; document.getElementById('mfs-summary').style.display='none';">{{ __('member_file.modify_search') }}</button>
    </div>
    @endif
    <form id="mfs-form" method="GET" action="{{ route('admin.member-file.index') }}" style="flex-shrink:0; margin:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px; {{ $searched ? 'display:none;' : '' }}">
        <input type="hidden" name="go" value="1">
        <div class="esb-row">
            @include('admin.cbe-kpi.hierarchy-nodes._tier-boxes', ['groupReloadUrl' => route('admin.member-file.index'), 'groupOptional' => true])
            {{-- item 8 (28 Sep 2026): every box type-ahead --}}
            <div class="esb-field" style="flex:1 1 120px;"><label>{{ __('member_file.city') }}</label><input name="city" value="{{ request('city') }}" list="mfs-city" autocomplete="off"><datalist id="mfs-city">@foreach($cityList as $c)<option value="{{ $c }}">@endforeach</datalist></div>
            <div class="esb-field" style="flex:0 0 150px;"><label>{{ __('member_file.postcode') }}</label>
                <div class="esb-pair"><input name="pc_from" value="{{ request('pc_from') }}" maxlength="5" list="mfs-pc" autocomplete="off"><span>–</span><input name="pc_to" value="{{ request('pc_to') }}" maxlength="5" list="mfs-pc" autocomplete="off"></div>
                <datalist id="mfs-pc">@foreach($pcList as $pc)<option value="{{ $pc['v'] }}" label="{{ $pc['v'] }} · {{ $pc['l'] }}">@endforeach</datalist></div>
            <div class="esb-field" style="flex:2 1 160px;"><label>{{ __('member_file.name_search') }}</label><input name="name" value="{{ request('name') }}" list="mfs-name" autocomplete="off" data-ta="name"><datalist id="mfs-name"></datalist></div>
            <div class="esb-field" style="flex:1 1 110px;"><label>{{ __('member_file.mobile') }}</label><input name="mobile" value="{{ request('mobile') }}" list="mfs-mobile" autocomplete="off" data-ta="mobile"><datalist id="mfs-mobile"></datalist></div>
            @if($canSensitive)
            {!! $optSel('race_id', $races, 'race') !!}
            {!! $optSel('religion_id', $religions, 'religion') !!}
            @endif
            {!! $optSel('gender_id', $genders, 'gender', '1 1 90px') !!}
            {!! $optSel('birth_month', $months, 'birth_month') !!}
            <div class="esb-field" style="flex:1 1 120px;"><label>{{ __('member_file.position') }}</label><input name="position" value="{{ request('position') }}" list="mfs-pos" autocomplete="off"><datalist id="mfs-pos">@foreach($positionList as $pl)<option value="{{ $pl }}">@endforeach</datalist></div>
            @if($group && $planOpts->isNotEmpty()){!! $optSel('plan_id', $planOpts, 'plan') !!}@endif
            {{-- CHANGED 28 Sep 2026 — per Chris: Fee Status → Membership Type, with + Add (Member Pick Lists › Membership Type) --}}
            <div class="esb-field" style="flex:1 1 150px;"><label>{{ __('member_file.membership_type') }}</label>
                <div style="display:flex; gap:6px; align-items:center;">
                    <select name="membership_type_id"><option value="">{{ __('member_file.any') }}</option>@foreach($memTypes as $mt)<option value="{{ $mt->id }}" @selected(request('membership_type_id') === $mt->id)>{{ $mt->label }}</option>@endforeach</select>
                    <a href="{{ route('admin.member-pick-lists.index', ['list' => 'MEMTYPE', 'return' => request()->fullUrl()]) }}" class="mf-btn" style="padding:5px 12px;">{{ __('member_file.add_type') }}</a>
                </div></div>
            <button type="submit" class="esb-go">GO</button>
        </div>
    </form>

    <div id="mf-table-wrap" style="flex:1; min-height:0; overflow:hidden;">
        @if(! $searched)
            <div class="mf-box" style="flex:0 0 auto; text-align:center; color:#94A3B8; font-size:10px; padding:24px;">{{ __('member_file.search_first') }}</div>
        @else
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
            <table class="mf-table" id="mf-table" style="font-size:12px;">
                <thead><tr><th>#</th><th>{{ __('member_file.col_name') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.city') }}</th><th>{{ __('member_file.postcode') }}</th><th>{{ __('member_file.col_affiliated') }}</th><th>{{ __('member_file.position') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td style="color:#9ca3af;">{{ $rows->firstItem() + $loop->index }}</td>
                        <td style="font-weight:600; color:#1565C0;">{{ $r->full_name }}@if($r->second_name) <span style="color:#6b7280; font-weight:400;">{{ $r->second_name }}</span>@endif @if($r->nick_name)<span style="color:#6b7280; font-weight:400;">({{ $r->nick_name }})</span>@endif</td>
                        <td>{{ $r->phone ?: '—' }}</td>
                        <td>{{ $r->city ?: '—' }}</td>
                        <td>{{ $r->postcode ?: '—' }}</td>
                        <td class="wrap">{{ $r->affiliated ? implode(', ', $r->affiliated) : __('member_file.not_affiliated') }}</td>
                        <td class="wrap">{{ $r->positions ? implode(', ', $r->positions) : '—' }}</td>
                        <td><a href="{{ route('admin.member-file.edit', array_filter(['id' => $r->agent_id, 'group' => $group->group_label_id ?? null, 'return' => request()->fullUrl()])) }}" style="color:#1565C0; text-decoration:none; font-weight:700;">{{ __('member_file.view_edit') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_cbe_directory.no_results') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @php($searchUrl = route('admin.member-file.index', array_filter(['group' => $group->group_label_id ?? null])))
    <div class="mf-bar">
        <a href="{{ ($rows && ! $rows->onFirstPage()) ? $rows->previousPageUrl() : ($searched ? $searchUrl : route('admin.member-file.landing', array_filter(['group' => $group->group_label_id ?? null]))) }}" class="mf-btn">{{ __('masterfile.prev') }}</a>
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
    // item 8: Name / Mobile type-ahead from the member file (Member ID · name · mobile)
    var url = @json(route('admin.member-file.person-lookup')), grp = @json($group->group_label_id ?? ''), t = null;
    document.querySelectorAll('[data-ta]').forEach(function(inp){
        var dl = document.getElementById(inp.getAttribute('list'));
        inp.addEventListener('input', function(){
            clearTimeout(t); var v = inp.value.trim(); if (v.length < 1) return;
            t = setTimeout(function(){
                fetch(url + '?by=' + inp.dataset.ta + '&q=' + encodeURIComponent(v) + (grp ? '&group=' + grp : ''), { headers: { 'Accept': 'application/json' } })
                    .then(function(r){ return r.json(); }).then(function(rows){
                        dl.innerHTML = '';
                        rows.forEach(function(x){ var o = document.createElement('option'); o.value = inp.dataset.ta === 'mobile' ? x.phone : x.name; o.label = x.code + ' · ' + x.name + ' · ' + (x.phone || ''); dl.appendChild(o); });
                    });
            }, 200);
        });
    });
})();
</script>
@if($searched && $rows && $rows->total() > 0)
<script>
(function(){
    // no scroll: one line per row (shrink the font together), then as many rows as fit
    var wrap = document.getElementById('mf-table-wrap'), table = document.getElementById('mf-table');
    var perPage = {{ $rows->perPage() }}, first = {{ $rows->firstItem() ?? 1 }}, total = {{ $rows->total() }};
    (function(){ var fs = parseFloat(getComputedStyle(table).fontSize); while (table.scrollWidth > wrap.clientWidth + 1 && fs > 6.5) { fs -= 0.25; table.style.fontSize = fs + 'px'; } })();
    function apply(){
        var rows = table.querySelectorAll('tbody tr'); if (!rows.length) return;
        var head = table.querySelector('thead'), maxH = 0;
        rows.forEach(function(r){ maxH = Math.max(maxH, r.offsetHeight); });
        var fit = Math.max(1, Math.min(50, Math.floor((wrap.clientHeight - (head ? head.offsetHeight : 0) - 4) / maxH)));
        if (fit === perPage) return;
        if (table.offsetHeight <= wrap.clientHeight && total <= perPage) return;
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', fit);
        url.searchParams.set('page', Math.floor((first - 1) / fit) + 1);
        window.location.replace(url.toString());
    }
    apply();
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(apply, 400); });
})();
</script>
@endif
@endsection
