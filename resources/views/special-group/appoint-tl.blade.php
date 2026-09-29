@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('special_group.appoint_role_button', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box;">

    <div style="flex:1 1 auto; min-height:0; overflow-y:auto;">

    <div>
        <a href="{{ route('gl.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('tl.back_to_dashboard_link') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin:6px 0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:11px; margin:6px 0; max-width:480px;">{{ session('success') }}</div>
    @endif

    {{-- UPDATED 16 Jul 2026 — multiple regional Team Leaders are now
         supported (e.g. PVATM's 14 state offices). Existing TLs are
         listed here; the appoint form below is always available to add
         another, no longer blocked after the first one. --}}
    @if($existingTLs->isNotEmpty())
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; max-width:560px; margin-top:6px; margin-bottom:14px;">
        <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('special_group.existing_role_count_heading', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER'), 'count' => $existingTLs->count()]) }}</div>
        <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:11px;">
            <colgroup>
                <col style="width:18%;">
                <col style="width:42%;">
                <col style="width:40%;">
            </colgroup>
            <thead>
                <tr style="border-bottom:1px solid #e0f2fe; color:#9ca3af;">
                    <th style="text-align:left; padding:3px 6px;">{{ __('network.col_code') }}</th>
                    <th style="text-align:left; padding:3px 6px;">{{ __('masterfile.col_full_name') }}</th>
                    <th style="text-align:left; padding:3px 6px;">{{ __('special_group.col_office_state') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($existingTLs as $tl)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:4px 6px; color:#1565C0; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $tl->agent_code }}</td>
                    <td style="padding:4px 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $tl->full_name }}</td>
                    <td style="padding:4px 6px; color:#4b5563; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $tl->office_city ? $tl->office_city . ', ' : '' }}{{ $tl->office_state ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <form method="POST" action="{{ route('special-group.appoint-tl.store') }}" style="margin-top:6px;">
        @csrf
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; max-width:480px;">
            <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:10px;">{{ __('special_group.appoint_your_role_heading', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</div>

            {{-- NEW 1 Aug 2026 — per Chris: prompt for rank right at
            registration when this group requires it. $ranksNeeded is
            resolved in the controller since the group is already fixed
            to the acting GL's own group here. --}}
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

            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('special_group.appoint_role_button', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</button>
        </div>
    </form>

    </div>

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
