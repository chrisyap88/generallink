@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.donor_link_title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris (member file item 26): link every donor /
     sponsor of the Donor Register to ONE person (member file) or ONE company,
     and merge duplicate donor records. CBE Group first, then folders
     Not Linked | Linked | Possible Duplicates. --}}
@include('admin.member-file._style')
@php
    $self = fn ($f) => route('admin.donor-link.index', array_filter(['group' => $group->group_label_id ?? null, 'f' => $f]));
@endphp
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.donor_link_title') }}</div>
    <div class="mf-f" style="flex-shrink:0; max-width:420px;">
        <label>{{ __('member_file.group') }} *</label>
        <input id="dl-group" list="dl-group-list" value="{{ $group->group_name ?? '' }}" autocomplete="off" placeholder="{{ __('member_file.pick_group_first') }}">
        <datalist id="dl-group-list">@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
    </div>
    @if(session('member_saved'))<div class="mf-ok">✓ {{ session('member_saved') }}</div>@endif
    @if(session('link_error'))<div class="mf-err">{{ session('link_error') }}</div>@endif

    @if($group)
    <div style="display:flex; gap:14px; border-bottom:1px solid #e5e7eb; flex-shrink:0;">
        @foreach(['open', 'linked', 'dups'] as $f)
            <a href="{{ $self($f) }}" style="font-size:10.5px; font-weight:700; text-decoration:none; padding-bottom:3px; {{ $folder === $f ? 'color:#263238; border-bottom:2px solid #263238;' : 'color:#1565C0;' }}">{{ __('member_file.folder_'.$f) }} ({{ $counts[$f] }})</a>
        @endforeach
    </div>
    <div id="dl-wrap" class="mf-box" style="padding:0; overflow:hidden;">
        @if($folder === 'dups')
            @if($dups->isEmpty())
                <div class="mf-sub" style="padding:18px; text-align:center;">—</div>
            @else
            <table class="mf-table" id="dl-table">
                <thead><tr><th>#</th><th>{{ __('member_file.col_donor') }}</th><th>{{ __('member_file.col_type') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.email') }}</th><th>{{ __('member_file.entity') }}</th><th></th></tr></thead>
                <tbody>
                @foreach($dups as $gi => $g)
                    @foreach($g['rows'] as $k => $d)
                    <tr style="{{ $k === 0 ? 'border-top:2px solid #93c5fd;' : '' }}">
                        <td>{{ $k === 0 ? $gi + 1 : '' }}</td>
                        <td style="{{ $k === 0 ? 'font-weight:700;' : '' }}">{{ $d->donor_name }}</td><td>{{ $d->donor_type }}</td><td>{{ $d->phone ?: '—' }}</td><td>{{ $d->email ?: '—' }}</td><td>{{ $d->node_name }}</td>
                        <td>
                            @if($k === 0)
                            <form method="POST" action="{{ route('admin.donor-link.merge') }}" style="margin:0;" onsubmit="return confirm({{ json_encode(__('member_file.merge_into_first').'?') }})">
                                @csrf
                                <input type="hidden" name="keep" value="{{ $d->donor_id }}">
                                @foreach(array_slice($g['rows'], 1) as $o)<input type="hidden" name="drop[]" value="{{ $o->donor_id }}">@endforeach
                                <button class="mf-btn" style="padding:3px 10px;">{{ __('member_file.merge_into_first') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
            @endif
        @else
            <table class="mf-table" id="dl-table">
                <thead><tr><th>#</th><th>{{ __('member_file.col_donor') }}</th><th>{{ __('member_file.col_type') }}</th><th>{{ __('member_file.col_mobile') }}</th><th>{{ __('member_file.entity') }}</th><th>{{ $folder === 'linked' ? __('member_file.col_linked_to') : __('member_file.col_suggest') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $d)
                    @php $isCo = in_array($d->donor_type, ['COMPANY', 'ORGANIZATION'], true); @endphp
                    <tr>
                        <td style="color:#9ca3af;">{{ $rows->firstItem() + $loop->index }}</td>
                        <td style="font-weight:600;">{{ $d->donor_name }}</td><td>{{ $d->donor_type }}</td><td>{{ $d->phone ?: '—' }}</td><td class="wrap">{{ $d->node_name }}</td>
                        @if($folder === 'linked')
                            <td>{{ $d->person_name ? $d->person_name.' · '.$d->agent_code : $d->company_name }}</td>
                            <td><form method="POST" action="{{ route('admin.donor-link.link') }}" style="margin:0;">@csrf<input type="hidden" name="donor_id" value="{{ $d->donor_id }}"><input type="hidden" name="action" value="unlink"><button class="mf-btn" style="padding:3px 10px; background:#6b7280;">{{ __('member_file.unlink') }}</button></form></td>
                        @else
                            <td class="wrap">@if($d->suggest){{ $d->suggest['label'] }} <span class="mf-sub">({{ __('member_file.why_'.$d->suggest['why']) }})</span>@else<span class="mf-sub">{{ __('member_file.no_suggest') }}</span>@endif</td>
                            <td>
                                <form method="POST" action="{{ route('admin.donor-link.link') }}" style="margin:0; display:flex; flex-wrap:wrap; gap:4px 5px; align-items:center; max-width:17em;">
                                    @csrf
                                    <input type="hidden" name="donor_id" value="{{ $d->donor_id }}">
                                    @if($isCo)
                                        <input type="hidden" name="company_id" value="{{ $d->suggest['id'] ?? '' }}">
                                        <input type="hidden" name="company_name" value="{{ $d->donor_name }}">
                                        <button class="mf-btn" style="padding:3px 10px;">{{ __('member_file.link') }}</button>
                                    @else
                                        <input type="hidden" name="agent_id" value="{{ $d->suggest['id'] ?? '' }}">
                                        @if($d->suggest)<button class="mf-btn" style="padding:3px 10px;">{{ __('member_file.link') }}</button>@endif
                                        <input name="member" list="dl-person" placeholder="{{ __('member_file.or_pick') }}" autocomplete="off" class="dl-pick" style="font-size:10px; width:120px; border:1px solid #d1d5db; border-radius:5px; padding:3px 5px;">
                                        <a href="{{ route('admin.member-file.create', ['group' => $group->group_label_id, 'link_donor' => $d->donor_id, 'prefill_full_name' => $d->donor_name, 'prefill_phone' => $d->phone, 'prefill_email' => $d->email, 'return' => request()->fullUrl()]) }}" class="mf-link" style="white-space:nowrap; color:#1565C0; font-weight:700; text-decoration:none;">{{ __('member_file.add_as_member') }}</a>
                                    @endif
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:18px; text-align:center; color:#9ca3af;">—</td></tr>
                @endforelse
                </tbody>
            </table>
            <datalist id="dl-person"></datalist>
        @endif
    </div>
    @else
    <div style="flex:1;"></div>
    @endif

    <div class="mf-bar">
        <a href="{{ ($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && ! $rows->onFirstPage()) ? $rows->previousPageUrl() : ($group ? route('admin.donor-link.index') : route('cbe.donors.index')) }}" class="mf-btn">{{ __('masterfile.prev') }}</a>
        <span style="font-size:10.5px; color:#4b5563;">@if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->total() > 0){{ __('masterfile.showing_records', ['first' => $rows->firstItem(), 'last' => $rows->lastItem(), 'total' => $rows->total()]) }}@endif</span>
        @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->hasMorePages())
            <a href="{{ $rows->nextPageUrl() }}" class="mf-btn">{{ __('masterfile.next') }}</a>
        @else
            <span class="mf-btn" style="cursor:default;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
