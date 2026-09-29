@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('special_group.create_special_role_title', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.masterfile.group-names') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('masterfile.back_to_group_name_maintenance') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    @if($labels->isEmpty())
    <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:14px; font-size:11.5px; color:#92400e; max-width:480px; margin-top:6px;">
        {{ __('special_group.no_group_names_note') }}
    </div>
    @else

    <form method="POST" action="{{ route('admin.special-group.create-gl.store') }}" style="margin-top:6px;">
        @csrf
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; max-width:760px;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('special_group.create_role_for_group_heading', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</div>

            <div style="margin-bottom:5px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.org_rewards_group_label') }} <span style="color:#e53935;">*</span></label>
                <select name="group_label_id" id="sg_group_label_id" onchange="sgToggleRankPicker(this.value)" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; background:#fff; box-sizing:border-box;">
                    <option value="">{{ __('special_group.select_dash_dash') }}</option>
                    @foreach($labels as $l)
                        <option value="{{ $l->group_label_id }}" {{ old('group_label_id')==$l->group_label_id?'selected':'' }}>{{ $l->group_name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- NEW 1 Aug 2026 — per Chris: prompt for rank right at
            registration when the chosen group requires it. Which group
            is only known once the dropdown above is picked, so this
            picker is built client-side from $ranksByGroup and revealed
            via JS rather than server-rendered up front. --}}
            <div id="sg_rank_wrap" style="display:none; margin-bottom:5px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_rank_required') }}</label>
                <select name="rank_id" id="sg_rank_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; background:#fff; box-sizing:border-box;">
                    <option value="">{{ __('special_group.select_rank_dash_dash') }}</option>
                </select>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px; margin-bottom:5px;">
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('customers.field_full_name_required') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.name_other_language') }}</label>
                    <input type="text" name="second_name" value="{{ old('second_name') }}" placeholder="{{ __('masterfile.optional_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_phone_required') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="{{ __('special_group.phone_landline_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_email_required') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
            </div>

            <div style="margin-bottom:5px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_street_address_required') }}</label>
                <input type="text" name="address" value="{{ old('address') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                <div style="position:relative;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_postcode_required') }}</label>
                    <input type="text" name="postcode" id="sg_postcode" value="{{ old('postcode') }}" required autocomplete="off" onkeyup="sgPC(this)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                    <div id="sg_pc_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div style="position:relative;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_city_required') }}</label>
                    <input type="text" name="city" id="sg_city" value="{{ old('city') }}" required autocomplete="off" onkeyup="sgCity(this)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                    <div id="sg_city_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('special_group.field_state_required') }}</label>
                    <input type="text" name="state" id="sg_state" value="{{ old('state') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
            </div>

            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('special_group.create_role_button', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</button>
        </div>
    </form>
    @endif

</div>

<script>
// NEW 1 Aug 2026 — ranks per group_label_id that requires a rank pick
// at registration (empty for groups that don't require one). Built
// server-side in createGLForm() from ranksRequiringSelection().
var SG_RANKS_BY_GROUP = {!! json_encode(collect($ranksByGroup ?? [])->map(function ($ranks) {
    return collect($ranks)->map(fn ($r) => ['rank_id' => $r->rank_id, 'rank_no' => $r->rank_no, 'rank_name' => $r->rank_name])->values();
})) !!};

function sgToggleRankPicker(groupLabelId) {
    var wrap = document.getElementById('sg_rank_wrap');
    var sel = document.getElementById('sg_rank_id');
    var ranks = SG_RANKS_BY_GROUP[groupLabelId];
    if (!ranks || !ranks.length) {
        wrap.style.display = 'none';
        sel.removeAttribute('required');
        sel.innerHTML = '<option value="">-- Select Rank --</option>';
        return;
    }
    sel.innerHTML = '<option value="">-- Select Rank --</option>';
    ranks.forEach(function (r) {
        var o = document.createElement('option');
        o.value = r.rank_id;
        o.textContent = r.rank_no + ' — ' + r.rank_name;
        sel.appendChild(o);
    });
    sel.setAttribute('required', 'required');
    wrap.style.display = 'block';
}
// Re-apply on page load in case of a validation-error redisplay with
// an old('group_label_id') already selected.
document.addEventListener('DOMContentLoaded', function () {
    var g = document.getElementById('sg_group_label_id');
    if (g && g.value) sgToggleRankPicker(g.value);
});

var _sgPCt, _sgCTt;
function sgPC(inp) {
    clearTimeout(_sgPCt);
    var v = inp.value.trim(), dd = document.getElementById('sg_pc_dd');
    if (v.length < 3) { dd.style.display = 'none'; return; }
    _sgPCt = setTimeout(function () {
        fetch('{{ route('register.postcode-lookup') }}?postcode=' + encodeURIComponent(v) + '&partial=1')
        .then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = '';
            data.forEach(function (item) {
                var d = document.createElement('div');
                d.style.cssText = 'padding:6px 8px; cursor:pointer; font-size:11px; border-bottom:1px solid #f3f4f6;';
                d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                d.onmousedown = function (e) {
                    e.preventDefault();
                    document.getElementById('sg_postcode').value = item.postcode;
                    document.getElementById('sg_city').value = item.city;
                    document.getElementById('sg_state').value = item.state;
                    dd.style.display = 'none';
                };
                dd.appendChild(d);
            });
            dd.style.display = 'block';
        }).catch(function () { dd.style.display = 'none'; });
    }, 300);
}
function sgCity(inp) {
    clearTimeout(_sgCTt);
    var v = inp.value.trim(), dd = document.getElementById('sg_city_dd');
    if (v.length < 2) { dd.style.display = 'none'; return; }
    _sgCTt = setTimeout(function () {
        fetch('{{ route('register.postcode-lookup') }}?city=' + encodeURIComponent(v))
        .then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.length) { dd.style.display = 'none'; return; }
            dd.innerHTML = '';
            var seen = {};
            data.forEach(function (item) {
                if (seen[item.city]) return; seen[item.city] = true;
                var d = document.createElement('div');
                d.style.cssText = 'padding:6px 8px; cursor:pointer; font-size:11px; border-bottom:1px solid #f3f4f6;';
                d.innerHTML = item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                d.onmousedown = function (e) {
                    e.preventDefault();
                    document.getElementById('sg_city').value = item.city;
                    document.getElementById('sg_state').value = item.state;
                    dd.style.display = 'none';
                };
                dd.appendChild(d);
            });
            dd.style.display = 'block';
        }).catch(function () { dd.style.display = 'none'; });
    }, 300);
}
document.addEventListener('click', function(e) {
    if (e.target.id !== 'sg_postcode') document.getElementById('sg_pc_dd').style.display = 'none';
    if (e.target.id !== 'sg_city') document.getElementById('sg_city_dd').style.display = 'none';
});
</script>
@endsection
