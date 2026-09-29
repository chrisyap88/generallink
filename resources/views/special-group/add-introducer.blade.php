@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('special_group.add_role_title', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0;">
    <div>
        <a href="{{ url()->previous() }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('network.back') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif
    </div>

    <form method="POST" action="{{ route('special-group.add-introducer.store') }}" style="margin-top:6px; flex:1 1 auto; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px; flex:1 1 auto; min-height:0; display:flex; flex-direction:column;">
            <div style="flex:1 1 auto; min-height:0; overflow-y:auto;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('special_group.add_role_to_group_heading', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</div>

            {{-- NEW 16 Jul 2026 — a group can now have multiple regional
                 Team Leaders, so a GL must pick which one/office this
                 Introducer belongs under. Only shown for GL callers —
                 a TL adding their own Introducer skips this entirely. --}}
            @if($teamLeaders->isNotEmpty())
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.role_office_required_label', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</label>
            <select name="team_leader_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box; margin-bottom:8px; background:#fff;">
                <option value="">{{ __('special_group.select_role_dash_dash', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</option>
                @foreach($teamLeaders as $tl)
                <option value="{{ $tl->agent_id }}" {{ old('team_leader_id') == $tl->agent_id ? 'selected' : '' }}>
                    {{ $tl->full_name }} ({{ $tl->agent_code }}){{ $tl->office_state ? ' — ' . $tl->office_state : '' }}
                </option>
                @endforeach
            </select>
            @endif

            {{-- NEW 1 Aug 2026 — per Chris: "when i register a new
            introducer... immediately you prompt the use to pick up his
            rank no." $ranksNeeded is resolved in the controller since
            the group is already fixed to $me->group_label_id here. --}}
            @if($ranksNeeded)
            <div style="margin-bottom:8px;">
                <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_rank_required') }}</label>
                <select name="rank_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; background:#fff; box-sizing:border-box;">
                    <option value="">{{ __('special_group.select_rank_dash_dash') }}</option>
                    @foreach($ranksNeeded as $r)
                    <option value="{{ $r->rank_id }}" {{ old('rank_id')==$r->rank_id?'selected':'' }}>{{ $r->rank_no }} — {{ $r->rank_name }}</option>
                    @endforeach
                </select>
            </div>
            @endif

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('customers.field_full_name_required') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.name_other_language') }}</label>
                    <input type="text" name="second_name" value="{{ old('second_name') }}" placeholder="{{ __('masterfile.optional_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_phone_required') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="{{ __('special_group.phone_mobile_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
            </div>

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_email_required') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box; margin-bottom:8px;">

            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_street_address_required') }}</label>
            <input type="text" name="address" value="{{ old('address') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box; margin-bottom:8px;">

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:10px;">
                <div style="position:relative;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_postcode_required') }}</label>
                    <input type="text" name="postcode" id="sg_postcode" value="{{ old('postcode') }}" required autocomplete="off" onkeyup="sgPC(this)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                    <div id="sg_pc_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div style="position:relative;">
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_city_required') }}</label>
                    <input type="text" name="city" id="sg_city" value="{{ old('city') }}" required autocomplete="off" onkeyup="sgCity(this)" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                    <div id="sg_city_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.field_state_required') }}</label>
                    <input type="text" name="state" id="sg_state" value="{{ old('state') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
                </div>
            </div>

            </div>
            <div style="flex-shrink:0; padding-top:10px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('special_group.add_role_button', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</button>
            </div>
        </div>
    </form>

</div>

<script>
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
