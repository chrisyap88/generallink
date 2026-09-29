<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GeneralLink GLADE – Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        {{-- REBUILT 28 Aug 2026 — per Chris: this screen must be the exact
             same GeneralLink login page (same logo, same wording, same
             layout) as auth/login.blade.php.
             REVERTED 28 Aug 2026 — per Chris: "i dont like the green
             color, just follow back the generallink color scheme" — back
             to the exact same blue palette as the main dashboard/login
             (--gl-blue:#1565C0 etc.), so GLADE is now visually identical
             to the main portal, not just structurally identical. --}}
        :root{--gl-blue:#1565C0;--gl-blue2:#1E88E5;--gl-cyan2:#B2EBF2;--gl-light:#E0F7FA;}
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: var(--gl-light); }

        .left-panel {
            flex: 0 0 32%;
            height: 100vh;
            background: linear-gradient(160deg, var(--gl-light) 0%, var(--gl-cyan2) 40%, var(--gl-light) 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2vh 1.5vw;
            gap: 1.5vh;
        }
        .logo-img {
            width: 85%;
            max-width: 250px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(15,118,110,0.2);
        }
        .slogan {
            font-style: italic;
            font-size: clamp(.75rem, 1.2vw, 1rem);
            font-weight: 500;
            color: var(--gl-blue);
            text-align: center;
        }
        .pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }
        .pill {
            background: rgba(255,255,255,0.75);
            border-radius: 50px;
            padding: .2rem .8rem;
            font-size: clamp(.65rem, .9vw, .78rem);
            color: var(--gl-blue);
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.9);
        }

        .right-panel {
            flex: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            background: #fff;
            padding: 2vh 3vw 2vh 5vw;
            position: relative;
        }
        .guest-lang-switch {
            position: absolute;
            top: 2vh;
            right: 3vw;
            display: flex;
            gap: .4rem;
            align-items: center;
            font-size: clamp(.65rem, .85vw, .75rem);
        }
        .guest-lang-switch form { display: inline; }
        .guest-lang-switch button {
            background: none;
            border: 1px solid var(--gl-cyan2);
            border-radius: 20px;
            padding: .2rem .7rem;
            font-size: inherit;
            font-family: 'Outfit', sans-serif;
            color: var(--gl-blue);
            cursor: pointer;
        }
        .guest-lang-switch button.active {
            background: var(--gl-blue2);
            border-color: var(--gl-blue2);
            color: #fff;
            font-weight: 600;
        }
        .form-box { width: 100%; max-width: 380px; }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(1.3rem, 2.2vw, 1.7rem);
            font-weight: 700;
            color: var(--gl-blue);
            margin-bottom: .3vh;
        }
        .subtitle { font-size: clamp(.72rem, 1vw, .85rem); color: #38A169; font-weight: 500; margin-bottom: 1.5vh; }

        .alert-box {
            border-radius: 8px;
            padding: .5vh .8rem;
            font-size: clamp(.7rem, .9vw, .8rem);
            margin-bottom: 1vh;
            border-left: 3px solid;
        }
        .alert-error  { background:#fde8e8; border-color:#e53935; color:#b71c1c; }
        .alert-success{ background:#e8f5e9; border-color:#38A169; color:#1b5e20; }

        .field { margin-bottom: 1.2vh; }
        label { display: block; font-size: clamp(.7rem, .9vw, .78rem); font-weight: 600; color: #2D3748; margin-bottom: .3vh; }

        input[type="email"], input[type="password"] {
            width: 100%;
            padding: .6vh .9rem;
            border: 1.5px solid var(--gl-cyan2);
            border-radius: 10px;
            font-size: clamp(.78rem, 1vw, .88rem);
            font-family: 'Outfit', sans-serif;
            color: #2D3748;
            background: #f7fdff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus { border-color: var(--gl-blue2); background: #fff; box-shadow: 0 0 0 3px rgba(15,118,110,.12); }

        .row-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5vh;
            font-size: clamp(.7rem, .9vw, .8rem);
        }
        .remember { display: flex; align-items: center; gap: .4rem; color: #718096; cursor: pointer; }
        .remember input[type="checkbox"] { accent-color: var(--gl-blue2); width: 13px; height: 13px; }
        .forgot { color: var(--gl-blue2); text-decoration: none; font-weight: 500; }
        .forgot:hover { text-decoration: underline; }

        .btn-signin {
            width: 100%;
            padding: .7vh;
            background: linear-gradient(90deg, var(--gl-blue2) 0%, var(--gl-blue) 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: clamp(.8rem, 1.1vw, .9rem);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(15,118,110,.25);
            margin-bottom: 1.5vh;
        }
        .btn-signin:hover { opacity: .93; }

        .register-row {
            text-align: center;
            font-size: clamp(.72rem, .95vw, .82rem);
            color: #718096;
            margin-bottom: 1vh;
        }
        .register-row a { color: var(--gl-blue2); font-weight: 600; text-decoration: none; }
        .register-row a:hover { text-decoration: underline; }

        .form-footer {
            text-align: center;
            font-size: clamp(.58rem, .75vw, .65rem);
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #b0bec5;
        }

        @media (max-width: 700px) {
            html, body { height: auto; min-height: 100vh; overflow-y: auto; overflow-x: hidden; }
            body { flex-direction: column; }
            .left-panel { flex: 0 0 auto; width: 100%; height: auto; padding: 3vh 6vw 2.5vh; gap: 1vh; }
            .logo-img { max-width: 160px; }
            .right-panel { flex: 0 0 auto; width: 100%; height: auto; justify-content: center; padding: 2.5vh 7vw 70px; }
        }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink Logo" />
    {{-- NEW 28 Aug 2026 — per Chris: "the login page also follow
         Generallink wording and everything same as General Link except
         add in with the Word GLADE." Every other word on this page is
         still identical to auth/login.blade.php — this one small badge
         is the only addition, so a CBE member knows they're on the right
         portal. --}}
    <div style="font-size:11px;font-weight:800;letter-spacing:0.2em;color:var(--gl-blue);">GLADE</div>
    <div class="slogan">{{ __('auth.slogan') }}</div>
    <div class="pills">
        <span class="pill">{{ __('auth.pill_ai') }}</span>
        <span class="pill">{{ __('auth.pill_analytics') }}</span>
        <span class="pill">{{ __('auth.pill_secure') }}</span>
    </div>
    @include('partials.intro-video-widget')
</div>

<div class="right-panel">
    <div class="guest-lang-switch">
        @php $curLang = ['en'=>'EN','ms'=>'MS','zh'=>'ZH'][app()->getLocale()] ?? 'EN'; @endphp
        <form method="POST" action="{{ route('language.guest-switch') }}">
            @csrf
            <input type="hidden" name="language" value="EN">
            <button type="submit" class="{{ $curLang === 'EN' ? 'active' : '' }}">ENG</button>
        </form>
        <form method="POST" action="{{ route('language.guest-switch') }}">
            @csrf
            <input type="hidden" name="language" value="MS">
            <button type="submit" class="{{ $curLang === 'MS' ? 'active' : '' }}">BM</button>
        </form>
        <form method="POST" action="{{ route('language.guest-switch') }}">
            @csrf
            <input type="hidden" name="language" value="ZH">
            <button type="submit" class="{{ $curLang === 'ZH' ? 'active' : '' }}">中文</button>
        </form>
    </div>
    <div class="form-box">
        <h1>{{ __('auth.welcome_back') }}</h1>
        <p class="subtitle">{{ __('auth.subtitle') }}</p>

        @if ($errors->any())
        <div class="alert-box alert-error">
            @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        @if (session('success'))
        <div class="alert-box alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
        <div class="alert-box alert-error">{{ session('error') }}</div>
        @endif

        {{-- Only functional difference from auth/login.blade.php: posts to
             glade.login.post (GladePortalController), which additionally
             checks hasGladeAccess() before letting anyone in. --}}
        <form method="POST" action="{{ route('glade.login.post') }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('auth.email_label') }}</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required autofocus />
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password_label') }}</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" placeholder="••••••••••" required style="padding-right:2.8rem;" />
                    <button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#90a4ae;font-size:1rem;">👁</button>
                </div>
            </div>
            <div class="row-flex">
                <label class="remember">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    {{ __('auth.remember_me') }}
                </label>
                <a href="#" class="forgot">{{ __('auth.forgot_password') }}</a>
            </div>
            <button type="submit" class="btn-signin">{{ __('auth.sign_in') }}</button>
        </form>

        <div class="register-row">
            {{ __('auth.no_account') }} <a href="{{ url('/register') }}">{{ __('auth.register_here') }}</a>
        </div>

        <div class="register-row" style="margin-bottom:1.5vh; color:#9ca3af; font-size:0.85em;">
            {{ __('auth.vendor_note') }}
        </div>

        <div class="form-footer">{{ __('auth.footer') }}</div>
    </div>
</div>

<script>function togglePw(){var i=document.getElementById("password");i.type=i.type==="password"?"text":"password";}</script>

@include('partials.ai-assistant-widget', ['guestMode' => true, 'guestPageLabel' => 'GLADE Login Page'])
</body>
</html>
