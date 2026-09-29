@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.title'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris: one member record for all CBEs.
     Add = 3 screens (Profile / Contact & Address / Preferences & Instructions),
     Prev / Next at the bottom, Save on screen 3 — the person only, no CBE.
     View / Edit = the same 3 screens + Affiliation Register (search-first
     Add Affiliation, End, Record Payment) + Committee Positions (read-only). --}}
@include('admin.member-file._style')
@php
    $p = $person;
    $isEdit = (bool) $p;
    $v = fn ($f) => old($f, $p->{$f} ?? request('prefill_'.$f, ''));   // prefill_* = from Donor Link › Add as New Member
    $sel = function ($name, $list, $labelKey) use ($v) {
        $cur = (string) $v($name);
        $h = '<div class="mf-f"><label>'.e(__('member_file.'.$labelKey)).'</label><select name="'.$name.'"><option value="">'.e(__('member_file.select')).'</option>';
        foreach ($list as $o) { $h .= '<option value="'.e($o->id).'"'.((string) $o->id === $cur ? ' selected' : '').'>'.e($o->label).'</option>'; }
        return $h.'</select></div>';
    };
    $steps = $isEdit ? ['profile', 'contact', 'prefs', 'register', 'committee'] : ['profile', 'contact', 'prefs'];
    $startPanel = request('panel') === 'register' ? 3 : (request('panel') === 'committee' ? 4 : ($errors->has('pdpa_consent') ? 2 : ($errors->has('phone') ? 1 : 0)));
    $ret = (string) request('return', old('return', ''));
    $backUrl = ($ret !== '' && str_starts_with($ret, url('/'))) ? $ret : ($isEdit ? route('admin.member-file.index') : route('admin.member-file.landing'));
    $age = ($isEdit && $p->date_of_birth) ? \Illuminate\Support\Carbon::parse($p->date_of_birth)->age : null;
