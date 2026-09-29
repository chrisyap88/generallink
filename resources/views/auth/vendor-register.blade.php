<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.vreg_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }
        .left-panel { flex: 0 0 22%; height: 100vh; background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2vh 1.5vw; gap: 1.5vh; }
        .logo-img { width: 85%; max-width: 170px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,131,143,0.2); }
        .slogan { font-style: italic; font-size: clamp(.62rem, 1vw, .82rem); font-weight: 500; color: #0D5A8E; text-align: center; }
        .right-panel { flex: 1; height: 100vh; display: flex; align-items: center; justify-content: center; background: #fff; padding: .8vh 3vw; overflow: hidden; }
        /* Real fit guarantee (Chris: "need in one screen"): #formScale is
           auto-shrunk by JS at the bottom of this file if its natural
           content height would exceed the actual visible area, which can
           be smaller than 100vh once the browser's own address bar / tabs
           / OS taskbar are accounted for. Now a secondary, more important
           safety net: the form itself is split into 2 Prev/Next STEPS
           (see regStep1/regStep2 below) so this scale-down is rarely
           needed at all — per Chris's house rule of no scrolling and
           Prev/Next-only navigation. */
        .form-box { width: 100%; max-width: 720px; max-height: 100vh; overflow: hidden; }
        #formScale { transform-origin: top center; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: clamp(1.05rem, 1.8vw, 1.3rem); font-weight: 700; color: #0D5A8E; margin-bottom: .15vh; }
        .subtitle { font-size: clamp(.62rem, .85vw, .72rem); color: #38A169; font-weight: 500; margin-bottom: .5vh; }
        .stepIndicator { font-size: clamp(.62rem, .8vw, .7rem); font-weight: 700; color: #1B9AE4; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .5vh; }
        .alert-box { border-radius: 8px; padding: .35vh .8rem; font-size: clamp(.64rem, .84vw, .74rem); margin-bottom: .5vh; border-left: 3px solid; }
        .alert-error { background:#fde8e8; border-color:#e53935; color:#b71c1c; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 12px; }
        .grid3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0 10px; }
        .field { margin-bottom: .35vh; position: relative; }
        label { display: block; font-size: clamp(.62rem, .78vw, .68rem); font-weight: 600; color: #2D3748; margin-bottom: .15vh; }
        label .req { color: #e53935; font-weight: 400; }
        label .opt { font-weight: 400; color: #9ca3af; }
        input, select, textarea { width: 100%; padding: .34vh .8rem; border: 1.5px solid #b2ebf2; border-radius: 9px; font-size: clamp(.68rem, .88vw, .76rem); font-family: 'Outfit', sans-serif; color: #2D3748; background: #f7fdff; outline: none; }
        textarea { resize: none; line-height: 1.25; }
        input:focus, select:focus, textarea:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }
        .btn-signin { width: 100%; padding: .5vh; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); color: #fff; border: none; border-radius: 10px; font-size: clamp(.74rem, 1vw, .84rem); font-family: 'Outfit', sans-serif; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; cursor: pointer; margin: 0; }
        .btn-outline { width: 100%; padding: .5vh; background: #fff; color: #1B9AE4; border: 1.5px solid #1B9AE4; border-radius: 10px; font-size: clamp(.74rem, 1vw, .84rem); font-family: 'Outfit', sans-serif; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; cursor: pointer; margin: 0; }
        .stepNavRow { display: grid; gap: 8px; margin: .45vh 0; }
        .stepNavRow.two { grid-template-columns: 1fr 1fr; }
        .login-row { text-align: center; font-size: clamp(.64rem, .85vw, .72rem); color: #718096; }
        .login-row a { color: #1B9AE4; font-weight: 600; text-decoration: none; }

        /* Help tooltip — click or hover a "?" icon next to a field label.
           Shown as a short tick-list of concrete points, not one dense
           paragraph — faster to scan. */
        .tipIcon { display:inline-flex; align-items:center; justify-content:center; width:12px; height:12px; border-radius:50%; background:#1B9AE4; color:#fff; font-size:8px; font-weight:700; cursor:pointer; margin-left:4px; vertical-align:middle; user-select:none; }
        .tipBox { display:none; position:absolute; z-index:60; top:100%; left:0; background:#0D5A8E; color:#fff; font-size:9.5px; font-weight:400; line-height:1.4; padding:8px 10px; border-radius:8px; width:250px; box-shadow:0 6px 18px rgba(0,0,0,.25); margin-top:3px; }
        .tipTitle { font-weight:700; margin-bottom:4px; font-size:9.5px; }
        .tipItem { display:flex; gap:5px; margin-bottom:3px; align-items:flex-start; }
        .tipItem:last-child { margin-bottom:0; }
        .tipItem .tick { flex-shrink:0; color:#7DD3FC; font-weight:700; }

        /* Postcode / City typeahead dropdowns (address section) */
        .addrDD { display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1.5px solid #b2ebf2; border-radius:8px; box-shadow:0 6px 18px rgba(0,0,0,.15); z-index:65; max-height:110px; overflow-y:auto; }
        .addrDD > div { padding:4px 8px; cursor:pointer; font-size:9.5px; border-bottom:1px solid #f3f4f6; }
        .addrDD > div:hover { background:#e0f7fa; }

        /* Multi-select picker (Industry / Nature of Business) — the box in
           the form only ever shows compact removable chips; the actual
           checkbox picker floats as a body-level fixed panel so it never
           pushes the page taller or covers other fields (Chris's spec).
           Panel header now carries an explicit ✕ close button — per
           Chris: "the selection window no close function" — clicking
           outside, pressing Escape, and the Apply button all still work
           too, but the ✕ makes "how do I go back to the main form"
           unambiguous. */
        .msBox { min-height: 2.6vh; display:flex; align-items:center; flex-wrap:wrap; gap:3px; cursor:pointer; }
        .msChips { display:flex; flex-wrap:wrap; gap:3px; max-height:42px; overflow-y:auto; width:100%; }
        .msPlaceholder { color:#9ca3af; font-size: inherit; }
        .msChip { display:inline-flex; align-items:center; gap:3px; background:#e0f7fa; border:1px solid #b2ebf2; color:#0D5A8E; font-size:8.5px; font-weight:600; padding:1px 6px; border-radius:10px; white-space:nowrap; }
        .msChip .msX { cursor:pointer; font-weight:700; color:#0D5A8E; }
        .msChip .msX:hover { color:#e53935; }
        .msPanel { display:none; flex-direction:column; position:fixed; z-index:80; background:#fff; border:1.5px solid #1B9AE4; border-radius:9px; width:300px; max-height:270px; box-shadow:0 10px 30px rgba(0,0,0,.22); padding:6px; }
        .msPanelHead { display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; }
        .msPanelTitle { font-size:9.5px; font-weight:700; color:#0D5A8E; }
        .msPanelClose { cursor:pointer; font-size:12px; font-weight:700; color:#9ca3af; padding:0 4px; line-height:1; }
        .msPanelClose:hover { color:#e53935; }
        .msPanel .msSearch { font-size:9.5px; padding:3px 6px; margin-bottom:3px; }
        .msActionsRow { display:flex; justify-content:space-between; font-size:8.5px; color:#1B9AE4; font-weight:700; padding:2px 3px; }
        .msActionsRow span { cursor:pointer; }
        .msActionsRow span:hover { text-decoration:underline; }
        .msList { overflow-y:auto; flex:1; border-top:1px solid #e0f2fe; border-bottom:1px solid #e0f2fe; margin:3px 0; }
        .msOpt { display:flex; align-items:center; gap:5px; padding:3px 4px; font-size:9.5px; color:#2D3748; cursor:pointer; }
        .msOpt:hover { background:#e0f7fa; }
        .msOpt input[type=checkbox] { width:auto; margin:0; }
        .msApplyBtn { width:100%; padding:4px; background:#1B9AE4; color:#fff; border:none; border-radius:7px; font-size:9.5px; font-weight:700; cursor:pointer; }
        .msApplyBtn:hover { background:#0D5A8E; }
        .msEmpty { font-size:9px; color:#9ca3af; padding:6px 4px; text-align:center; }

        /* Dynamic document checklist — REBUILT 9 Aug 2026 per Chris: "no
           scroll for choosing ssm document...show the document selected
           and no truncation...i cant see the submit button." Entity Type
           + Required Documents now live on their own Prev/Next STEP (see
           regStep2), and within that the document cards themselves page
           3-at-a-time (docBox never scrolls — every entity type, even the
           9-10 document ones, stays reachable via Prev/Next only), with a
           running "X of Y attached" summary always visible above the
           cards so progress is never hidden off-screen. */
        .docBox { border:1.5px solid #b2ebf2; border-radius:9px; background:#f7fdff; padding:6px 8px; }
        .docEmpty { font-size:9.5px; color:#9ca3af; padding:3px 0; }
        .docSummary { font-size:9px; font-weight:700; color:#0D5A8E; padding:1px 2px 6px; }
        .docPageNav { display:flex; align-items:center; justify-content:space-between; margin-top:5px; padding-top:5px; border-top:1px solid #e0f2fe; }
        .docPageBtn { background:#1B9AE4; color:#fff; border:none; border-radius:6px; padding:4px 12px; font-size:9px; font-weight:700; cursor:pointer; }
        .docPageBtn:disabled { background:#cbd5e1; cursor:default; }
        .docPageInfo { font-size:9px; color:#6b7280; font-weight:600; }

        /* Document checklist cards — one document per card (name +
           description + file). Tier=MANDATORY gets the same red "*" the
           rest of this form already uses for every other required field;
           no other badge/pill. The checkbox reflects "this document is
           attached" and auto-ticks itself the moment a file is chosen. */
        .docCard { border:1px solid #e0f2fe; border-radius:7px; padding:6px 8px; margin-bottom:5px; background:#fff; }
        .docCard:last-child { margin-bottom:0; }
        .docCardHead { display:flex; align-items:flex-start; gap:5px; cursor:pointer; }
        .docCardHead input[type=checkbox] { width:auto; margin:2px 0 0; flex-shrink:0; }
        .docCardName { font-size:9.5px; font-weight:600; color:#2D3748; line-height:1.3; flex:1; }
        .docCardName .req { color:#e53935; font-weight:400; }
        .docCardDesc { font-size:8.5px; color:#718096; line-height:1.35; margin:2px 0 4px 19px; }
        .docCardFileRow { display:flex; align-items:center; gap:6px; margin-left:19px; }
        .docCardFileRow input[type=file] { flex:1; font-size:8px; padding:0; border:none; background:none; }
        .docCardStatus { font-size:8px; color:#9ca3af; white-space:nowrap; }
        .docCardStatus.uploaded { color:#16a34a; font-weight:600; }

        /* Contact Persons — 3 compact rows in one bordered box (Contact 1 required, 2 & 3 optional) */
        .contactBox { border:1.5px solid #b2ebf2; border-radius:9px; background:#f7fdff; padding:3px 8px; }
        .contactRow { display:flex; align-items:center; gap:5px; padding:2px 0; border-bottom:1px solid #e0f2fe; }
        .contactRow:last-child { border-bottom:none; }
        .contactNum { font-size:9px; font-weight:700; color:#1565C0; width:12px; flex-shrink:0; text-align:center; }
        .contactRow input { padding:2px 6px; font-size:9px; border:1px solid #d8ecf0; border-radius:6px; background:#fff; min-width:0; }
        .contactRow input.nameF { flex:1.3; }
        .contactRow input.desigF { flex:1; }
        .contactRow input.phoneF { flex:0.85; }
        .contactRow input.emailF { flex:1.3; }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink Logo" />
    <div class="slogan">{{ __('auth.vreg_slogan') }}</div>
    @include('partials.intro-video-widget')
</div>

<div class="right-panel">
    <div class="form-box">
    <div id="formScale">
        <h1>{{ __('auth.vreg_heading') }}</h1>
        <p class="subtitle">{{ __('auth.vreg_subtitle') }}</p>
        <div class="stepIndicator" id="stepIndicator">{{ __('auth.vreg_step1_indicator') }}</div>

        @if ($errors->any())
        <div class="alert-box alert-error">
            @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('vendor.register.post') }}" enctype="multipart/form-data" id="vendorRegForm">
            @csrf

            {{-- STEP 1 — Company identity, address, industry, contacts --}}
            <div id="regStep1">
                <div class="grid2">
                    <div class="field">
                        <label for="vendor_name">{{ __('auth.vreg_company_name_label') }}</label>
                        <input type="text" id="vendor_name" name="vendor_name" value="{{ old('vendor_name') }}" required autofocus />
                    </div>
                    <div class="field">
                        <label for="second_name">{{ __('auth.second_name_label') }}</label>
                        <input type="text" id="second_name" name="second_name" value="{{ old('second_name') }}" placeholder="{{ __('auth.vreg_optional_placeholder') }}" />
                    </div>
                </div>

                <div class="field">
                    <label for="vendor_address">{{ __('auth.vreg_office_address_label') }} <span class="req">*</span></label>
                    <input type="text" id="vendor_address" name="vendor_address" placeholder="{{ __('auth.vreg_office_address_placeholder') }}" value="{{ old('vendor_address') }}" required />
                </div>
                <div class="grid3">
                    <div class="field">
                        <label for="vendor_postcode">{{ __('masterfile.postcode') }} <span class="req">*</span></label>
                        <input type="text" id="vendor_postcode" name="vendor_postcode" maxlength="5" autocomplete="off" oninput="vpPC(this)" value="{{ old('vendor_postcode') }}" required />
                        <div id="vp_pc_dd" class="addrDD"></div>
                    </div>
                    <div class="field">
                        <label for="vendor_city">{{ __('masterfile.city') }} <span class="req">*</span></label>
                        <input type="text" id="vendor_city" name="vendor_city" autocomplete="off" oninput="vpCity(this)" value="{{ old('vendor_city') }}" required />
                        <div id="vp_city_dd" class="addrDD"></div>
                    </div>
                    <div class="field">
                        <label for="vendor_state">{{ __('masterfile.state') }} <span class="req">*</span></label>
                        <select id="vendor_state" name="vendor_state" required>
                            <option value="">{{ __('auth.select_default_option') }}</option>
                            @foreach($states as $state)
                            <option value="{{ $state }}" {{ old('vendor_state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('auth.vreg_industry_label') }} <span class="req">*</span>
                        <span class="tipIcon" onmouseenter="tipShow('tip_industry')" onmouseleave="tipHide('tip_industry')" onclick="tipToggle('tip_industry')">?</span>
                        <span class="tipBox" id="tip_industry">
                            <div class="tipTitle">{{ __('auth.vreg_tip_industry_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_select_multiple') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_industry_diff') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_other_option') }}</div>
                        </span>
                    </label>
                    <div class="msBox" id="industryMsBox" onclick="msOpen('industry')"><div class="msChips" id="industryChips"></div></div>
                    <div id="industryHidden"></div>
                    <div class="field" id="industryOtherWrap" style="display:none; margin-top:3px; margin-bottom:0;">
                        <input type="text" id="industry_other_text" name="industry_other_text" maxlength="200" placeholder="{{ __('auth.vreg_industry_other_placeholder') }}" value="{{ old('industry_other_text') }}" />
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('auth.vreg_nature_label') }} <span class="req">*</span>
                        <span class="tipIcon" onmouseenter="tipShow('tip_nature')" onmouseleave="tipHide('tip_nature')" onclick="tipToggle('tip_nature')">?</span>
                        <span class="tipBox" id="tip_nature">
                            <div class="tipTitle">{{ __('auth.vreg_tip_nature_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_nature_multiple') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_nature_diff') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_other_option') }}</div>
                        </span>
                    </label>
                    <div class="msBox" id="activityMsBox" onclick="msOpen('activity')"><div class="msChips" id="activityChips"></div></div>
                    <div id="activityHidden"></div>
                    <div class="field" id="activityOtherWrap" style="display:none; margin-top:3px; margin-bottom:0;">
                        <input type="text" id="nature_of_business_other_text" name="nature_of_business_other_text" maxlength="200" placeholder="{{ __('auth.vreg_nature_other_placeholder') }}" value="{{ old('nature_of_business_other_text') }}" />
                    </div>
                </div>

                <div class="field">
                    <label for="fb_page_url">{{ __('vendor.website_fb_label') }} <span class="opt">({{ __('auth.vreg_optional_suffix') }})</span></label>
                    <input type="url" id="fb_page_url" name="fb_page_url" placeholder="{{ __('auth.vreg_website_placeholder') }}" value="{{ old('fb_page_url') }}" />
                </div>
                <div class="field">
                    <label>{{ __('vendor.contact_persons_heading') }}
                        <span class="tipIcon" onmouseenter="tipShow('tip_contacts')" onmouseleave="tipHide('tip_contacts')" onclick="tipToggle('tip_contacts')">?</span>
                        <span class="tipBox" id="tip_contacts">
                            <div class="tipTitle">{{ __('auth.vreg_tip_contacts_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_contact1_note') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_contact23_note') }}</div>
                        </span>
                    </label>
                    <div style="font-size:8.5px; color:#0D5A8E; font-weight:600; margin-bottom:2px;">{{ __('auth.vreg_contact1_login_note') }}</div>
                    <div class="contactBox">
                        <div class="contactRow">
                            <span class="contactNum">1*</span>
                            <input class="nameF" type="text" name="contacts[1][name]" placeholder="{{ __('auth.vreg_full_name_req_placeholder') }}" value="{{ old('contacts.1.name') }}" required>
                            <input class="desigF" type="text" name="contacts[1][designation]" placeholder="{{ __('auth.vreg_designation_req_placeholder') }}" value="{{ old('contacts.1.designation') }}" required>
                            <input class="phoneF" type="text" name="contacts[1][phone]" placeholder="{{ __('auth.vreg_hp_req_placeholder') }}" value="{{ old('contacts.1.phone') }}" required>
                            <input class="emailF" type="email" name="contacts[1][email]" placeholder="{{ __('auth.vreg_email_login_req_placeholder') }}" value="{{ old('contacts.1.email') }}" required>
                        </div>
                        <div class="contactRow">
                            <span class="contactNum">2</span>
                            <input class="nameF" type="text" name="contacts[2][name]" placeholder="{{ __('masterfile.full_name') }}" value="{{ old('contacts.2.name') }}">
                            <input class="desigF" type="text" name="contacts[2][designation]" placeholder="{{ __('auth.vreg_designation_placeholder') }}" value="{{ old('contacts.2.designation') }}">
                            <input class="phoneF" type="text" name="contacts[2][phone]" placeholder="{{ __('auth.vreg_hp_placeholder') }}" value="{{ old('contacts.2.phone') }}">
                            <input class="emailF" type="email" name="contacts[2][email]" placeholder="{{ __('masterfile.email') }}" value="{{ old('contacts.2.email') }}">
                        </div>
                        <div class="contactRow">
                            <span class="contactNum">3</span>
                            <input class="nameF" type="text" name="contacts[3][name]" placeholder="{{ __('masterfile.full_name') }}" value="{{ old('contacts.3.name') }}">
                            <input class="desigF" type="text" name="contacts[3][designation]" placeholder="{{ __('auth.vreg_designation_placeholder') }}" value="{{ old('contacts.3.designation') }}">
                            <input class="phoneF" type="text" name="contacts[3][phone]" placeholder="{{ __('auth.vreg_hp_placeholder') }}" value="{{ old('contacts.3.phone') }}">
                            <input class="emailF" type="email" name="contacts[3][email]" placeholder="{{ __('masterfile.email') }}" value="{{ old('contacts.3.email') }}">
                        </div>
                    </div>

                    {{-- NEW 13 Aug 2026 — per Chris: "which vendor email
                         address? contact 1 or 2 or 3?... add another
                         contact as authorized Director... make sure dont
                         make the screen truncated or scroll." Contact 1
                         already IS the authorised signatory by default
                         (same person, same email) — this row stays
                         collapsed to a single checkbox unless someone
                         ELSE needs to sign GLADE's Agreement, so Step 1
                         doesn't grow taller for the common case. --}}
                    <div style="display:flex; align-items:flex-start; gap:6px; margin-top:4px;">
                        <input type="checkbox" id="authSigToggle" name="authorized_signatory_different" value="1" onchange="document.getElementById('authSigBox').style.display = this.checked ? 'block' : 'none';" {{ old('authorized_signatory_different') ? 'checked' : '' }} style="width:auto; margin-top:2px; flex-shrink:0;">
                        <label for="authSigToggle" style="font-weight:400; margin-bottom:0; font-size:clamp(.6rem,.78vw,.66rem); line-height:1.3; color:#4A5568;">{{ __('auth.vreg_diff_signatory_label') }}</label>
                    </div>
                    <div class="contactBox" id="authSigBox" style="display:{{ old('authorized_signatory_different') ? 'block' : 'none' }}; margin-top:3px;">
                        <div class="contactRow">
                            <span class="contactNum">✎</span>
                            <input class="nameF" type="text" name="authorized_signatory_name" placeholder="{{ __('auth.vreg_full_name_req_placeholder') }}" value="{{ old('authorized_signatory_name') }}">
                            <input class="desigF" type="text" name="authorized_signatory_designation" placeholder="{{ __('auth.vreg_designation_director_placeholder') }}" value="{{ old('authorized_signatory_designation') }}">
                            <input class="phoneF" type="text" name="authorized_signatory_phone" placeholder="{{ __('auth.vreg_hp_req_placeholder') }}" value="{{ old('authorized_signatory_phone') }}">
                            <input class="emailF" type="email" name="authorized_signatory_email" placeholder="{{ __('auth.vreg_email_req_placeholder') }}" value="{{ old('authorized_signatory_email') }}">
                        </div>
                    </div>
                </div>

                <div class="stepNavRow">
                    <button type="button" class="btn-signin" onclick="goToStep2()">{{ __('auth.vreg_next_ssm_button') }}</button>
                </div>
                <div class="login-row">{{ __('auth.vreg_already_registered_prompt') }} <a href="{{ route('vendor.login') }}">{{ __('auth.sign_in_here_link') }}</a></div>
            </div>

            {{-- STEP 2 — Entity type, required documents, declaration, submit --}}
            <div id="regStep2" style="display:none;">
                <div class="field">
                    <label for="entity_type">{{ __('auth.vreg_entity_type_label') }}
                        <span class="tipIcon" onmouseenter="tipShow('tip_entity')" onmouseleave="tipHide('tip_entity')" onclick="tipToggle('tip_entity')">?</span>
                        <span class="tipBox" id="tip_entity">
                            <div class="tipTitle">{{ __('auth.vreg_tip_entity_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_entity_decides') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_entity_first') }}</div>
                        </span>
                    </label>
                    <select id="entity_type" name="entity_type" required onchange="renderDocChecklist()">
                        <option value="">{{ __('auth.select_default_option') }}</option>
                        @foreach($entityTypes as $key => $label)
                        <option value="{{ $key }}" {{ old('entity_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>{{ __('auth.vreg_ssm_document_label') }}
                        <span class="tipIcon" onmouseenter="tipShow('tip_docs')" onmouseleave="tipHide('tip_docs')" onclick="tipToggle('tip_docs')">?</span>
                        <span class="tipBox" id="tip_docs">
                            <div class="tipTitle">{{ __('auth.vreg_tip_docs_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_docs_older') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_docs_filetypes') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_docs_pagenav') }}</div>
                        </span>
                    </label>
                    <div class="docBox" id="docChecklistBox">
                        <div class="docEmpty">{{ __('auth.vreg_doc_empty_note') }}</div>
                    </div>
                </div>

                <div class="stepNavRow two">
                    <button type="button" class="btn-outline" onclick="goStep(1)">{{ __('network.prev') }}</button>
                    <button type="button" class="btn-signin" onclick="goToStep3()">{{ __('auth.vreg_next_media_button') }}</button>
                </div>
                <div class="login-row">{{ __('auth.vreg_already_registered_prompt') }} <a href="{{ route('vendor.login') }}">{{ __('auth.sign_in_here_link') }}</a></div>
            </div>

            {{-- STEP 3 — NEW 12 Aug 2026 per Chris: "for new vendor
                 registration can he upload his video or slide show or
                 you tube like or ppt, product flyer." All optional —
                 kept as its own step rather than crammed into the
                 already-full Step 2, since none of it blocks
                 registration. Declaration + the real Submit button moved
                 here too, as the natural final confirmation step. --}}
            <div id="regStep3" style="display:none;">
                <div class="field">
                    <label>{{ __('auth.vreg_media_upload_label') }} <span class="opt">({{ __('auth.vreg_all_optional_suffix') }})</span>
                        <span class="tipIcon" onmouseenter="tipShow('tip_marketing')" onmouseleave="tipHide('tip_marketing')" onclick="tipToggle('tip_marketing')">?</span>
                        <span class="tipBox" id="tip_marketing">
                            <div class="tipTitle">{{ __('auth.vreg_tip_marketing_title') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_marketing_formats') }}</div>
                            <div class="tipItem"><span class="tick">✓</span> {{ __('auth.vreg_tip_marketing_optional') }}</div>
                        </span>
                    </label>
                </div>
                <div class="field">
                    <label for="marketing_video">{{ __('auth.vreg_video_label') }} <span class="opt">({{ __('auth.vreg_video_hint') }})</span></label>
                    <input type="file" id="marketing_video" name="marketing_video" accept=".mp4,.mov,.avi,.webm" />
                </div>
                <div class="field">
                    <label for="marketing_slideshow">{{ __('auth.vreg_slideshow_label') }} <span class="opt">({{ __('auth.vreg_slideshow_hint') }})</span></label>
                    <input type="file" id="marketing_slideshow" name="marketing_slideshow" accept=".pdf,.ppt,.pptx" />
                </div>
                <div class="field">
                    <label for="marketing_flyer">{{ __('auth.vreg_flyer_label') }} <span class="opt">({{ __('auth.vreg_flyer_hint') }})</span></label>
                    <input type="file" id="marketing_flyer" name="marketing_flyer" accept=".jpg,.jpeg,.png,.pdf" />
                </div>
                <div class="field">
                    <label for="marketing_link">{{ __('auth.vreg_link_label') }} <span class="opt">({{ __('auth.vreg_link_hint') }})</span></label>
                    <input type="url" id="marketing_link" name="marketing_link" placeholder="{{ __('auth.vreg_link_placeholder') }}" value="{{ old('marketing_link') }}" />
                </div>

                <div class="field" style="display:flex; align-items:flex-start; gap:6px; margin-top:.2vh;">
                    <input type="checkbox" id="declaration" name="declaration" value="1" {{ old('declaration') ? 'checked' : '' }} required style="width:auto; margin-top:2px; flex-shrink:0;" />
                    <label for="declaration" style="font-weight:400; margin-bottom:0; font-size:clamp(.6rem,.78vw,.66rem); line-height:1.3; color:#4A5568;">{{ $declarationText }}</label>
                </div>

                <div class="stepNavRow two">
                    <button type="button" class="btn-outline" onclick="goStep(2)">{{ __('network.prev') }}</button>
                    <button type="submit" class="btn-signin">{{ __('auth.vreg_submit_approval_button') }}</button>
                </div>
                <div class="login-row">{{ __('auth.vreg_already_registered_prompt') }} <a href="{{ route('vendor.login') }}">{{ __('auth.sign_in_here_link') }}</a></div>
            </div>
        </form>
    </div>
    </div>
</div>

{{-- Placed outside #formScale deliberately — position:fixed panels lose
     correct viewport coordinates if a transformed (scaled) ancestor
     becomes their containing block, per CSS spec. Living at the body
     level here keeps getBoundingClientRect()-based positioning correct
     whether or not the form is currently auto-scaled down. --}}
<div id="msPanel_industry" class="msPanel">
    <div class="msPanelHead">
        <span class="msPanelTitle">{{ __('auth.vreg_select_industry_title') }}</span>
        <span class="msPanelClose" onclick="msClose('industry')" title="{{ __('auth.vreg_close_tooltip') }}">✕</span>
    </div>
    <input type="text" class="msSearch" placeholder="{{ __('auth.vreg_search_industry_placeholder') }}" oninput="msFilter('industry', this.value)">
    <div class="msActionsRow">
        <span onclick="msSelectAll('industry')">{{ __('auth.vreg_select_all') }}</span>
        <span onclick="msClearAll('industry')">{{ __('auth.vreg_clear_all') }}</span>
    </div>
    <div class="msList" id="msList_industry"></div>
    <button type="button" class="msApplyBtn" onclick="msClose('industry')">{{ __('auth.vreg_done_back_button') }}</button>
</div>
<div id="msPanel_activity" class="msPanel">
    <div class="msPanelHead">
        <span class="msPanelTitle">{{ __('auth.vreg_select_nature_title') }}</span>
        <span class="msPanelClose" onclick="msClose('activity')" title="{{ __('auth.vreg_close_tooltip') }}">✕</span>
    </div>
    <input type="text" class="msSearch" placeholder="{{ __('auth.vreg_search_nature_placeholder') }}" oninput="msFilter('activity', this.value)">
    <div class="msActionsRow">
        <span onclick="msSelectAll('activity')">{{ __('auth.vreg_select_all') }}</span>
        <span onclick="msClearAll('activity')">{{ __('auth.vreg_clear_all') }}</span>
    </div>
    <div class="msList" id="msList_activity"></div>
    <button type="button" class="msApplyBtn" onclick="msClose('activity')">{{ __('auth.vreg_done_back_button') }}</button>
</div>

@include('partials.ai-assistant-widget', ['guestMode' => true, 'guestPageLabel' => 'Vendor Registration Page'])

<script>
    var VREG_I18N = {
        step1Indicator: @json(__('auth.vreg_step1_indicator')),
        step2Indicator: @json(__('auth.vreg_step2_indicator')),
        step3Indicator: @json(__('auth.vreg_step3_indicator')),
        alertSelectEntityType: @json(__('auth.vreg_alert_select_entity_type')),
        alertPleaseUploadPrefix: @json(__('auth.vreg_alert_please_upload_prefix')),
        alertPleaseFillPrefix: @json(__('auth.vreg_alert_please_fill_prefix')),
        alertSelectIndustry: @json(__('auth.vreg_alert_select_industry')),
        alertSpecifyIndustryOther: @json(__('auth.vreg_alert_specify_industry_other')),
        alertSelectNature: @json(__('auth.vreg_alert_select_nature')),
        alertSpecifyNatureOther: @json(__('auth.vreg_alert_specify_nature_other')),
        industryMsPlaceholder: @json(__('auth.vreg_industry_ms_placeholder')),
        natureMsPlaceholder: @json(__('auth.vreg_nature_ms_placeholder')),
        noMatch: @json(__('auth.vreg_no_match_js')),
        docEmptyNote: @json(__('auth.vreg_doc_empty_note')),
        docRequiredStatus: @json(__('auth.vreg_doc_required_status')),
        docNotAttachedStatus: @json(__('auth.vreg_doc_not_attached_status')),
        docPageOf: @json(__('auth.vreg_doc_page_of')),
        docSummaryTemplate: @json(__('auth.vreg_doc_summary_template')),
        docPrevBtn: @json(__('auth.vreg_doc_prev_btn')),
        docNextBtn: @json(__('auth.vreg_doc_next_btn')),
    };

    // Watch Intro Video button (left panel, below logo) is now the
    // shared partials.intro-video-widget component (openIntroVideo() /
    // closeIntroVideo() are defined there) — standardized across every
    // guest login/register page.

    // ---- Step 1 / Step 2 / Step 3 navigation ----
    // Registration is split into 3 Prev/Next steps (Chris's house rule:
    // no scrolling, Prev/Next only, no jump screens) — Step 1 is company
    // identity/address/industry/contacts, Step 2 is entity type + the SSM
    // document checklist, Step 3 (NEW 12 Aug 2026) is optional Marketing
    // Materials (video/slideshow/flyer/link) + declaration + the real
    // Submit button.
    function goStep(n){
        document.getElementById('regStep1').style.display = (n === 1) ? 'block' : 'none';
        document.getElementById('regStep2').style.display = (n === 2) ? 'block' : 'none';
        document.getElementById('regStep3').style.display = (n === 3) ? 'block' : 'none';
        document.getElementById('stepIndicator').textContent = (n === 1)
            ? VREG_I18N.step1Indicator
            : (n === 2)
                ? VREG_I18N.step2Indicator
                : VREG_I18N.step3Indicator;
        Object.keys(MS).forEach(function(f){ document.getElementById(MS[f].panelId).style.display = 'none'; });
        fitFormToScreen();
    }
    // NEW 12 Aug 2026 — same guard pattern as goToStep2() below: re-check
    // entity type + every mandatory document before letting the vendor
    // move past Step 2, so a missing mandatory document is caught right
    // where it happened instead of only at final Submit.
    function goToStep3(){
        var entityType = document.getElementById('entity_type').value;
        if (!entityType){
            alert(VREG_I18N.alertSelectEntityType);
            document.getElementById('entity_type').focus();
            return;
        }
        var list = DOC_CHECKLISTS[entityType] || [];
        for (var i = 0; i < list.length; i++){
            if (list[i].tier === 'MANDATORY'){
                var f = document.getElementById('docFile_' + i);
                if (!f || !f.files || !f.files.length){
                    docGoToPage(Math.floor(i / DOC_PER_PAGE));
                    alert(VREG_I18N.alertPleaseUploadPrefix + list[i].label);
                    return;
                }
            }
        }
        goStep(3);
    }
    // FIXED 12 Aug 2026 — per Chris: "next entity document next when i
    // click nothing happen." Root cause: this used form.reportValidity(),
    // which shows a native browser bubble anchored to the invalid field —
    // but the whole form lives inside #formScale, which fitFormToScreen()
    // applies a CSS transform:scale() to. A transformed ancestor becomes
    // the containing block for that anchored bubble, so on most browsers
    // it renders in the wrong place or not at all — the click looked like
    // it silently did nothing, when really a required field was failing
    // validation with an invisible bubble. Fixed by checking each
    // required Step 1 field explicitly with checkValidity() (a silent
    // boolean check, no bubble involved) and showing our own alert()
    // instead — same pattern already used for Industry/Nature of
    // Business below, and immune to the transform issue.
    function goToStep2(){
        var requiredFields = document.querySelectorAll('#regStep1 [required]');
        for (var i = 0; i < requiredFields.length; i++){
            var el = requiredFields[i];
            if (!el.checkValidity()){
                var fieldWrap = el.closest('.field') || el.closest('.contactRow');
                var labelEl = fieldWrap ? fieldWrap.querySelector('label') : null;
                var fieldName = labelEl ? labelEl.textContent.replace('*', '').trim() : (el.placeholder || el.name || 'a required field');
                alert(VREG_I18N.alertPleaseFillPrefix + fieldName);
                el.focus();
                return;
            }
        }
        if (!MS.industry.selected.size){
            alert(VREG_I18N.alertSelectIndustry);
            return;
        }
        if (MS.industry.selected.has('OTHER') && !document.getElementById('industry_other_text').value.trim()){
            alert(VREG_I18N.alertSpecifyIndustryOther);
            document.getElementById('industry_other_text').focus();
            return;
        }
        if (!MS.activity.selected.size){
            alert(VREG_I18N.alertSelectNature);
            return;
        }
        if (MS.activity.selected.has('OTHER') && !document.getElementById('nature_of_business_other_text').value.trim()){
            alert(VREG_I18N.alertSpecifyNatureOther);
            document.getElementById('nature_of_business_other_text').focus();
            return;
        }
        goStep(2);
    }

    // Help tooltips — click OR hover the "?" icon next to a field label.
    function tipShow(id){ document.getElementById(id).style.display = 'block'; }
    function tipHide(id){ document.getElementById(id).style.display = 'none'; }
    function tipToggle(id){
        var box = document.getElementById(id);
        box.style.display = (box.style.display === 'block') ? 'none' : 'block';
    }
    document.addEventListener('click', function(e){
        if (!e.target.classList.contains('tipIcon')) {
            document.querySelectorAll('.tipBox').forEach(function(b){ b.style.display = 'none'; });
        }
    });

    // ---- Office address: postcode / city typeahead, reads the shared
    // malaysia_postcodes table via the same public route already used on
    // the agent self-registration page. ----
    var _vpPCt, _vpCTt;
    function vpPC(inp){
        clearTimeout(_vpPCt);
        var v = inp.value.trim(), dd = document.getElementById('vp_pc_dd');
        if (v.length < 3){ dd.style.display = 'none'; return; }
        _vpPCt = setTimeout(function(){
            fetch('{{ route('register.postcode-lookup') }}?postcode=' + encodeURIComponent(v) + '&partial=1')
            .then(function(r){ return r.json(); }).then(function(data){
                if (!data || !data.length){ dd.style.display = 'none'; return; }
                dd.innerHTML = '';
                data.forEach(function(item){
                    var d = document.createElement('div');
                    d.innerHTML = '<strong>' + item.postcode + '</strong> — ' + item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                    d.onmousedown = function(e){
                        e.preventDefault();
                        document.getElementById('vendor_postcode').value = item.postcode;
                        document.getElementById('vendor_city').value = item.city;
                        var s = document.getElementById('vendor_state');
                        for (var i = 0; i < s.options.length; i++){ if (s.options[i].value === item.state){ s.selectedIndex = i; break; } }
                        dd.style.display = 'none';
                    };
                    dd.appendChild(d);
                });
                dd.style.display = 'block';
            }).catch(function(){ dd.style.display = 'none'; });
        }, 300);
    }
    function vpCity(inp){
        clearTimeout(_vpCTt);
        var v = inp.value.trim(), dd = document.getElementById('vp_city_dd');
        if (v.length < 2){ dd.style.display = 'none'; return; }
        _vpCTt = setTimeout(function(){
            fetch('{{ route('register.postcode-lookup') }}?city=' + encodeURIComponent(v))
            .then(function(r){ return r.json(); }).then(function(data){
                if (!data || !data.length){ dd.style.display = 'none'; return; }
                dd.innerHTML = '';
                var seen = {};
                data.forEach(function(item){
                    if (seen[item.city]) return; seen[item.city] = true;
                    var d = document.createElement('div');
                    d.innerHTML = item.city + ' <span style="color:#6b7280;">(' + item.state + ')</span>';
                    d.onmousedown = function(e){
                        e.preventDefault();
                        document.getElementById('vendor_city').value = item.city;
                        var s = document.getElementById('vendor_state');
                        for (var i = 0; i < s.options.length; i++){ if (s.options[i].value === item.state){ s.selectedIndex = i; break; } }
                        dd.style.display = 'none';
                    };
                    dd.appendChild(d);
                });
                dd.style.display = 'block';
            }).catch(function(){ dd.style.display = 'none'; });
        }, 300);
    }
    document.addEventListener('click', function(){
        var a = document.getElementById('vp_pc_dd'), b = document.getElementById('vp_city_dd');
        if (a) a.style.display = 'none';
        if (b) b.style.display = 'none';
    });

    // ---- Industry & Nature of Business multi-select pickers ----
    // Both fields support picking more than one value from a fixed list,
    // show removable chips, offer Select All / Clear All / search-inside
    // -panel, and the picker itself floats as a body-level fixed panel so
    // it never pushes the page taller or hides the input or other fields.
    var INDUSTRY_OPTIONS = @json($industries);
    var ACTIVITY_OPTIONS = @json($businessActivities);
    var OLD_INDUSTRIES   = @json(old('industries', []));
    var OLD_ACTIVITIES   = @json(old('business_activities', []));

    var MS = {
        industry: {
            options: INDUSTRY_OPTIONS,
            selected: new Set(OLD_INDUSTRIES),
            hiddenName: 'industries[]',
            otherWrapId: 'industryOtherWrap',
            boxId: 'industryMsBox',
            chipsId: 'industryChips',
            panelId: 'msPanel_industry',
            listId: 'msList_industry',
            hiddenBoxId: 'industryHidden',
            placeholder: VREG_I18N.industryMsPlaceholder
        },
        activity: {
            options: ACTIVITY_OPTIONS,
            selected: new Set(OLD_ACTIVITIES),
            hiddenName: 'business_activities[]',
            otherWrapId: 'activityOtherWrap',
            boxId: 'activityMsBox',
            chipsId: 'activityChips',
            panelId: 'msPanel_activity',
            listId: 'msList_activity',
            hiddenBoxId: 'activityHidden',
            placeholder: VREG_I18N.natureMsPlaceholder
        }
    };

    function msLabel(field, key){ return MS[field].options[key] || key; }

    function msRenderChips(field){
        var cfg = MS[field];
        var chipsEl = document.getElementById(cfg.chipsId);
        if (!cfg.selected.size){
            chipsEl.innerHTML = '<span class="msPlaceholder">' + cfg.placeholder + '</span>';
        } else {
            chipsEl.innerHTML = Array.from(cfg.selected).map(function(key){
                return '<span class="msChip">' + msLabel(field, key) +
                    ' <span class="msX" onclick="event.stopPropagation(); msRemoveChip(\'' + field + '\',\'' + key + '\')">&times;</span></span>';
            }).join('');
        }
        var hiddenBox = document.getElementById(cfg.hiddenBoxId);
        hiddenBox.innerHTML = Array.from(cfg.selected).map(function(key){
            return '<input type="hidden" name="' + cfg.hiddenName + '" value="' + key + '">';
        }).join('');
        var otherWrap = document.getElementById(cfg.otherWrapId);
        otherWrap.style.display = cfg.selected.has('OTHER') ? 'block' : 'none';
        fitFormToScreen();
    }

    function msRenderList(field, filter){
        var cfg = MS[field];
        var listEl = document.getElementById(cfg.listId);
        var f = (filter || '').trim().toLowerCase();
        var entries = Object.entries(cfg.options).filter(function(e){
            return !f || e[1].toLowerCase().indexOf(f) !== -1;
        });
        if (!entries.length){
            listEl.innerHTML = '<div class="msEmpty">' + VREG_I18N.noMatch + '</div>';
            return;
        }
        listEl.innerHTML = entries.map(function(e){
            var checked = cfg.selected.has(e[0]) ? 'checked' : '';
            return '<label class="msOpt"><input type="checkbox" data-field="' + field + '" data-key="' + e[0] + '" ' + checked + '> ' + e[1] + '</label>';
        }).join('');
    }

    function msPosition(field){
        var cfg = MS[field];
        var box = document.getElementById(cfg.boxId);
        var panel = document.getElementById(cfg.panelId);
        var rect = box.getBoundingClientRect();
        var panelH = 270, panelW = 300;
        var vh = window.innerHeight, vw = window.innerWidth;
        var spaceBelow = vh - rect.bottom, spaceAbove = rect.top;
        var top, left = rect.left;
        if (spaceBelow >= panelH || spaceBelow >= spaceAbove){
            top = rect.bottom + 4;
        } else {
            top = rect.top - panelH - 4;
        }
        top = Math.max(6, Math.min(top, vh - panelH - 6));
        if (left + panelW > vw - 6) left = Math.max(6, vw - panelW - 6);
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.width = Math.max(rect.width, panelW) + 'px';
    }

    function msOpen(field){
        Object.keys(MS).forEach(function(f){
            if (f !== field) document.getElementById(MS[f].panelId).style.display = 'none';
        });
        msRenderList(field, '');
        var panel = document.getElementById(MS[field].panelId);
        panel.style.display = 'flex';
        msPosition(field);
        var search = panel.querySelector('.msSearch');
        if (search){ search.value = ''; search.focus(); }
    }
    function msClose(field){ document.getElementById(MS[field].panelId).style.display = 'none'; }
    function msSelectAll(field){
        var cfg = MS[field];
        Object.keys(cfg.options).forEach(function(k){ cfg.selected.add(k); });
        msRenderList(field, panelSearchVal(field));
        msRenderChips(field);
    }
    function msClearAll(field){
        MS[field].selected.clear();
        msRenderList(field, panelSearchVal(field));
        msRenderChips(field);
    }
    function msFilter(field, val){ msRenderList(field, val); }
    function msRemoveChip(field, key){
        MS[field].selected.delete(key);
        msRenderChips(field);
        if (document.getElementById(MS[field].panelId).style.display === 'flex'){
            msRenderList(field, panelSearchVal(field));
        }
    }
    function panelSearchVal(field){
        var el = document.querySelector('#' + MS[field].panelId + ' .msSearch');
        return el ? el.value : '';
    }

    document.addEventListener('change', function(e){
        if (e.target.matches('.msPanel input[type=checkbox]')){
            var field = e.target.getAttribute('data-field'), key = e.target.getAttribute('data-key');
            if (e.target.checked) MS[field].selected.add(key); else MS[field].selected.delete(key);
            msRenderChips(field);
        }
    });
    document.addEventListener('click', function(e){
        Object.keys(MS).forEach(function(f){
            var cfg = MS[f];
            var panel = document.getElementById(cfg.panelId), box = document.getElementById(cfg.boxId);
            if (panel.style.display === 'flex' && !panel.contains(e.target) && !box.contains(e.target)){
                panel.style.display = 'none';
            }
        });
    });
    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape'){
            Object.keys(MS).forEach(function(f){ document.getElementById(MS[f].panelId).style.display = 'none'; });
        }
    });
    window.addEventListener('resize', function(){
        Object.keys(MS).forEach(function(f){
            if (document.getElementById(MS[f].panelId).style.display === 'flex') msPosition(f);
        });
    });

    // Dynamic, entity-type-driven document checklist. Which documents are
    // required switches automatically per Chris's spec — the list below
    // is generated server-side from VendorDocumentChecklistService so it
    // can never drift out of sync with what register() actually validates.
    // REBUILT AGAIN 9 Aug 2026 per Chris: "no scroll for choosing ssm
    // document...show the document selected and no truncation...i cant
    // see the submit button." Cards now page 3-at-a-time via Prev/Next
    // (docBox itself never scrolls, however many documents an entity type
    // has) and a running "X of Y attached" summary sits above the cards.
    // Native HTML "required" is deliberately NOT used on the file inputs
    // any more — a hidden (not-currently-shown) page's required fields
    // would be silently skipped by the browser's own validation, which
    // would let a vendor submit without ever seeing a mandatory document
    // on another page. Instead, the form's submit handler below walks
    // every document for the selected entity type itself, regardless of
    // which page is currently showing, and blocks + jumps to the right
    // page if anything mandatory is missing. The server re-checks this
    // again regardless (VendorAuthController::register()), same as always.
    var DOC_CHECKLISTS = @json($documentChecklists);
    var DOC_PER_PAGE = 3;
    var docCurrentPage = 0;

    function renderDocChecklist(){
        docCurrentPage = 0;
        var entityType = document.getElementById('entity_type').value;
        var box = document.getElementById('docChecklistBox');
        var list = DOC_CHECKLISTS[entityType];
        if (!list){
            box.innerHTML = '<div class="docEmpty">' + VREG_I18N.docEmptyNote + '</div>';
            fitFormToScreen();
            return;
        }
        var cardsHtml = list.map(function(doc, idx){
            var descHtml = doc.description ? '<div class="docCardDesc">' + doc.description + '</div>' : '';
            var isMandatory = doc.tier === 'MANDATORY';
            var reqStar = isMandatory ? ' <span class="req">*</span>' : '';
            var initialStatus = isMandatory ? VREG_I18N.docRequiredStatus : VREG_I18N.docNotAttachedStatus;
            var page = Math.floor(idx / DOC_PER_PAGE);
            return '<div class="docCard" data-page="' + page + '" style="display:none;">' +
                '<label class="docCardHead">' +
                    '<input type="checkbox" id="docChk_' + idx + '" onchange="docCardToggle(' + idx + ', this.checked)">' +
                    '<span class="docCardName">' + doc.label + reqStar + '</span>' +
                '</label>' +
                descHtml +
                '<div class="docCardFileRow">' +
                    '<input type="file" id="docFile_' + idx + '" name="documents[' + doc.key + ']" accept=".pdf,.jpg,.jpeg,.png" data-mandatory="' + (isMandatory ? '1' : '0') + '" onchange="docCardFileChanged(' + idx + ')">' +
                    '<span class="docCardStatus" id="docStat_' + idx + '">' + initialStatus + '</span>' +
                '</div>' +
            '</div>';
        }).join('');

        box.innerHTML =
            '<div class="docSummary" id="docSummary"></div>' +
            '<div id="docCardsViewport">' + cardsHtml + '</div>' +
            '<div class="docPageNav">' +
                '<button type="button" class="docPageBtn" id="docPrevBtn" onclick="docGoToPage(docCurrentPage - 1)">' + VREG_I18N.docPrevBtn + '</button>' +
                '<span class="docPageInfo" id="docPageInfo"></span>' +
                '<button type="button" class="docPageBtn" id="docNextBtn" onclick="docGoToPage(docCurrentPage + 1)">' + VREG_I18N.docNextBtn + '</button>' +
            '</div>';

        docGoToPage(0);
    }

    function docTotalPages(){
        var entityType = document.getElementById('entity_type').value;
        var list = DOC_CHECKLISTS[entityType] || [];
        return Math.max(1, Math.ceil(list.length / DOC_PER_PAGE));
    }

    function docGoToPage(page){
        var total = docTotalPages();
        if (page < 0) page = 0;
        if (page > total - 1) page = total - 1;
        docCurrentPage = page;
        document.querySelectorAll('#docCardsViewport .docCard').forEach(function(card){
            card.style.display = (parseInt(card.getAttribute('data-page'), 10) === page) ? 'block' : 'none';
        });
        var prevBtn = document.getElementById('docPrevBtn'), nextBtn = document.getElementById('docNextBtn');
        if (prevBtn) prevBtn.disabled = (page === 0);
        if (nextBtn) nextBtn.disabled = (page === total - 1);
        var info = document.getElementById('docPageInfo');
        if (info) info.textContent = VREG_I18N.docPageOf.replace(':current', page + 1).replace(':last', total);
        docUpdateSummary();
        fitFormToScreen();
    }

    function docUpdateSummary(){
        var entityType = document.getElementById('entity_type').value;
        var list = DOC_CHECKLISTS[entityType] || [];
        var attached = 0, mandatory = 0, mandatoryAttached = 0;
        list.forEach(function(doc, idx){
            var f = document.getElementById('docFile_' + idx);
            var has = !!(f && f.files && f.files.length);
            if (has) attached++;
            if (doc.tier === 'MANDATORY'){ mandatory++; if (has) mandatoryAttached++; }
        });
        var el = document.getElementById('docSummary');
        if (!el) return;
        el.textContent = '📎 ' + VREG_I18N.docSummaryTemplate
            .replace(':attached', attached).replace(':total', list.length)
            .replace(':mandatoryDone', mandatoryAttached).replace(':mandatoryTotal', mandatory);
    }

    function docCardFileChanged(idx){
        var f = document.getElementById('docFile_' + idx);
        var chk = document.getElementById('docChk_' + idx);
        chk.checked = !!(f.files && f.files.length);
        docCardStatusUpdate(idx);
    }
    function docCardToggle(idx, checked){
        if (!checked){
            var f = document.getElementById('docFile_' + idx);
            f.value = '';
        }
        docCardStatusUpdate(idx);
    }
    function docCardStatusUpdate(idx){
        var f = document.getElementById('docFile_' + idx);
        var s = document.getElementById('docStat_' + idx);
        if (f.files && f.files.length){
            s.textContent = '✓ ' + f.files[0].name;
            s.classList.add('uploaded');
        } else {
            s.textContent = (f.dataset.mandatory === '1') ? VREG_I18N.docRequiredStatus : VREG_I18N.docNotAttachedStatus;
            s.classList.remove('uploaded');
        }
        docUpdateSummary();
        fitFormToScreen();
    }

    // FIXED 12 Aug 2026 — per Chris's screenshot: a marketing_slideshow
    // (Step 3) error was landing the vendor on Step 2, hiding where the
    // real problem actually was. This used to always jump to Step 2 on
    // any validation round-trip (as long as entity_type was filled in),
    // regardless of which step the actual failing field belonged to.
    // Now it reads the real error keys from the server and jumps to
    // whichever step actually has the problem — Step 3 errors win first
    // since they're checked last (declaration/marketing fields), then
    // Step 2, else Step 1.
    var ERROR_KEYS = @json(array_keys($errors->getMessages()));
    var STEP3_FIELDS = ['marketing_video', 'marketing_slideshow', 'marketing_flyer', 'marketing_link', 'declaration'];
    var STEP2_FIELDS = ['entity_type', 'documents'];
    function stepForErrors(){
        if (!ERROR_KEYS.length) return null;
        for (var i = 0; i < ERROR_KEYS.length; i++){
            if (STEP3_FIELDS.some(function(p){ return ERROR_KEYS[i].indexOf(p) === 0; })) return 3;
        }
        for (var i = 0; i < ERROR_KEYS.length; i++){
            if (STEP2_FIELDS.some(function(p){ return ERROR_KEYS[i].indexOf(p) === 0; })) return 2;
        }
        return 1;
    }
    document.addEventListener('DOMContentLoaded', function(){
        msRenderChips('industry');
        msRenderChips('activity');
        var errorStep = stepForErrors();
        if (document.getElementById('entity_type').value) {
            renderDocChecklist();
        }
        if (errorStep){
            goStep(errorStep);
        } else if (document.getElementById('entity_type').value) {
            goStep(2);
        } else {
            goStep(1);
        }
        fitFormToScreen();
    });

    document.getElementById('vendorRegForm').addEventListener('submit', function(e){
        // Defensive re-checks (already gated when moving Step 1 -> Step 2,
        // but this is the final, authoritative client-side gate).
        if (!MS.industry.selected.size){
            e.preventDefault();
            goStep(1);
            alert(VREG_I18N.alertSelectIndustry);
            return;
        }
        if (MS.industry.selected.has('OTHER') && !document.getElementById('industry_other_text').value.trim()){
            e.preventDefault();
            goStep(1);
            alert(VREG_I18N.alertSpecifyIndustryOther);
            document.getElementById('industry_other_text').focus();
            return;
        }
        if (!MS.activity.selected.size){
            e.preventDefault();
            goStep(1);
            alert(VREG_I18N.alertSelectNature);
            return;
        }
        if (MS.activity.selected.has('OTHER') && !document.getElementById('nature_of_business_other_text').value.trim()){
            e.preventDefault();
            goStep(1);
            alert(VREG_I18N.alertSpecifyNatureOther);
            document.getElementById('nature_of_business_other_text').focus();
            return;
        }
        var entityType = document.getElementById('entity_type').value;
        if (!entityType){
            e.preventDefault();
            goStep(2);
            alert(VREG_I18N.alertSelectEntityType);
            document.getElementById('entity_type').focus();
            return;
        }

        // Mandatory-document check across ALL pages, not just the one
        // currently showing — see the big comment above DOC_CHECKLISTS.
        var list = DOC_CHECKLISTS[entityType] || [];
        for (var i = 0; i < list.length; i++){
            if (list[i].tier === 'MANDATORY'){
                var f = document.getElementById('docFile_' + i);
                if (!f || !f.files || !f.files.length){
                    e.preventDefault();
                    goStep(2);
                    docGoToPage(Math.floor(i / DOC_PER_PAGE));
                    alert(VREG_I18N.alertPleaseUploadPrefix + list[i].label);
                    return;
                }
            }
        }
    });

    // Real "fit on one screen" guarantee, per Chris's compulsory no-scroll
    // rule. vh-based sizing alone can't account for a specific browser's
    // address bar / tabs / bookmarks bar / the Windows taskbar all eating
    // into the real visible height — so this measures the ACTUAL space
    // available (the right-panel's real rendered height) against the
    // form's actual content height, and gently scales the whole form down
    // (never up) until it truly fits. Runs on load, resize, and whenever
    // dynamic content (document checklist, chips, step switch) changes
    // size. This is now a rarely-needed last-resort safety net — the
    // Step 1/Step 2 split and the document Prev/Next paging above do the
    // real work of keeping each screen short enough on their own.
    function fitFormToScreen(){
        var scale = document.getElementById('formScale');
        var available = scale.parentElement.parentElement.clientHeight; // .right-panel
        scale.style.transform = 'scale(1)';
        var needed = scale.scrollHeight;
        if (needed > available){
            var ratio = Math.max(available / needed, 0.72); // never shrink below 72%
            scale.style.transform = 'scale(' + ratio + ')';
        }
    }
    window.addEventListener('load', fitFormToScreen);
    window.addEventListener('resize', fitFormToScreen);
</script>

</body>
</html>
