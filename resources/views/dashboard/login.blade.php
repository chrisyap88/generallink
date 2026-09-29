<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeneralLink – Sign In</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; font-family: 'Outfit', sans-serif; }
        body { display: flex; min-height: 100vh; background: #e0f7fa; }

        .left-panel {
            flex: 0 0 48%;
            background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            gap: 1.8rem;
        }
        .logo-img {
            width: 100%;
            max-width: 420px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,131,143,0.2);
            display: block;
        }
        .slogan {
            font-style: italic;
            font-size: 1.05rem;
            font-weight: 500;
            color: #0D5A8E;
            text-align: center;
        }
        .pills { display: flex; gap: .6rem; flex-wrap: wrap; justify-content: center; }
        .pill {
            background: rgba(255,255,255,0.75);
            border-radius: 50px;
            padding: .35rem .9rem;
            font-size: .75rem;
            color: #0D5A8E;
            font-weight: 500;
            border: 1px solid rgba(255,255,255,0.9);
        }

        .right-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2.5rem;
            background: #fff;
            overflow-y: auto;
        }
        .form-box { width: 100%; max-width: 420px; padding-top: 1.5rem; }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: 2.4rem;
            font-weight: 700;
            color: #0D5A8E;
            margin-bottom: .3rem;
        }
        .subtitle { font-size: .9rem; color: #38A169; font-weight: 500; margin-bottom: 2rem; }

        .alert-error {
            background: #fde8e8;
            border-left: 3px solid #e53935;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .85rem;
            color: #b71c1c;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #e8f5e9;
            border-left: 3px solid #38A169;
            border-radius: 8px;
            padding: .75rem 1rem;
            font-size: .85rem;
            color: #1b5e20;
            margin-bottom: 1.5rem;
        }

        .field { margin-bottom: 1.3rem; }
        label { display: block; font-size: .82rem; font-weight: 600; color: #2D3748; margin-bottom: .45rem; }

        input[type="email"], input[type="password"] {
            width: 100%;
            padding: .78rem 1rem;
            border: 1.5px solid #b2ebf2;
            border-radius: 10px;
            font-size: .92rem;
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
            margin-bottom: 1.6rem;
            font-size: .84rem;
        }
        .remember { display: flex; align-items: center; gap: .5rem; color: #718096; cursor: pointer; }
        .remember input[type="checkbox"] { accent-color: #1B9AE4; width: 16px; height: 16px; }
        .forgot { color: #1B9AE4; text-decoration: none; font-weight: 500; }
        .forgot:hover { text-decoration: underline; }

        .btn-signin {
            width: 100%;
            padding: .9rem;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: .95rem;
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
        }
        .btn-signin:hover { opacity: .93; }

        .register-row {
            text-align: center;
            margin-top: 1.4rem;
            font-size: .85rem;
            color: #718096;
        }
        .register-row a { color: #1B9AE4; font-weight: 600; text-decoration: none; }
        .register-row a:hover { text-decoration: underline; }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            font-size: .68rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: #b0bec5;
        }

        @media (max-width: 768px) {
            body { flex-direction: column; }
            .left-panel { flex: 0 0 auto; padding: 2rem 1.5rem; }
            .right-panel { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpg') }}" alt="GeneralLink Logo" />
    <div class="slogan">Smarter. More Efficient. Highly Transparent.</div>
    <div class="pills">
        <span class="pill">AI-Powered</span>
        <span class="pill">Real-Time Analytics</span>
        <span class="pill">Secure & Transparent</span>
    </div>
</div>

<div class="right-panel">
    <div class="form-box">
        <h1>Welcome Back</h1>
        <p class="subtitle">Sign in to your GeneralLink account</p>

        @if ($errors->any())
        <div class="alert-error">
            @foreach ($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
        </div>
        @endif

        @if (session('success'))
        <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
        <div class="alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('auth.login.post') }}">
            @csrf
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="{{ old('email') }}" required autofocus />
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div style="position:relative;"><input type="password" id="password" name="password" placeholder="••••••••••" required style="padding-right:2.8rem;" /><button type="button" onclick="togglePw()" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#90a4ae;font-size:1.1rem;">👁</button></div>
            </div>
            <div class="row-flex">
                <label class="remember">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    Remember me
                </label>
                <a href="#" class="forgot">Forgot password?</a>
            </div>
            <button type="submit" class="btn-signin">Sign In</button>
        </form>

        <div class="register-row">
            Don't have an account? <a href="{{ '#' }}">Register here</a>
        </div>

        <div class="form-footer">AI-POWERED · MALAYSIA · SOUTHEAST ASIA</div>
    </div>
</div>

<script>function togglePw(){var i=document.getElementById("password");i.type=i.type==="password"?"text":"password";}</script>
</body>
</html>
