@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris: Member Maintenance for ALL CBEs (one person,
     many affiliations). CHANGED 28 Sep 2026 — per Chris: choose the CBE Group
     first (type-ahead, or All CBE Groups), then Add New Member | Search / View /
     Edit — both open for that CBE. --}}
@include('admin.member-file._style')
<div class="mf-page">
    <div><div class="mf-title">{{ __('member_file.title') }}</div><div class="mf-sub">{{ __('member_file.landing_prompt') }}</div></div>
    @if(session('member_saved'))<div class="mf-ok">✓ {{ __('member_file.saved_msg', ['name' => session('member_saved')]) }}</div>@endif
    <div class="mf-box" style="flex:0 0 auto; display:flex; flex-direction:column; gap:10px;">
        <div class="mf-f" style="max-width:420px;">
            <label>{{ __('member_file.group') }} *</label>
            <input id="mfl-group" list="mfl-group-list" autocomplete="off" value="{{ $group->group_name ?? ($groups->count() === 1 && auth('agent')->user()->role !== 'ADMIN' ? $groups->first()->group_name : '') }}" placeholder="{{ __('member_file.pick_group_first') }}">
            <datalist id="mfl-group-list">@if(auth('agent')->user()->role === 'ADMIN')<option value="{{ __('member_file.all_groups') }}">@endif@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="#" id="mfl-add" class="mf-btn-sq">{{ __('member_file.add_new') }}</a>
            <a href="#" id="mfl-search" class="mf-btn-sq">{{ __('member_file.search_view_edit') }}</a>
        </div>
    </div>
    <div style="flex:1;"></div>
    <div class="mf-bar"><a href="{{ auth('agent')->user()->role === 'ADMIN' ? route('admin.cbe-kpi') : route('cbe.exec-dashboard') }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span><span class="mf-btn" style="visibility:hidden;">{{ __('masterfile.next') }}</span></div>
</div>
<script>
(function(){
    var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values());
    var ALL = @json(__('member_file.all_groups'));
    var inp = document.getElementById('mfl-group'), add = document.getElementById('mfl-add'), srch = document.getElementById('mfl-search');
    var U = { add: @json(route('admin.member-file.create')), search: @json(route('admin.member-file.index')), self: @json(route('admin.member-file.landing')) };
    function sync(){
        var hit = groups.filter(function(g){ return g.v === inp.value; })[0], all = inp.value === ALL, ok = !!hit || all;
        [add, srch].forEach(function(b){ b.style.opacity = ok ? 1 : .4; b.style.pointerEvents = ok ? '' : 'none'; });
        var q = hit ? 'group=' + encodeURIComponent(hit.id) : '';
        var back = encodeURIComponent(U.self + (q ? '?' + q : ''));
        add.href = U.add + '?' + (q ? q + '&' : '') + 'return=' + back;
        srch.href = U.search + (q ? '?' + q : '');
    }
    inp.addEventListener('input', sync); inp.addEventListener('change', sync);
    sync(); if (!inp.value) inp.focus();
})();
</script>
@endsection