@endphp
<div class="mf-page">
    <div style="flex-shrink:0; display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:2px 10px; padding-right:58px;">
        <div class="mf-title">{{ __('member_file.title') }} — {{ $isEdit ? ($p->full_name.($p->nick_name ? ' ('.$p->nick_name.')' : '')) : __('member_file.add_new') }}</div>
        <div class="mf-steps">@foreach($steps as $i => $s)<span data-step="{{ $i }}">{{ __('member_file.step_'.$s) }}</span>@if(! $loop->last)<span>›</span>@endif @endforeach</div>
    </div>
    @if($isEdit)
    @php
        $curPos = $committee->where('current', true);
        $nEnt = collect($register)->filter(fn ($r) => collect($r['lines'])->contains('status', 'ACTIVE'))->count();
        $nGrp = collect($register)->filter(fn ($r) => collect($r['lines'])->contains('status', 'ACTIVE'))->pluck('group_id')->unique()->count();
    @endphp
    <div style="flex-shrink:0; font-size:9.5px; color:#374151;">
        {{ __('member_file.member_id') }} <b>{{ $p->agent_code }}</b> · {{ __('member_file.summary', ['entities' => $nEnt, 'groups' => $nGrp]) }}@foreach($curPos as $c) · <b>{{ $c->position_label }}</b> — {{ $c->group_name }}@endforeach
        @if($p->personal_instructions)<div style="margin-top:3px; background:#FFF8E1; border:1px solid #FFE082; border-radius:5px; padding:4px 8px; color:#5d4600; white-space:normal;"><b>{{ __('member_file.instructions_banner') }}:</b> {{ $p->personal_instructions }}</div>@endif
    </div>
    @endif
    @if(session('member_saved'))<div class="mf-ok">✓ {{ session('member_saved') }}</div>@endif

    <form id="mf-form" method="POST" action="{{ $isEdit ? route('admin.member-file.update', $p->agent_id) : route('admin.member-file.store') }}" class="mf-box" style="display:flex; flex-direction:column;">
        @csrf
        @if($isEdit) @method('PUT') @endif
        <input type="hidden" name="return" value="{{ $ret }}">
        @unless($isEdit)<input type="hidden" name="link_donor" value="{{ request('link_donor', old('link_donor')) }}"><input type="hidden" name="add_node" value="{{ request('node', old('add_node')) }}"><input type="hidden" name="add_group" value="{{ request('group', old('add_group')) }}">@endunless
        {{-- Screen 1: Profile --}}
        <div class="mf-panel mf-grid" data-panel="0">
            <div class="mf-f"><label>{{ __('member_file.full_name') }} *</label><input name="full_name" value="{{ $v('full_name') }}" required>@error('full_name')<span class="mf-err">{{ $message }}</span>@enderror</div>
            <div class="mf-f"><label>{{ __('member_file.other_name') }}</label><input name="second_name" value="{{ $v('second_name') }}"></div>
            <div class="mf-f"><label>{{ __('member_file.nick_name') }}</label><input name="nick_name" value="{{ $v('nick_name') }}"></div>
            @if($canSensitive)
            <div class="mf-f"><label>{{ __('member_file.nric') }}</label><input name="nric" value="{{ old('nric') }}" placeholder="{{ $isEdit && $p->nric_hash ? '•••••• (on file)' : '' }}"></div>
            @else
            {{-- item 19: NRIC / Race / Religion are Admin only --}}
            <div class="mf-f"><label>{{ __('member_file.nric') }}</label><input value="{{ __('member_file.restricted') }}" disabled style="background:#f3f4f6; color:#6b7280;"></div>
            @endif
            <div class="mf-f"><label>{{ __('member_file.dob') }}@if($age !== null) · {{ __('member_file.age') }} {{ $age }}@endif</label><input type="date" name="date_of_birth" value="{{ $v('date_of_birth') ? substr($v('date_of_birth'), 0, 10) : '' }}"></div>
            {!! $sel('gender_id', $genders, 'gender') !!}
            @if($canSensitive)
            {!! $sel('race_id', $races, 'race') !!}
            {!! $sel('religion_id', $religions, 'religion') !!}
            @else
            <div class="mf-f"><label>{{ __('member_file.race') }}</label><input value="{{ __('member_file.restricted') }}" disabled style="background:#f3f4f6; color:#6b7280;"></div>
            <div class="mf-f"><label>{{ __('member_file.religion') }}</label><input value="{{ __('member_file.restricted') }}" disabled style="background:#f3f4f6; color:#6b7280;"></div>
            @endif
            {!! $sel('nationality_id', $nationalities, 'nationality') !!}
            {!! $sel('marital_status_id', $maritals, 'marital') !!}
            {!! $sel('occupation_group_id', $occupationGroups, 'occupation_group') !!}
            <div class="mf-f"><label>{{ __('member_file.occupation') }}</label><input name="occupation" value="{{ $v('occupation') }}"></div>
            {!! $sel('customer_type_id', $customerTypes, 'customer_type') !!}
            {!! $sel('customer_category_id', $customerCategories, 'category') !!}
            {!! $sel('source_id', $sources, 'source') !!}
        </div>
        {{-- Screen 2: Contact & Address --}}
        <div class="mf-panel mf-grid" data-panel="1" style="display:none;">
            <div class="mf-f"><label>{{ __('member_file.mobile') }} *</label><input name="phone" value="{{ $v('phone') }}" required>@error('phone')<span class="mf-err">{{ $message }}</span>@enderror</div>
            <div class="mf-f"><label>{{ __('member_file.office_phone') }}</label><input name="office_phone" value="{{ $v('office_phone') }}"></div>
            <div class="mf-f"><label>{{ __('member_file.email') }}</label><input type="email" name="email" value="{{ old('email', ($isEdit && ! \App\Services\MemberFileService::isPlaceholderEmail($p->email)) ? $p->email : request('prefill_email', '')) }}"></div>
            <div class="mf-f"><label>{{ __('member_file.language') }}</label><select name="preferred_language">@foreach(['EN' => 'English', 'MS' => 'Bahasa Melayu', 'ZH' => '中文'] as $k => $lbl)<option value="{{ $k }}" {{ strtoupper((string) ($v('preferred_language') ?: 'EN')) === $k ? 'selected' : '' }}>{{ $lbl }}</option>@endforeach</select></div>
            <div class="mf-f" style="grid-column:span 2;"><label>{{ __('member_file.address') }}</label><input name="address" value="{{ $v('address') }}"></div>
            <div class="mf-f"><label>{{ __('member_file.postcode') }}</label><input name="postcode" id="mf-postcode" value="{{ $v('postcode') }}" maxlength="5"></div>
            <div class="mf-f"><label>{{ __('member_file.city') }}</label><input name="city" id="mf-city" value="{{ $v('city') }}"></div>
            <div class="mf-f"><label>{{ __('member_file.state') }}</label><input name="state" id="mf-state" value="{{ $v('state') }}"></div>
        </div>
        {{-- Screen 3: Preferences & Instructions --}}
        <div class="mf-panel" data-panel="2" style="display:none; flex-direction:column; gap:8px;">
            <div class="mf-grid">{!! $sel('dietary_id', $diets, 'diet') !!}{!! $sel('customer_status_id', $customerStatuses, 'customer_status') !!}</div>
            <div class="mf-f"><label>{{ __('member_file.instructions') }}</label>
                <textarea name="personal_instructions" rows="5" style="resize:none;" placeholder="{{ __('member_file.instructions_hint') }}">{{ $v('personal_instructions') }}</textarea></div>
            <label style="display:flex; align-items:center; gap:6px; font-size:10.5px; color:#263238;"><input type="checkbox" name="pdpa_consent" value="1" {{ old('pdpa_consent', $isEdit && $p->pdpa_consent_at ? 1 : 0) ? 'checked' : '' }}> {{ __('member_file.pdpa') }}</label>
            @error('pdpa_consent')<span class="mf-err">{{ $message }}</span>@enderror
        </div>
        @if($isEdit)
        <div class="mf-panel" data-panel="3" style="display:none;">@include('admin.member-file._register')</div>
        <div class="mf-panel" data-panel="4" style="display:none;">@include('admin.member-file._committee')</div>
        @endif
    </form>

    <div class="mf-bar">
        <button type="button" class="mf-btn" onclick="mfPrev()">{{ __('masterfile.prev') }}</button>
        <span style="display:flex; gap:8px;">
            @if($isEdit)<button type="button" class="mf-btn" id="mf-save-btn" onclick="document.getElementById('mf-form').submit()">{{ __('member_file.save') }}</button>@endif
        </span>
        <button type="button" class="mf-btn" id="mf-next" onclick="mfNext()">{{ __('masterfile.next') }}</button>
    </div>
