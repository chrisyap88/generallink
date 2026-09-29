<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.register_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }

        .left-panel {
            flex: 0 0 30%;
            height: 100vh;
            background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%);
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; padding: 2vh 1.5vw; gap: 1.2vh;
        }
        .logo-img { width: 100%; max-width: 260px; border-radius: 14px; box-shadow: 0 6px 24px rgba(0,131,143,0.2); display: block; }
        .left-title { font-family: 'Rajdhani', sans-serif; font-size: clamp(1rem, 1.9vh, 1.3rem); font-weight: 700; color: #0D5A8E; text-align: center; }
        .left-sub { font-size: clamp(.6rem, 1.15vh, .72rem); color: #0D5A8E; text-align: center; line-height: 1.4; }
        .info-box { background: rgba(255,255,255,0.8); border-radius: 8px; padding: .8vh .8rem; font-size: clamp(.56rem, 1.05vh, .68rem); color: #0D5A8E; line-height: 1.4; width: 100%; border-left: 3px solid #1B9AE4; }
        .signin-link { font-size: clamp(.6rem, 1.15vh, .72rem); color: #718096; }
        .signin-link a { color: #0D5A8E; font-weight: 700; text-decoration: none; }

        .right-panel { flex: 1; height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 1.5vh 2.2vw; background: #fff; overflow: hidden; }

        h1 { font-family: 'Rajdhani', sans-serif; font-size: clamp(1.15rem, 2.6vh, 1.6rem); font-weight: 700; color: #0D5A8E; margin-bottom: .3vh; }
        .subtitle { font-size: clamp(.65rem, 1.3vh, .75rem); color: #38A169; font-weight: 500; margin-bottom: 1vh; }

        .alert-error { background: #fde8e8; border-left: 3px solid #e53935; border-radius: 6px; padding: .6vh .8rem; font-size: clamp(.64rem, 1.2vh, .74rem); color: #b71c1c; margin-bottom: 1vh; }

        /* Upline section */
        .upline-section { background: #f0f9ff; border-radius: 8px; padding: 1vh .9rem; margin-bottom: 1vh; border: 2px solid #1B9AE4; }
        .upline-title { font-size: clamp(.64rem, 1.25vh, .74rem); font-weight: 700; color: #0D5A8E; margin-bottom: .8vh; display: flex; align-items: center; gap: .4rem; }
        .upline-badge { background: #1B9AE4; color: #fff; border-radius: 20px; padding: .15vh .6rem; font-size: clamp(.52rem, 1vh, .6rem); font-weight: 600; }
        .upline-row { display: grid; grid-template-columns: 1fr 1fr; gap: .8vh .6rem; }

        .lookup-result { margin-top: .5vh; padding: .6vh .7rem; border-radius: 5px; font-size: clamp(.6rem, 1.15vh, .7rem); display: none; }
        .lookup-result.found { background: #e8f5e9; border-left: 3px solid #38A169; color: #1b5e20; display: block; }
        .lookup-result.error { background: #fde8e8; border-left: 3px solid #e53935; color: #b71c1c; display: block; }
        .lookup-result.searching { background: #e3f2fd; border-left: 3px solid #1B9AE4; color: #0D5A8E; display: block; }
        .lookup-name { font-weight: 700; }
        .lookup-role { display: inline-block; background: #1B9AE4; color: #fff; border-radius: 20px; padding: .1vh .45rem; font-size: clamp(.52rem, 1vh, .6rem); font-weight: 600; margin-left: .3rem; }
        .lookup-status { display: inline-block; background: #38A169; color: #fff; border-radius: 20px; padding: .1vh .45rem; font-size: clamp(.52rem, 1vh, .6rem); font-weight: 600; margin-left: .2rem; }

        .admin-assign-box { background: #fff8e1; border-radius: 6px; padding: .6vh .7rem; border-left: 3px solid #D97706; font-size: clamp(.6rem, 1.15vh, .7rem); color: #92400e; margin-top: .6vh; display: none; }
        .admin-assign-box.show { display: block; }

        /* Section label */
        .section-label { font-size: clamp(.56rem, 1.1vh, .65rem); font-weight: 700; color: #0D5A8E; letter-spacing: .06em; text-transform: uppercase; margin: .8vh 0 .6vh; padding-bottom: .3vh; border-bottom: 1.5px solid #b2ebf2; }

        /* Grids */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .7vh .9rem; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: .7vh .9rem; }
        .col-full { grid-column: 1 / -1; }

        label { display: block; font-size: clamp(.58rem, 1.1vh, .68rem); font-weight: 600; color: #2D3748; margin-bottom: .3vh; }
        label span.req { color: #e53935; margin-left: 1px; }

        input[type="text"], input[type="email"], input[type="tel"], select, textarea {
            width: 100%; padding: .8vh .7rem;
            border: 1.5px solid #b2ebf2; border-radius: 7px;
            font-size: clamp(.68rem, 1.3vh, .78rem); font-family: 'Outfit', sans-serif;
            color: #2D3748; background: #f7fdff; outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        textarea { resize: none; height: 6.2vh; min-height: 38px; }
        input:focus, select:focus, textarea:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 2px rgba(27,154,228,.10); }

        .field-hint { font-size: clamp(.52rem, 1vh, .6rem); color: #718096; margin-top: .25vh; }
        .error-msg { font-size: clamp(.54rem, 1.05vh, .62rem); color: #e53935; margin-top: .25vh; }

        .btn-submit {
            width: 100%; padding: 1.1vh;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff; border: none; border-radius: 8px;
            font-size: clamp(.72rem, 1.4vh, .82rem); font-family: 'Outfit', sans-serif;
            font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
            cursor: pointer; box-shadow: 0 4px 12px rgba(13,90,142,.22);
            margin-top: .8vh;
        }
        .btn-submit:hover { opacity: .93; }
        .form-footer { text-align: center; margin-top: .7vh; font-size: clamp(.5rem, .95vh, .58rem); letter-spacing: .1em; text-transform: uppercase; color: #b0bec5; }
    </style>
</head>
<body>

<!-- LEFT -->
<div class="left-panel">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink" />
    <div class="left-title">{{ __('auth.register_left_title') }}</div>
    <div class="left-sub">{{ __('auth.register_left_sub') }}</div>
    <div class="info-box">● {{ __('auth.register_info_box') }}</div>
    <div class="signin-link">{{ __('auth.already_have_account_prompt') }} <a href="{{ route('auth.login') }}">{{ __('auth.sign_in_here_link') }}</a></div>
    @include('partials.intro-video-widget')
</div>

<!-- RIGHT -->
<div class="right-panel">
    <h1>{{ __('auth.create_account_heading') }}</h1>
    <p class="subtitle">{{ __('auth.register_subtitle') }}</p>

    @if ($errors->any())
    <div class="alert-error">
        @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route(request()->routeIs('admin.*') ? 'admin.masterfile.introducers.create.store' : 'auth.register.post') }}">
        @csrf

        <!-- STEP 1 — UPLINE -->
        <div class="upline-section">
            <div class="upline-title">🔗 {{ __('auth.upline_step_title') }} <span class="upline-badge">{{ __('auth.select_first_badge') }}</span></div>

            <div style="display:flex; gap:.7rem; align-items:flex-start;">
                <div style="flex:0 0 auto; background:#fff; border:1px dashed #1B9AE4; border-radius:6px; padding:.5vh .7rem;">
                    <label style="font-size:clamp(.56rem,1.05vh,.64rem); font-weight:700; color:#0D5A8E; display:block; margin-bottom:.25vh; white-space:nowrap;">📷 {{ __('auth.qr_option_label') }}</label>
                    <input type="file" id="qr_upload" accept="image/*" onchange="handleQrUpload(this)" style="font-size:clamp(.56rem,1vh,.62rem); max-width:170px;">
                </div>

                <div style="flex:0 0 auto; align-self:center; font-size:clamp(.56rem,1.05vh,.64rem); color:#9ca3af; padding-top:1vh;">{{ __('auth.or_word') }}</div>

                <div style="flex:1; background:#fff; border-radius:6px; padding:.5vh .7rem;">
                    <label style="font-size:clamp(.56rem,1.05vh,.64rem); font-weight:700; color:#374151; display:block; margin-bottom:.25vh;">{{ __('auth.search_option_label') }}</label>
                    <div style="display:flex; gap:.6rem;">
                        <div style="flex:0 0 120px;">
                            <label style="font-size:clamp(.54rem,1vh,.6rem);">{{ __('auth.upline_type_label') }} <span class="req">*</span></label>
                            <select id="upline_type" name="upline_type" required onchange="handleUplineChange(this.value)" style="width:100%;" data-ai-nav="upline-type-dropdown">
                                <option value="">{{ __('auth.select_default_option') }}</option>
                                <option value="GROUP_LEADER"  {{ old('upline_type')=='GROUP_LEADER'  ?'selected':'' }}>{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</option>
                                <option value="TEAM_LEADER"   {{ old('upline_type')=='TEAM_LEADER'   ?'selected':'' }}>{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</option>
                                <option value="INTRODUCER"    {{ old('upline_type')=='INTRODUCER'    ?'selected':'' }}>{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</option>
                                <option value="ADMIN_ASSIGN"  {{ old('upline_type')=='ADMIN_ASSIGN'  ?'selected':'' }}>{{ __('auth.admin_assign_option') }}</option>
                            </select>
                            @error('upline_type') <div class="error-msg">{{ $message }}</div> @enderror
                        </div>
                        <div id="affiliate-code-field" style="display:none; position:relative; flex:1;">
                            <label style="font-size:clamp(.54rem,1vh,.6rem);">{{ __('auth.name_or_code_label') }} <span class="req">*</span></label>
                            <input type="text" id="affiliate_search" placeholder="{{ __('auth.name_or_code_placeholder') }}" style="width:100%;"
                                oninput="debounceLookup(this.value)" autocomplete="off" data-ai-nav="affiliate-search-input" />
                            <div class="field-hint">{{ __('auth.min_2_chars_hint') }}</div>
                            <div id="affiliate_dropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #b2ebf2; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:30; max-height:18vh; overflow-y:auto;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lookup-result" id="lookup-result"></div>
            <div class="admin-assign-box" id="admin-assign-box" data-ai-nav="admin-assign-notice">⚠ {{ __('auth.admin_assign_notice') }}</div>
            <input type="hidden" name="upline_agent_id" id="upline_agent_id" value="{{ old('upline_agent_id') }}" data-ai-nav="upline-agent-id-hidden" />
        </div>

        <!-- STEP 2 — PERSONAL DETAILS -->
        <div class="section-label">{{ __('auth.step2_personal_details') }}</div>
        <div class="grid-2">
            <div>
                <label>{{ __('masterfile.full_name') }} <span class="req">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" placeholder="{{ __('auth.nric_placeholder') }}" required data-ai-nav="full-name-input" />
                <div class="field-hint">{{ __('auth.nric_hint') }}</div>
                @error('name') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div>
                <label>{{ __('auth.mobile_phone_label') }} <span class="req">*</span></label>
                <input type="tel" name="phone" value="{{ old('phone') }}" placeholder="{{ __('auth.phone_placeholder') }}" required data-ai-nav="phone-input" />
                <div class="field-hint">{{ __('auth.phone_hint') }}</div>
                @error('phone') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div>
                <label>{{ __('auth.email_label') }} <span class="req">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" placeholder="{{ __('auth.email_placeholder') }}" required data-ai-nav="email-input" />
                @error('email') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="col-full" style="grid-column: 2 / 4;">
                <label>{{ __('masterfile.street_address') }} <span class="req">*</span></label>
                <input type="text" name="address" value="{{ old('address') }}" placeholder="{{ __('auth.street_address_placeholder') }}" required data-ai-nav="address-input" />
                @error('address') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div style="position:relative;">
                <label>{{ __('masterfile.postcode') }} <span class="req">*</span></label>
                <input type="text" name="postcode" id="reg_postcode" value="{{ old('postcode') }}" placeholder="{{ __('auth.postcode_placeholder') }}" maxlength="5" autocomplete="off" oninput="regPC(this)" required data-ai-nav="postcode-input" />
                <div id="reg_pc_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:22vh;overflow-y:auto;"></div>
                @error('postcode') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div style="position:relative;">
                <label>{{ __('masterfile.city') }} <span class="req">*</span></label>
                <input type="text" name="city" id="reg_city" value="{{ old('city') }}" placeholder="{{ __('auth.city_placeholder') }}" autocomplete="off" oninput="regCity(this)" required data-ai-nav="city-input" />
                <div id="reg_city_dd" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #d1d5db;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,.15);z-index:9999;max-height:22vh;overflow-y:auto;"></div>
                @error('city') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div>
                <label>{{ __('masterfile.state') }} <span class="req">*</span></label>
                <select name="state" id="reg_state" required data-ai-nav="state-dropdown">
                    <option value="">{{ __('auth.select_state_option') }}</option>
                    @foreach(($states ?? []) as $state)
                        <option value="{{ $state }}" {{ old('state')==$state?'selected':'' }}>{{ $state }}</option>
                    @endforeach
                </select>
                @error('state') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div>
                <label>{{ __('auth.second_name_label') }}</label>
                <input type="text" name="second_name" value="{{ old('second_name') }}" placeholder="{{ __('auth.second_name_placeholder') }}" data-ai-nav="second-name-input" />
                <div class="field-hint">{{ __('auth.second_name_hint') }}</div>
                @error('second_name') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
        </div>

        <!-- Postcode -> City/State auto-lookup, reads the shared malaysia_postcodes table -->
        <script>
        var _regPCt, _regCTt;
        function regPC(inp) {
            clearTimeout(_regPCt);
            var v = inp.value.trim(), dd = document.getElementById('reg_pc_dd');
            if (v.length < 3) { dd.style.display = 'none'; return; }
            _regPCt = setTimeout(function () {
                fetch('{{ route('register.postcode-lookup') }}?postcode=' + encodeURIComponent(v) + '&partial=1')
                .then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || !data.length) { dd.style.display = 'none'; return; }
                    dd.innerHTML = '';
                    data.forEach(function (item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:.7vh .8rem;cursor:pointer;font-size:.72rem;border-bottom:1px solid #f3f4f6;white-space:nowrap;';
                        d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                        d.onmouseover = function () { this.style.background = '#f0f9ff'; };
                        d.onmouseout  = function () { this.style.background = ''; };
                        d.onmousedown = function (e) {
                            e.preventDefault();
                            document.getElementById('reg_postcode').value = item.postcode;
                            document.getElementById('reg_city').value = item.city;
                            var s = document.getElementById('reg_state');
                            for (var i = 0; i < s.options.length; i++) { if (s.options[i].value === item.state) { s.selectedIndex = i; break; } }
                            dd.style.display = 'none';
                        };
                        dd.appendChild(d);
                    });
                    dd.style.display = 'block';
                }).catch(function () { dd.style.display = 'none'; });
            }, 300);
        }
        function regCity(inp) {
            clearTimeout(_regCTt);
            var v = inp.value.trim(), dd = document.getElementById('reg_city_dd');
            if (v.length < 2) { dd.style.display = 'none'; return; }
            _regCTt = setTimeout(function () {
                fetch('{{ route('register.postcode-lookup') }}?city=' + encodeURIComponent(v))
                .then(function (r) { return r.json(); }).then(function (data) {
                    if (!data || !data.length) { dd.style.display = 'none'; return; }
                    dd.innerHTML = '';
                    var seen = {};
                    data.forEach(function (item) {
                        if (seen[item.city]) return; seen[item.city] = true;
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:.7vh .8rem;cursor:pointer;font-size:.72rem;border-bottom:1px solid #f3f4f6;white-space:nowrap;';
                        d.innerHTML = item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                        d.onmouseover = function () { this.style.background = '#f0f9ff'; };
                        d.onmouseout  = function () { this.style.background = ''; };
                        d.onmousedown = function (e) {
                            e.preventDefault();
                            document.getElementById('reg_city').value = item.city;
                            var s = document.getElementById('reg_state');
                            for (var i = 0; i < s.options.length; i++) { if (s.options[i].value === item.state) { s.selectedIndex = i; break; } }
                            dd.style.display = 'none';
                        };
                        dd.appendChild(d);
                    });
                    dd.style.display = 'block';
                }).catch(function () { dd.style.display = 'none'; });
            }, 300);
        }
        document.addEventListener('click', function () {
            var a = document.getElementById('reg_pc_dd'), b = document.getElementById('reg_city_dd');
            if (a) a.style.display = 'none';
            if (b) b.style.display = 'none';
        });
        </script>

        <button type="submit" class="btn-submit" data-ai-nav="register-submit-btn">{{ __('auth.submit_registration_button') }}</button>
    </form>

    <div class="form-footer">{{ __('auth.footer') }}</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jsQR/1.4.0/jsQR.js"></script>
<script>
const REG_I18N = {
    noMatches: @json(__('auth.no_matches_found_js')),
    activeStatus: @json(__('auth.active_status_badge_js')),
    viaQrCode: @json(__('auth.via_qr_code_js')),
    readingQr: @json(__('auth.reading_qr_js')),
    qrNoLink: @json(__('auth.qr_no_link_js')),
    qrUnreadable: @json(__('auth.qr_unreadable_js')),
};
let debounceTimer = null;

function handleUplineChange(value) {
    const codeField = document.getElementById('affiliate-code-field');
    const adminBox  = document.getElementById('admin-assign-box');
    const result    = document.getElementById('lookup-result');
    const agentId   = document.getElementById('upline_agent_id');
    result.className = 'lookup-result'; result.innerHTML = ''; agentId.value = '';
    if (value === 'ADMIN_ASSIGN') { codeField.style.display = 'none'; adminBox.classList.add('show'); }
    else if (value === '') { codeField.style.display = 'none'; adminBox.classList.remove('show'); }
    else { codeField.style.display = 'block'; adminBox.classList.remove('show'); document.getElementById('affiliate_search').value = ''; }
}

function debounceLookup(value) {
    clearTimeout(debounceTimer);
    const dropdown = document.getElementById('affiliate_dropdown');
    const agentId = document.getElementById('upline_agent_id');
    agentId.value = '';
    if (value.trim().length < 2) { dropdown.style.display = 'none'; return; }
    debounceTimer = setTimeout(() => { searchAffiliates(value.trim()); }, 400);
}

function searchAffiliates(q) {
    const uplineType = document.getElementById('upline_type').value;
    const dropdown   = document.getElementById('affiliate_dropdown');
    fetch(`{{ route('affiliate.lookup') }}?q=${encodeURIComponent(q)}&type=${uplineType}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success && data.matches.length) {
            dropdown.innerHTML = '';
            data.matches.forEach(m => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:.6vh .8rem; font-size:clamp(.6rem,1.1vh,.7rem); cursor:pointer; border-bottom:1px solid #f3f4f6;';
                item.innerHTML = `<strong>${m.name}</strong> <span style="color:#9ca3af;">— ${m.affiliate_code}</span>`;
                item.onmousedown = (e) => { e.preventDefault(); selectAffiliate(m); };
                dropdown.appendChild(item);
            });
            dropdown.style.display = 'block';
        } else {
            dropdown.innerHTML = `<div style="padding:.6vh .8rem; font-size:clamp(.6rem,1.1vh,.7rem); color:#9ca3af;">${data.message || REG_I18N.noMatches}</div>`;
            dropdown.style.display = 'block';
        }
    })
    .catch(() => { dropdown.style.display = 'none'; });
}

function selectAffiliate(agent) {
    document.getElementById('upline_agent_id').value = agent.id;
    document.getElementById('affiliate_search').value = agent.name;
    document.getElementById('affiliate_dropdown').style.display = 'none';
    const result = document.getElementById('lookup-result');
    result.className = 'lookup-result found';
    result.innerHTML = `✅ <span class="lookup-name">${agent.name}</span> <span class="lookup-role">${agent.role_label}</span> <span class="lookup-status">${REG_I18N.activeStatus}</span> <small style="color:#546e7a;margin-left:.5rem;">Code: ${agent.affiliate_code}</small>`;
}

document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('affiliate_dropdown');
    if (dropdown && e.target.id !== 'affiliate_search') dropdown.style.display = 'none';
});

// -------------------------------------------------------
// QR CODE — Option A. Handles both a direct file upload (decoded
// client-side) and arriving via a scanned link (?ref=TOKEN in the URL,
// checked automatically on page load below).
// -------------------------------------------------------
function applyQrSponsor(agent) {
    document.getElementById('upline_type').value = agent.role;
    handleUplineChange(agent.role);
    document.getElementById('upline_agent_id').value = agent.id;
    const result = document.getElementById('lookup-result');
    result.className = 'lookup-result found';
    result.innerHTML = `✅ <span class="lookup-name">${agent.name}</span> <span class="lookup-role">${agent.role_label}</span> <span class="lookup-status">${REG_I18N.activeStatus}</span> <small style="color:#546e7a;margin-left:.5rem;">${REG_I18N.viaQrCode}</small>`;
}

function lookupByToken(token) {
    fetch(`{{ route('affiliate.lookup-by-token') }}?token=${encodeURIComponent(token)}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            applyQrSponsor(data.agent);
        } else {
            const result = document.getElementById('lookup-result');
            result.className = 'lookup-result error';
            result.innerHTML = `❌ ${data.message}`;
        }
    });
}

function extractTokenFromText(text) {
    // The QR encodes a full URL like https://site.com/register?ref=TOKEN
    try {
        const url = new URL(text);
        return url.searchParams.get('ref');
    } catch (e) {
        // Not a valid URL — maybe just the raw token was encoded
        return text;
    }
}

function handleQrUpload(input) {
    if (!input.files || !input.files[0]) return;
    const result = document.getElementById('lookup-result');
    result.className = 'lookup-result searching'; result.innerHTML = '🔍 ' + REG_I18N.readingQr;

    const reader = new FileReader();
    reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = img.width; canvas.height = img.height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code) {
                const token = extractTokenFromText(code.data);
                if (token) { lookupByToken(token); }
                else { result.className = 'lookup-result error'; result.innerHTML = '❌ ' + REG_I18N.qrNoLink; }
            } else {
                result.className = 'lookup-result error'; result.innerHTML = '❌ ' + REG_I18N.qrUnreadable;
            }
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
}

document.addEventListener('DOMContentLoaded', () => {
    const uplineType = document.getElementById('upline_type').value;
    if (uplineType) handleUplineChange(uplineType);

    // Auto-fill if arriving via a scanned QR link, e.g. /register?ref=Xk9pQ2wZ
    const params = new URLSearchParams(window.location.search);
    const ref = params.get('ref');
    if (ref) { lookupByToken(ref); }
});
</script>

@include('partials.ai-assistant-widget', ['guestMode' => true, 'guestPageLabel' => 'Agent Registration Page'])
@include('partials.ai-guidance-overlay')
</body>
</html>
