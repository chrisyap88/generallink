<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.vendor_login_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }
        .left-panel { flex: 0 0 32%; height: 100vh; background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2vh 1.5vw; gap: 1.5vh; }
        .logo-img { width: 85%; max-width: 250px; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,131,143,0.2); }
        .slogan { font-style: italic; font-size: clamp(.75rem, 1.2vw, 1rem); font-weight: 500; color: #0D5A8E; text-align: center; }
        .pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }
        .pill { background: rgba(255,255,255,0.75); border-radius: 50px; padding: .2rem .8rem; font-size: clamp(.65rem, .9vw, .78rem); color: #0D5A8E; font-weight: 500; border: 1px solid rgba(255,255,255,0.9); }
        .right-panel { flex: 1; height: 100vh; display: flex; align-items: center; justify-content: flex-start; background: #fff; padding: 2vh 3vw 2vh 5vw; }
        .form-box { width: 100%; max-width: 380px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: clamp(1.3rem, 2.2vw, 1.7rem); font-weight: 700; color: #0D5A8E; margin-bottom: .3vh; }
        .subtitle { font-size: clamp(.72rem, 1vw, .85rem); color: #38A169; font-weight: 500; margin-bottom: 1.5vh; }
        .alert-box { border-radius: 8px; padding: .5vh .8rem; font-size: clamp(.7rem, .9vw, .8rem); margin-bottom: 1vh; border-left: 3px solid; }
        .alert-error { background:#fde8e8; border-color:#e53935; color:#b71c1c; }
        .alert-success{ background:#e8f5e9; border-color:#38A169; color:#1b5e20; }
        .field { margin-bottom: 1.2vh; }
        label { display: block; font-size: clamp(.7rem, .9vw, .78rem); font-weight: 600; color: #2D3748; margin-bottom: .3vh; }
        input[type="email"], input[type="password"] { width: 100%; padding: .6vh .9rem; border: 1.5px solid #b2ebf2; border-radius: 10px; font-size: clamp(.78rem, 1vw, .88rem); font-family: 'Outfit', sans-serif; color: #2D3748; background: #f7fdff; outline: none; transition: border-color .2s, box-shadow .2s; }
        input:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }
        .row-flex { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5vh; font-size: clamp(.7rem, .9vw, .8rem); }
        .remember { display: flex; align-items: center; gap: .4rem; color: #718096; cursor: pointer; }
        .remember input[type="checkbox"] { accent-color: #1B9AE4; width: 13px; height: 13px; }
        .btn-signin { width: 100%; padding: .7vh; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); color: #fff; border: none; border-radius: 10px; font-size: clamp(.8rem, 1.1vw, .9rem); font-family: 'Outfit', sans-serif; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; cursor: pointer; box-shadow: 0 4px 14px rgba(13,90,142,.25); margin-bottom: 1.5vh; }
        .btn-signin:hover { opacity: .93; }
        .register-row { text-align: center; font-size: clamp(.72rem, .95vw, .82rem); color: #718096; margin-bottom: 1vh; }
        .register-row a { color: #1B9AE4; font-weight: 600; text-decoration: none; }
        .register-row a:hover { text-decoration: underline; }
        .form-footer { text-align: center; font-size: clamp(.58rem, .75vw, .65rem); letter-spacing: .12em; text-transform: uppercase; color: #b0bec5; }

        /* NEW 14 Aug 2026 per Chris: this page was built for a desktop
           side-by-side layout with html/body locked to height:100vh and
           overflow:hidden — on a phone (narrow width, shorter real
           viewport once the browser's own address bar is accounted for)
           that fixed height clipped the bottom of the form, including
           the "New vendor? Register here" link, with no way to scroll
           down to it. Below this width, stack the two panels vertically
           and let the page take its natural height instead of forcing
           everything into exactly one screen — a public login page
           scrolling slightly on a phone is normal, unlike the no-scroll
           rule for the operational admin/agent screens. */
        @media (max-width: 700px) {
            html, body { height: auto; min-height: 100vh; overflow-y: auto; overflow-x: hidden; }
            body { flex-direction: column; }
            .left-panel { flex: 0 0 auto; width: 100%; height: auto; padding: 3vh 6vw 2.5vh; gap: 1vh; }
            .logo-img { max-width: 160px; }
            /* Extra bottom padding — the fixed Carolyn chat bubble (42px
               circle, bottom:20px; right:20px; z-index:9999) sits on top
               of whatever content happens to scroll to that corner; this
               keeps the "Register here" link clear of it. */
            .right-panel { flex: 0 0 auto; width: 100%; justify-content: center; padding: 2.5vh 7vw 70px; }
            .form-box { max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink Logo" />
    <div class="slogan">{{ __('auth.vendor_login_slogan') }}</div>
    <div class="pills">
        <span class="pill">{{ __('auth.vendor_pill_verified') }}</span>
        <span class="pill">{{ __('auth.vendor_pill_manage') }}</span>
    </div>
    @include('partials.intro-video-widget')
</div>

<div class="right-panel">
    <div class="form-box">
        <h1>{{ __('auth.vendor_login_heading') }}</h1>
        <p class="subtitle">{{ __('auth.vendor_login_subtitle') }}</p>

        @if ($errors->any())
        <div class="alert-box alert-error">
            @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        @if (session('success'))
        <div class="alert-box alert-success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('vendor.login.post') }}">
            @csrf
            <div class="field">
                <label for="vendor_email">{{ __('auth.email_label') }} <span style="font-weight:400; color:#9ca3af;">{{ __('auth.vendor_login_id_hint') }}</span></label>
                <input type="email" id="vendor_email" name="vendor_email" placeholder="you@company.com" value="{{ old('vendor_email') }}" required autofocus />
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password_label') }}</label>
                <input type="password" id="password" name="password" placeholder="••••••••••" required />
            </div>
            <div class="row-flex">
                <label class="remember">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    {{ __('auth.remember_me') }}
                </label>
            </div>
            <button type="submit" class="btn-signin">{{ __('auth.sign_in') }}</button>
        </form>

        {{-- FIXED 8 Aug 2026 per Chris: "dedicated login only for vendor,
             don't want the vendor confused why so many type of
             registration" — removed the "Agent/Admin login" escape link
             that used to sit here. This page now shows vendors nothing
             but vendor sign-in and vendor registration. --}}
        <div class="register-row">
            {{ __('auth.new_vendor_prompt') }} <a href="{{ route('vendor.register') }}">{{ __('auth.register_here') }}</a>
        </div>

        <div class="form-footer">{{ __('auth.footer') }}</div>
    </div>
</div>

{{-- NEW 8 Aug 2026 per Chris: same Carolyn widget every other login/
     registration screen has (auth/login.blade.php, auth/register.blade.php,
     etc.) — this vendor screen was missing it. --}}
@include('partials.ai-assistant-widget', ['guestMode' => true, 'guestPageLabel' => 'Vendor Login Page'])

@if(session('vendor_just_registered'))
<script>
    // NEW 12 Aug 2026 — per Chris: Carolyn greets a just-registered
    // vendor with a spoken (and visible) thank-you the moment they land
    // back here, instead of the generic greeting. window.__aiAssistantOpened
    // is set true FIRST so aiAssistantToggle()'s own auto-greeting logic
    // is skipped — this message replaces it, it doesn't play alongside it.
    document.addEventListener('DOMContentLoaded', function () {
        window.__aiAssistantOpened = true;
        var panel = document.getElementById('aiAssistantPanel');
        panel.style.display = 'flex';
        if (window.__aiAssistantRestorePanelPosition) { window.__aiAssistantRestorePanelPosition(); }
        var thankYouMsg = @json(session('vendor_just_registered'));
        aiAssistantAppend('assistant', thankYouMsg);
        aiAssistantSpeak(thankYouMsg);
        aiAssistantPersistState();
    });
</script>
@endif

</body>
</html>
