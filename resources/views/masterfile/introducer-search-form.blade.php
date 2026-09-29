@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', 'Search To Edit — ' . \App\Services\RoleLabelService::label('INTRODUCER'))

@section('content')
@php
    $rolePrefix = match(auth('agent')->user()->role) {
        'ADMIN' => 'admin', 'GROUP_LEADER' => 'gl', 'TEAM_LEADER' => 'tl', default => 'introducer',
    };
@endphp
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin-bottom:4px;">
        <a href="{{ route($rolePrefix . '.masterfile.introducers') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.back_to_dashboard') }}</a>
    </div>
    <p style="font-size:9.5px; color:#718096; margin:0 0 5px;">{{ __('masterfile.search_fill_instructions') }}</p>

    <form method="GET" action="{{ route($rolePrefix . '.masterfile.introducers') }}" id="searchForm">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:9px 14px;">

            <div style="font-size:9.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:5px; border-bottom:1px solid #e0f2fe; padding-bottom:3px;">{{ __('masterfile.search_all_label') }}</div>
            <div style="margin-bottom:8px;">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('masterfile.search_all_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 9px; font-size:11.5px; box-sizing:border-box;">
                <div style="font-size:8.5px; color:#94A3B8; margin-top:2px;">{{ __('masterfile.search_all_helper') }}</div>
            </div>

            <div style="font-size:9.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:5px; border-bottom:1px solid #e0f2fe; padding-bottom:3px;">{{ __('masterfile.identity') }}</div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:8px;">
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.agent_code') }}</label>
                    <input type="text" name="agent_code" value="{{ request('agent_code') }}" placeholder="e.g. 1-1-3" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.full_name') }}</label>
                    <input type="text" name="name" id="nameSearch" value="{{ request('name') }}" placeholder="Any part of name" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="nameDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div style="opacity:0.55;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#9ca3af; margin-bottom:2px;">{{ __('masterfile.nric') }}</label>
                    <input type="text" disabled placeholder="{{ __('masterfile.not_searchable') }}" style="width:100%; border:1px dashed #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box; background:#f3f4f6; color:#9ca3af;">
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.phone') }}</label>
                    <input type="text" name="phone" id="phoneSearch" value="{{ request('phone') }}" placeholder="012-3456789" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="phoneDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
            </div>

            <div style="font-size:9.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:5px; border-bottom:1px solid #e0f2fe; padding-bottom:3px;">{{ __('masterfile.contact_address') }}</div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin-bottom:8px;">
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.email') }}</label>
                    <input type="text" name="email" id="emailSearch" value="{{ request('email') }}" placeholder="name@mail.com" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="emailDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div style="grid-column:span 2;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.street_address') }}</label>
                    <input type="text" name="address" value="{{ request('address') }}" placeholder="Unit / street" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.postcode') }}</label>
                    <input type="text" name="postcode" id="postcodeSearch" value="{{ request('postcode') }}" placeholder="e.g. 41050" maxlength="5" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="postcodeDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.city') }}</label>
                    <input type="text" name="city" id="citySearch" value="{{ request('city') }}" placeholder="Type city" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="cityDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.state') }}</label>
                    <select name="state" id="stateSelect" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.any') }}</option>
                        @foreach($states as $st)
                            <option value="{{ $st }}" {{ request('state')==$st?'selected':'' }}>{{ $st }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.bank_name') }}</label>
                    <input type="text" name="bank_name" id="bankSearch" value="{{ request('bank_name') }}" placeholder="e.g. Maybank" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <div id="bankDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div style="opacity:0.55;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#9ca3af; margin-bottom:2px;">{{ __('masterfile.bank_account_no') }}</label>
                    <input type="text" disabled placeholder="{{ __('masterfile.not_searchable') }}" style="width:100%; border:1px dashed #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box; background:#f3f4f6; color:#9ca3af;">
                </div>
            </div>

            <div style="font-size:9.5px; font-weight:700; color:#1565C0; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:5px; border-bottom:1px solid #e0f2fe; padding-bottom:3px;">{{ __('masterfile.hierarchy_status') }}</div>
            <div style="display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr 1fr; gap:8px; margin-bottom:8px;">
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.sponsor_upline') }}</label>
                    <input type="text" id="sponsorSearch" placeholder="{{ __('masterfile.type_a_name') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box;">
                    <input type="hidden" name="sponsor_id" id="sponsorId" value="{{ request('sponsor_id') }}">
                    <div id="sponsorDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:160px; overflow-y:auto;"></div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.status') }}</label>
                    <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.any') }}</option>
                        <option value="ACTIVE" {{ request('status')=='ACTIVE'?'selected':'' }}>{{ __('masterfile.active') }}</option>
                        <option value="INACTIVE" {{ request('status')=='INACTIVE'?'selected':'' }}>{{ __('masterfile.inactive') }}</option>
                        <option value="TERMINATED" {{ request('status')=='TERMINATED'?'selected':'' }}>{{ __('masterfile.terminated') }}</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.joined_from') }}</label>
                    <input type="date" name="joined_from" value="{{ request('joined_from') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 5px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.joined_to') }}</label>
                    <input type="date" name="joined_to" value="{{ request('joined_to') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 5px; font-size:10px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.recruit_blocked') }}</label>
                    <select name="recruitment_blocked" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:11px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.any') }}</option>
                        <option value="1" {{ request('recruitment_blocked')=='1'?'selected':'' }}>{{ __('masterfile.yes') }}</option>
                        <option value="0" {{ request('recruitment_blocked')=='0'?'selected':'' }}>{{ __('masterfile.no') }}</option>
                    </select>
                </div>
            </div>

            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 24px; font-size:12px; font-weight:700; cursor:pointer;">{{ __('masterfile.search') }}</button>
        </div>
    </form>
</div>

<script>
function glTypeahead(inputId, dropdownId, mode, hiddenId) {
    var searchInput = document.getElementById(inputId);
    var dropdown    = document.getElementById(dropdownId);
    var hidden      = hiddenId ? document.getElementById(hiddenId) : null;
    var timer;

    searchInput.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (hidden) hidden.value = ''; // typing again invalidates a previous exact pick
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route('admin.network.ajax.typeahead') }}?mode=' + mode + '&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.length) { dropdown.style.display = 'none'; return; }
                    dropdown.innerHTML = '';
                    data.forEach(function(item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:7px 10px; font-size:11px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        d.innerHTML = item.name + ' <span style="color:#9ca3af;">— ' + item.code + '</span>';
                        d.onmouseover = function() { this.style.background = '#f0f9ff'; };
                        d.onmouseout  = function() { this.style.background = ''; };
                        d.onmousedown = function(e) {
                            e.preventDefault();
                            searchInput.value = item.name;
                            if (hidden) hidden.value = item.id;
                            dropdown.style.display = 'none';
                        };
                        dropdown.appendChild(d);
                    });
                    dropdown.style.display = 'block';
                }).catch(function() { dropdown.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== searchInput) dropdown.style.display = 'none';
    });
}