</div>
<script>
(function(){
    var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values());
    var gi = document.getElementById('dl-group');
    gi.addEventListener('change', function(){ var h = groups.filter(function(g){ return g.v === gi.value; })[0]; if (h) window.location.href = @json(route('admin.donor-link.index')) + '?group=' + encodeURIComponent(h.id); });
    // pick another person: type-ahead from the member file (Member ID · name)
    var url = @json(route('admin.member-file.person-lookup')), dl = document.getElementById('dl-person'), tm = null;
    document.querySelectorAll('.dl-pick').forEach(function(inp){
        inp.addEventListener('input', function(){ clearTimeout(tm); var v = inp.value.trim(); if (!v || /^CBE-/.test(v)) return;
            tm = setTimeout(function(){ fetch(url + '?by=name&q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function(r){ return r.json(); }).then(function(rows){
                dl.innerHTML = ''; rows.forEach(function(x){ var o = document.createElement('option'); o.value = x.code; o.label = x.name + ' · ' + (x.phone || ''); dl.appendChild(o); }); }); }, 200); });
        inp.addEventListener('change', function(){ if (/^CBE-/.test(inp.value)) inp.form.submit(); });
    });
    // no scroll: shrink together, then rows that fit
    var w = document.getElementById('dl-wrap'), t = document.getElementById('dl-table');
    if (w && t) { var fs = 11; t.style.fontSize = fs + 'px'; while ((t.scrollWidth > w.clientWidth + 1) && fs > 6.5) { fs -= 0.25; t.style.fontSize = fs + 'px'; } }
    @if($rows instanceof \Illuminate\Pagination\LengthAwarePaginator && $rows->total() > 0)
    (function(){
        var rs = t.querySelectorAll('tbody tr'), mh = 0; rs.forEach(function(r){ mh = Math.max(mh, r.offsetHeight); });
        var fit = Math.max(3, Math.min(50, Math.floor((w.clientHeight - t.querySelector('thead').offsetHeight - 4) / mh))), per = {{ $rows->perPage() }};
        if (fit === per || (t.offsetHeight <= w.clientHeight && {{ $rows->total() }} <= per)) return;
        var u = new URL(window.location.href); u.searchParams.set('per_page', fit); u.searchParams.set('page', Math.floor(({{ $rows->firstItem() ?? 1 }} - 1) / fit) + 1); window.location.replace(u.toString());
    })();
    @endif
})();
</script>
@endsection
