@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.plans_title'))
@section('content')
{{-- NEW 28 Sep 2026 — Master File › Membership Plans, per CBE group:
     plan name, period (Free / Yearly / One-time / Lifetime) and fee.
     A Member line in the Affiliation Register uses one of these plans; the
     fee status (Paid / Due / Overdue) is worked out from it. --}}
@include('admin.member-file._style')
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.plans_title') }}</div>
    <div class="mf-f" style="flex-shrink:0; max-width:340px;">
        <label>{{ __('member_file.group') }}</label>
        <input id="mp-group" list="mp-group-list" value="{{ $group->group_name ?? '' }}" autocomplete="off">
        <datalist id="mp-group-list">@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
    </div>
    @if(session('saved'))<div class="mf-ok">✓ {{ __('member_file.saved_title') }}</div>@endif
    @if($group)
    <div class="mf-box" style="display:flex; flex-direction:column; gap:8px;">
        <form method="POST" action="{{ route('admin.membership-plans.save') }}" style="display:flex; gap:10px; align-items:flex-end; margin:0;">
            @csrf
            <input type="hidden" name="group" value="{{ $group->group_label_id }}">
            <input type="hidden" name="id" id="mp-id">
            <div class="mf-f" style="flex:1.3;"><label>{{ __('member_file.membership_type') }}</label>
                <select name="membership_type_id" id="mp-type"><option value="">{{ __('member_file.select') }}</option>@foreach($types as $ty)<option value="{{ $ty->id }}">{{ $ty->label }}</option>@endforeach</select></div>
            <div class="mf-f" style="flex:2;"><label>{{ __('member_file.plan_name') }}</label><input name="plan_name" id="mp-name" required maxlength="120"></div>
            <div class="mf-f" style="flex:1;"><label>{{ __('member_file.period') }}</label>
                <select name="period" id="mp-period">@foreach(['FREE', 'YEARLY', 'ONE_TIME', 'LIFETIME'] as $pd)<option value="{{ $pd }}">{{ __('member_file.period_'.$pd) }}</option>@endforeach</select></div>
            <div class="mf-f" style="flex:1;"><label>{{ __('member_file.fee') }}</label><input name="fee" id="mp-fee" type="number" step="0.01" min="0"></div>
            <button type="submit" class="mf-btn" id="mp-btn">{{ __('member_file.add_row') }}</button>
        </form>
        @if($errors->any())<div class="mf-err">{{ $errors->first() }}</div>@endif
        <div id="mp-wrap" style="flex:1; min-height:0; overflow:hidden;">
            <table class="mf-table" id="mp-table">
                <thead><tr><th style="width:30px;">#</th><th>{{ __('member_file.membership_type') }}</th><th>{{ __('member_file.plan_name') }}</th><th>{{ __('member_file.period') }}</th><th>{{ __('member_file.fee') }}</th><th></th></tr></thead>
                <tbody>
                @forelse($rows as $i => $r)
                    <tr><td>{{ $i + 1 }}</td><td>{{ optional($types->firstWhere('id', $r->membership_type_id))->label ?? '—' }}</td><td>{{ $r->plan_name }}</td><td>{{ __('member_file.period_'.$r->period) }}</td><td>{{ number_format((float) $r->fee, 2) }}</td>
                        <td><a href="#" style="color:#1565C0; font-weight:700; text-decoration:none;" onclick='mpEdit({{ json_encode(["id" => $r->id, "name" => $r->plan_name, "period" => $r->period, "fee" => $r->fee, "type" => $r->membership_type_id], JSON_HEX_APOS | JSON_HEX_QUOT) }}); return false;'>{{ __('member_file.edit') }}</a></td></tr>
                @empty
                    <tr><td colspan="6" style="color:#9ca3af; padding:14px; text-align:center;">—</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div style="flex:1;"></div>
    @endif
    <div class="mf-bar">
        <a href="{{ route('admin.member-pick-lists.index') }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span>
        <a href="{{ route('admin.member-file.landing') }}" class="mf-btn">{{ __('masterfile.next') }}</a>
    </div>
</div>
<script>
(function(){
    var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values());
    var gi = document.getElementById('mp-group');
    gi.addEventListener('change', function(){
        var hit = groups.filter(function(g){ return g.v === gi.value; })[0];
        if (hit) window.location.href = @json(route('admin.membership-plans.index')) + '?group=' + encodeURIComponent(hit.id);
    });
    var per = document.getElementById('mp-period'), fee = document.getElementById('mp-fee');
    function sync(){ if (!per) return; fee.disabled = per.value === 'FREE'; if (fee.disabled) fee.value = ''; }
    if (per) per.addEventListener('change', sync); sync();
    window.mpEdit = function(r){ document.getElementById('mp-id').value = r.id; document.getElementById('mp-name').value = r.name; document.getElementById('mp-type').value = r.type || ''; per.value = r.period; sync(); if (!fee.disabled) fee.value = r.fee; document.getElementById('mp-btn').textContent = @json(__('member_file.save')); };
    var w = document.getElementById('mp-wrap'), t = document.getElementById('mp-table'), fs = 11;
    if (w) while (t.offsetHeight > w.clientHeight && fs > 7) { fs -= 0.25; t.style.fontSize = fs + 'px'; }
})();
</script>
@endsection
