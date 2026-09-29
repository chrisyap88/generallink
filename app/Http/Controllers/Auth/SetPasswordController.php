<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeneralLink – Create Your Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Outfit', sans-serif; background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%); }
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: .8rem; }

        .card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(13,90,142,0.15);
            padding: 1.3rem 1.5rem;
            max-width: 400px;
            width: 100%;
        }

        .logo-img {
            width: 100%;
            max-width: 190px;
            border-radius: 10px;
            margin: 0 auto .7rem;
            display: block;
            box-shadow: 0 4px 16px rgba(0,131,143,0.15);
        }

        .icon { font-size: 1.5rem; text-align: center; margin-bottom: .2rem; }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 1.25rem;
            font-weight: 700;
            color: #0D5A8E;
            text-align: center;
            margin-bottom: .15rem;
        }
        .subtitle { font-size: .72rem; color: #718096; text-align: center; margin-bottom: .8rem; }

        .alert-error {
            background: #fde8e8;
            border-left: 3px solid #e53935;
            border-radius: 6px;
            padding: .5rem .7rem;
            font-size: .72rem;
            color: #b71c1c;
            margin-bottom: .8rem;
        }

        .field { margin-bottom: .6rem; }
        label { display: block; font-size: .72rem; font-weight: 600; color: #2D3748; margin-bottom: .22rem; }

        .input-wrap { position: relative; }
        .input-wrap input {
            width: 100%;
            padding: .5rem 2.4rem .5rem .8rem;
            border: 1.5px solid #b2ebf2;
            border-radius: 8px;
            font-size: .82rem;
            font-family: 'Outfit', sans-serif;
            color: #2D3748;
            background: #f7fdff;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-wrap input:focus { border-color: #1B9AE4; background: #fff; box-shadow: 0 0 0 3px rgba(27,154,228,.12); }
        .toggle-pw {
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; color: #90a4ae; font-size: .78rem;
        }

        .rules {
            background: #f0f9ff;
            border-radius: 8px;
            padding: .55rem .7rem;
            margin-bottom: .7rem;
            border-left: 3px solid #1B9AE4;
        }
        .rules-title { font-size: .7rem; font-weight: 700; color: #0D5A8E; margin-bottom: .3rem; }
        .rule-item { font-size: .68rem; color: #2D3748; margin-bottom: .14rem; display: flex; align-items: center; gap: .35rem; }
        .rule-item.ok { color: #38A169; }
        .rule-item.fail { color: #e53935; }

        .strength-bar { height: 4px; border-radius: 3px; background: #e2e8f0; margin-top: .35rem; margin-bottom: .6rem; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 3px; transition: width .3s, background .3s; width: 0%; background: #e53935; }

        .btn-submit {
            width: 100%;
            padding: .65rem;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: .85rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
        }
        .btn-submit:hover { opacity: .93; }

        .footer { text-align: center; margin-top: .7rem; font-size: .58rem; letter-spacing: .1em; text-transform: uppercase; color: #b0bec5; }
    </style>
</head>
<body>
<div class="card">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpg') }}" alt="GeneralLink" />

    <div class="icon">🔐</div>
    <h1>Create Your Password</h1>
    <p class="subtitle">Set a strong password to secure your GeneralLink account</p>

    @if ($errors->any())
    <div class="alert-error">
        @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('auth.set-password.post') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token ?? '' }}" />

        <div class="field">
            <label for="password">New Password</label>
            <div class="input-wrap">
                <input type="password" id="password" name="password" placeholder="Create a strong password" required oninput="checkStrength(this.value)" />
                <button type="button" class="toggle-pw" onclick="togglePw('password', this)">👁</button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
        </div>

        <div class="rules" id="password-rules">
            <div class="rules-title">Password Requirements:</div>
            <div class="rule-item" id="rule-length">○ Minimum 10 characters</div>
            <div class="rule-item" id="rule-upper">○ At least one uppercase letter (A-Z)</div>
            <div class="rule-item" id="rule-lower">○ At least one lowercase letter (a-z)</div>
            <div class="rule-item" id="rule-number">○ At least one number (0-9)</div>
            <div class="rule-item" id="rule-special">○ At least one special character (!@#$%^&*)</div>
        </div>

        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <div class="input-wrap">
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Re-enter your password" required />
                <button type="button" class="toggle-pw" onclick="togglePw('password_confirmation', this)">👁</button>
            </div>
        </div>

        <button type="submit" class="btn-submit">Set Password & Activate Account</button>
    </form>

    <div class="footer">GeneralLink Digital Ecosystem · AI-Powered · Malaysia · Southeast Asia</div>
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
    const texts = { length: 'Minimum 10 characters', upper: 'At least one uppercase letter (A-Z)', lower: 'At least one lowercase letter (a-z)', number: 'At least one number (0-9)', special: 'At least one special character (!@#$%^&*)' };
    Object.keys(rules).forEach(k => {
        const el = document.getElementById(labels[k]);
        el.textContent = (rules[k] ? '✓ ' : '○ ') + texts[k];
        el.className = 'rule-item ' + (rules[k] ? 'ok' : 'fail');
    });
}
</script>
</body>
</html>