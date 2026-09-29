<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brandGroupName ?? 'GeneralLink' }} {{ __('auth.create_password_title_suffix') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        /* Left/right split layout — mirrors login.blade.php / special-group/login.blade.php.
           Fixed 100vh, no scroll ever (hard requirement, 15 Jul 2026). */
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }

        .left-panel {
            flex: 0 0 44%;
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
            max-width: 280px;
            max-height: 28vh;
            object-fit: contain;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,131,143,0.2);
        }
        .brand-name {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(1rem, 1.8vw, 1.3rem);
            font-weight: 700;
            color: #0D5A8E;
            text-align: center;
        }
        .slogan {
            font-style: italic;
            font-size: clamp(.7rem, 1.1vw, .9rem);
            font-weight: 500;
            color: #0D5A8E;
            text-align: center;
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
        .subtitle { font-size: clamp(.72rem, 1vw, .85rem); color: #718096; margin-bottom: 1.5vh; }

        .alert-error {
            background: #fde8e8;
            border-left: 3px solid #e53935;
            border-radius: 6px;
            padding: .6vh .8rem;
            font-size: clamp(.7rem, .9vw, .8rem);
            color: #b71c1c;
            margin-bottom: 1vh;
        }

        .field { margin-bottom: 1.1vh; }
        label { display: block; font-size: clamp(.7rem, .9vw, .78rem); font-weight: 600; color: #2D3748; margin-bottom: .3vh; }

        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            padding: .6vh 2.6rem .6vh .9rem;
            border: 1.5px solid #b2ebf2;
            border-radius: 10px;
            font-size: clamp(.78rem, 1vw, .88rem);
            font-family: 'Outfit', sans-serif;
            color: #2D3748;
            background: #f7fdff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-wrap input:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }
        .toggle-pw {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #90a4ae; font-size: .85rem;
        }

        .rules {
            background: #f0f9ff;
            border-radius: 8px;
            padding: .7vh .8rem;
            margin-bottom: 1vh;
            border-left: 3px solid #1B9AE4;
        }
        .rules-title { font-size: clamp(.66rem, .85vw, .72rem); font-weight: 700; color: #0D5A8E; margin-bottom: .3vh; }
        .rule-item { font-size: clamp(.62rem, .8vw, .68rem); color: #2D3748; margin-bottom: .2vh; display: flex; align-items: center; gap: .35rem; }
        .rule-item.ok { color: #38A169; }
        .rule-item.fail { color: #e53935; }

        .strength-bar { height: 4px; border-radius: 3px; background: #e2e8f0; margin-top: .4vh; margin-bottom: .8vh; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 3px; transition: width .3s, background .3s; width: 0%; background: #e53935; }

        .btn-submit {
            width: 100%;
            padding: .7vh;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: clamp(.8rem, 1.1vw, .9rem);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
            margin-bottom: 1vh;
        }
        .btn-submit:hover { opacity: .93; }

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
    <img class="logo-img" src="{{ $brandLogoUrl ?? asset('images/generallink-logo.jpeg') }}" alt="{{ $brandGroupName ?? 'GeneralLink' }} Logo" />
    <div class="brand-name">{{ $brandGroupName ?? 'GeneralLink' }}</div>
    <div class="slogan">{{ __('auth.slogan') }}</div>
</div>

<div class="right-panel">
    <div class="form-box">
        <h1>{{ __('auth.create_password_heading') }}</h1>
        <p class="subtitle">{{ __('auth.create_password_subtitle', ['group' => $brandGroupName ?? 'GeneralLink']) }}</p>

        @if ($errors->any())
        <div class="alert-error">
            @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('auth.set-password.post') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token ?? '' }}" />

            <div class="field">
                <label for="password">{{ __('auth.new_password_label') }}</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="{{ __('auth.create_password_placeholder') }}" required oninput="checkStrength(this.value)" />
                    <button type="button" class="toggle-pw" onclick="togglePw('password', this)">👁</button>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
            </div>

            <div class="rules" id="password-rules">
                <div class="rules-title">{{ __('auth.password_requirements_label') }}</div>
                <div class="rule-item" id="rule-length">○ {{ __('auth.rule_length') }}</div>
                <div class="rule-item" id="rule-upper">○ {{ __('auth.rule_upper') }}</div>
                <div class="rule-item" id="rule-lower">○ {{ __('auth.rule_lower') }}</div>
                <div class="rule-item" id="rule-number">○ {{ __('auth.rule_number') }}</div>
                <div class="rule-item" id="rule-special">○ {{ __('auth.rule_special') }}</div>
            </div>

            <div class="field">
                <label for="password_confirmation">{{ __('auth.confirm_password_label') }}</label>
                <div class="input-wrap">
                    <input type="password" id="password_confirmation" name="password_confirmation" placeholder="{{ __('auth.reenter_password_placeholder') }}" required />
                    <button type="button" class="toggle-pw" onclick="togglePw('password_confirmation', this)">👁</button>
                </div>
            </div>

            <button type="submit" class="btn-submit">{{ __('auth.set_password_activate_button') }}</button>
        </form>

        <div class="form-footer">{{ __('auth.footer_full') }}</div>
    </div>
</div>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
}

function checkStrength(val) {
    const rules = {
        length:  val.length >= 10,
        upper:   /[A-Z]/.test(val),
        lower:   /[a-z]/.test(val),
        number:  /[0-9]/.test(val),
        special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val),
    };
    const passed = Object.values(rules).filter(Boolean).length;
    const fill = document.getElementById('strength-fill');
    fill.style.width = (passed / 5 * 100) + '%';
    fill.style.background = passed <= 2 ? '#e53935' : passed <= 3 ? '#D97706' : passed <= 4 ? '#1B9AE4' : '#38A169';

    const labels = { length: 'rule-length', upper: 'rule-upper', lower: 'rule-lower', number: 'rule-number', special: 'rule-special' };
    const texts = { length: @json(__('auth.rule_length')), upper: @json(__('auth.rule_upper')), lower: @json(__('auth.rule_lower')), number: @json(__('auth.rule_number')), special: @json(__('auth.rule_special')) };
    Object.keys(rules).forEach(k => {
        const el = document.getElementById(labels[k]);
        el.textContent = (rules[k] ? '✓ ' : '○ ') + texts[k];
        el.className = 'rule-item ' + (rules[k] ? 'ok' : 'fail');
    });
}
</script>

@include('partials.ai-assistant-widget', ['guestMode' => true])
</body>
</html>