glTypeahead('sponsorSearch', 'sponsorDropdown', 'all', 'sponsorId');
glTypeahead('nameSearch', 'nameDropdown', 'intro', null);

// Phone / Email / Bank Name — new field-lookup endpoint, scoped to Introducers
function glFieldLookup(inputId, dropdownId, fieldName) {
    var input    = document.getElementById(inputId);
    var dropdown = document.getElementById(dropdownId);
    var timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 2) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch('{{ route($rolePrefix . '.masterfile.introducers.field-lookup') }}?field=' + fieldName + '&q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.length) { dropdown.style.display = 'none'; return; }
                    dropdown.innerHTML = '';
                    data.forEach(function(item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:7px 10px; font-size:11px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        d.innerHTML = item.value + ' <span style="color:#9ca3af;">— ' + item.name + '</span>';
                        d.onmouseover = function() { this.style.background = '#f0f9ff'; };
                        d.onmouseout  = function() { this.style.background = ''; };
                        d.onmousedown = function(e) {
                            e.preventDefault();
                            input.value = item.value;
                            dropdown.style.display = 'none';
                        };
                        dropdown.appendChild(d);
                    });
                    dropdown.style.display = 'block';
                }).catch(function() { dropdown.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input) dropdown.style.display = 'none';
    });
}

glFieldLookup('phoneSearch', 'phoneDropdown', 'phone');
glFieldLookup('emailSearch', 'emailDropdown', 'email');
glFieldLookup('bankSearch', 'bankDropdown', 'bank_name');

// Postcode / City — reuse the SAME public postcode-lookup endpoint built
// for the registration form (reads the shared malaysia_postcodes table).
// On pick, also set the State dropdown to match, same as register.blade.php.
function glPostcodeCity(inputId, dropdownId, queryParam) {
    var input    = document.getElementById(inputId);
    var dropdown = document.getElementById(dropdownId);
    var timer;

    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = this.value.trim();
        var minLen = queryParam === 'postcode' ? 3 : 2;
        if (q.length < minLen) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function() {
            var url = '{{ route('register.postcode-lookup') }}?' + queryParam + '=' + encodeURIComponent(q);
            if (queryParam === 'postcode') url += '&partial=1';
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.length) { dropdown.style.display = 'none'; return; }
                    dropdown.innerHTML = '';
                    var seen = {};
                    data.forEach(function(item) {
                        var key = item.postcode + item.city;
                        if (seen[key]) return; seen[key] = true;
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:7px 10px; font-size:11px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' <span style="color:#9ca3af;">(' + item.state + ')</span>';
                        d.onmouseover = function() { this.style.background = '#f0f9ff'; };
                        d.onmouseout  = function() { this.style.background = ''; };
                        d.onmousedown = function(e) {
                            e.preventDefault();
                            document.getElementById('postcodeSearch').value = item.postcode;
                            document.getElementById('citySearch').value = item.city;
                            var s = document.getElementById('stateSelect');
                            for (var i = 0; i < s.options.length; i++) { if (s.options[i].value === item.state) { s.selectedIndex = i; break; } }
                            dropdown.style.display = 'none';
                        };
                        dropdown.appendChild(d);
                    });
                    dropdown.style.display = 'block';
                }).catch(function() { dropdown.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input) dropdown.style.display = 'none';
    });
}

glPostcodeCity('postcodeSearch', 'postcodeDropdown', 'postcode');
glPostcodeCity('citySearch', 'cityDropdown', 'city');
</script>
@endsection