</div>
<script>
(function(){
    var panels = document.querySelectorAll('.mf-panel'), steps = document.querySelectorAll('.mf-steps span[data-step]');
    var cur = {{ $startPanel }}, last = panels.length - 1, isEdit = @json($isEdit), backUrl = @json($backUrl);
    var T = { next: @json(__('masterfile.next')), save: @json(__('member_file.save')) };
    function show(){
        panels.forEach(function(p, i){ p.style.display = i === cur ? (p.classList.contains('mf-grid') ? 'grid' : 'flex') : 'none'; if (i === cur && !p.classList.contains('mf-grid')) p.style.flexDirection = 'column'; });
        steps.forEach(function(s, i){ s.classList.toggle('on', i === cur); });
        var nb = document.getElementById('mf-next');
        nb.textContent = (!isEdit && cur === last) ? T.save : T.next;
        nb.style.visibility = (isEdit && cur === last) ? 'hidden' : 'visible';
        var sb = document.getElementById('mf-save-btn'); if (sb) sb.style.display = cur <= 2 ? '' : 'none';
        // no scroll: when the screen is short (e.g. browser zoom 175%), the whole
        // screen of boxes shrinks together until every box is visible
        var pnl = panels[cur], box = document.getElementById('mf-form'), z = 1;
        pnl.style.zoom = 1;
        var bottom = function(){ var b = 0; pnl.querySelectorAll('*').forEach(function(e){ if (e.offsetParent && !e.closest('.mf-pop')) b = Math.max(b, e.getBoundingClientRect().bottom); }); return b; };
        var limit = box.getBoundingClientRect().bottom - 10;
        while (bottom() > limit && z > 0.6) { z -= 0.03; pnl.style.zoom = z; }
        // one line per row, no hidden columns: shrink the table font together to fit the box
        panels[cur].querySelectorAll('table.mf-table').forEach(function(t){
            var box = t.parentNode, fs = 11; t.style.fontSize = fs + 'px';
            while ((t.scrollWidth > box.clientWidth + 1 || t.offsetHeight > box.clientHeight + 1) && fs > 6.5) { fs -= 0.25; t.style.fontSize = fs + 'px'; }
        });
    }
    window.mfShowPanel = show;
    window.mfPrev = function(){ if (cur > 0) { cur--; show(); } else { window.location.href = backUrl; } };
    window.mfNext = function(){
        if (!isEdit && cur === last) { document.getElementById('mf-form').submit(); return; }
        if (cur < last) { cur++; show(); }
    };
    // Postcode -> City / State (national postcode list)
    var pc = document.getElementById('mf-postcode');
    if (pc) pc.addEventListener('change', function(){
        if (!/^\d{5}$/.test(pc.value)) return;
        fetch(@json(route('register.postcode-lookup')) + '?postcode=' + pc.value).then(function(r){ return r.json(); }).then(function(d){
            if (d && d.city) { document.getElementById('mf-city').value = d.city; document.getElementById('mf-state').value = d.state || ''; }
        });
    });
    show();
})();
</script>
@endsection
