<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('special_group.sign_in_page_title', ['group' => $label->group_name]) }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }

        .left-panel {
            flex: 0 0 48%;
            height: 100vh;
            background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2vh 2vw;
            gap: 1.5vh;
        }
        .logo-img {
            width: auto;
            max-width: 320px;
            max-height: 30vh;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,131,143,0.2);
        }
        .slogan {
            font-style: italic;
            font-size: clamp(.75rem, 1.2vw, 1rem);
            font-weight: 500;
            color: #0D5A8E;
            text-align: center;
        }
        .pills { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }
        .pill {
            background: rgba(255,255,255,0.75);
            border-radius: 50px;
            padding: .2rem .8rem;
            font-size: clamp(.65rem, .9vw, .78rem);
            color: #0D5A8E;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.9);
        }

        .right-panel {
            flex: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 2vh 3vw;
        }
        .form-box { width: 100%; max-width: 380px; }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(1.3rem, 2.2vw, 1.7rem);
            font-weight: 700;
            color: #0D5A8E;
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
            border: 1.5px solid #b2ebf2;
            border-radius: 10px;
            font-size: clamp(.78rem, 1vw, .88rem);
            font-family: 'Outfit', sans-serif;
            color: #2D3748;
            background: #f7fdff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        input:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }

        .row-flex {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5vh;
            font-size: clamp(.7rem, .9vw, .8rem);
        }
        .remember { display: flex; align-items: center; gap: .4rem; color: #718096; cursor: pointer; }
        .remember input[type="checkbox"] { accent-color: #1B9AE4; width: 13px; height: 13px; }
        .forgot { color: #1B9AE4; text-decoration: none; font-weight: 500; }
        .forgot:hover { text-decoration: underline; }

        .btn-signin {
            width: 100%;
            padding: .7vh;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: clamp(.8rem, 1.1vw, .9rem);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
            margin-bottom: 1.5vh;
        }
        .btn-signin:hover { opacity: .93; }

        .register-row {
            text-align: center;
            font-size: clamp(.72rem, .95vw, .82rem);
            color: #718096;
            margin-bottom: 1vh;
        }
        .register-row a { color: #1B9AE4; font-weight: 600; text-decoration: none; }
        .register-row a:hover { text-decoration: underline; }

        .form-footer {
            text-align: center;
            font-size: clamp(.58rem, .75vw, .65rem);
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #b0bec5;
        }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ $label->logo_path ? asset('images/' . $label->logo_path) : asset('images/generallink-logo.jpeg') }}" alt="{{ $label->group_name }} Logo" />
    <div class="slogan">{{ __('special_group.slogan_text') }}</div>
    <div class="pills">
        <span class="pill">{{ __('special_group.pill_ai_powered') }}</span>
        <span class="pill">{{ __('special_group.pill_real_time_analytics') }}</span>
        <span class="pill">{{ __('special_group.pill_secure_transparent') }}</span>
    </div>
</div>

<div class="right-panel">
    <div class="form-box">
        <h1>{{ __('special_group.welcome_back_heading') }}</h1>
        <p class="subtitle">{{ __('special_group.sign_in_subtitle') }}</p>

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

        <form method="POST" action="{{ route('special-group.login.post', $label->slug) }}">
            @csrf
            <div class="field">
                <label for="email">{{ __('special_group.field_email_address_label') }}</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required autofocus />
            </div>
            <div class="field">
                <label for="password">{{ __('special_group.field_password_label') }}</label>
                <div style="position:relative;">
                    <input type="password" id="password" name="password" placeholder="••••••••••" required style="padding-right:2.8rem;" />
                    <button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#90a4ae;font-size:1rem;">👁</button>
                </div>
            </div>
            <div class="row-flex">
                <label class="remember">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    {{ __('special_group.remember_me_label') }}
                </label>
                <a href="#" class="forgot">{{ __('special_group.forgot_password_link') }}</a>
            </div>
            <button type="submit" class="btn-signin">{{ __('special_group.sign_in_button') }}</button>
        </form>

        <div class="register-row">
            {{ __('special_group.new_to_group_note', ['group' => $label->group_name]) }} <a href="{{ route('special-group.join', $label->slug) }}">{{ __('special_group.join_here_link') }}</a>
        </div>

        <div class="form-footer">{{ __('special_group.footer_tagline') }}</div>
    </div>
</div>

<script>function togglePw(){var i=document.getElementById("password");i.type=i.type==="password"?"text":"password";}</script>
</body>
</html>
