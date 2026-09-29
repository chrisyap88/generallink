<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ __('special_group.join_group_title', ['group' => $label->group_name]) }} — GeneralLink</title>
    <style>
        body { font-family: -apple-system, sans-serif; background:#E0F7FA; margin:0; padding:20px; }
        .container { max-width:520px; margin:0 auto; }
        .logo-wrap { text-align:center; margin-bottom:16px; }
        .logo-wrap img { max-height:80px; }
        .card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.08); }
        h1 { font-size:16px; color:#1565C0; margin:0 0 4px; text-align:center; }
        .subtitle { font-size:11.5px; color:#6b7280; text-align:center; margin-bottom:16px; }
        label { font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
        input { width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; box-sizing:border-box; margin-bottom:10px; }
        .req { color:#e53935; }
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; }
        .btn { width:100%; background:#1565C0; color:#fff; border:none; border-radius:8px; padding:10px; font-size:13px; font-weight:600; cursor:pointer; }
        .success { background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:8px 12px; font-size:11px; margin-bottom:12px; }
        .error { background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:8px 12px; font-size:11px; margin-bottom:12px; }
    </style>
</head>
<body>
<div class="container">

    @if($label->logo_path)
    <div class="logo-wrap">
        <img src="{{ asset('image/' . $label->logo_path) }}" alt="{{ $label->group_name }}">
    </div>
    @endif

    <div class="card">
        <h1>{{ __('special_group.join_group_title', ['group' => $label->group_name]) }}</h1>
        <div class="subtitle">{{ __('special_group.register_as_role_under_note', ['role' => \App\Services\RoleLabelService::label('INTRODUCER'), 'name' => $tl->full_name ?? 'our team']) }}</div>

        @if(session('success'))
        <div class="success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
        <div class="error">
            @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        @if(!$tl)
        <div class="error">{{ __('special_group.group_not_ready_note') }}</div>
        @else

        <form method="POST" action="{{ route('special-group.join.submit', $label->group_label_id) }}">
            @csrf

            <div class="grid-3">
                <div>
                    <label>{{ __('customers.field_full_name_required') }}</label>
                    <input type="text" name="full_name" value="{{ old('full_name') }}" required>
                </div>
                <div>
                    <label>{{ __('masterfile.name_other_language') }}</label>
                    <input type="text" name="second_name" value="{{ old('second_name') }}" placeholder="{{ __('masterfile.optional_placeholder') }}">
                </div>
                <div>
                    <label>{{ __('special_group.field_phone_required') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" required placeholder="{{ __('special_group.phone_mobile_placeholder') }}">
                </div>
            </div>

            <label>{{ __('special_group.field_email_required') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" required>

            <label>{{ __('special_group.field_street_address_required') }}</label>
            <input type="text" name="address" value="{{ old('address') }}" required>

            <div class="grid-3">
                <div style="position:relative;">
                    <label>{{ __('special_group.field_postcode_required') }}</label>
                    <input type="text" name="postcode" id="sg_postcode" value="{{ old('postcode') }}" required autocomplete="off" onkeyup="sgPC(this)">
                    <div id="sg_pc_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div style="position:relative;">
                    <label>{{ __('special_group.field_city_required') }}</label>
                    <input type="text" name="city" id="sg_city" value="{{ old('city') }}" required autocomplete="off" onkeyup="sgCity(this)">
                    <div id="sg_city_dd" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:150px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label>{{ __('special_group.field_state_required') }}</label>
                    <input type="text" name="state" id="sg_state" value="{{ old('state') }}" required>
                </div>
            </div>

            <button type="submit" class="btn">{{ __('special_group.join_now_button') }}</button>
        </form>
        @endif
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
</body>
</html>
