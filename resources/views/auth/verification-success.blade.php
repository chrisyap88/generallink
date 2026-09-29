<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $vaGroupName ?? 'GeneralLink' }} {{ __('auth.account_activated_title_suffix') }}</title>
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
        .form-box { width: 100%; max-width: 380px; text-align: center; }

        .success-icon {
            width: 7vh; height: 7vh;
            max-width: 52px; max-height: 52px;
            background: linear-gradient(135deg, #38A169, #1B9AE4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1vh;
            font-size: clamp(1rem, 2.4vh, 1.4rem);
            box-shadow: 0 4px 20px rgba(56,161,105,0.3);
            animation: pop .5s ease;
        }
        @keyframes pop {
            0% { transform: scale(0); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(1.3rem, 2.2vw, 1.7rem);
            font-weight: 700;
            color: #0D5A8E;
            margin-bottom: .4vh;
        }
        .agent-name { font-size: clamp(.78rem, 1vw, .9rem); font-weight: 600; color: #38A169; margin-bottom: .4vh; }
        .subtitle { font-size: clamp(.72rem, .95vw, .82rem); color: #718096; margin-bottom: 1.5vh; line-height: 1.4; }

        .info-box {
            background: #f0f9ff;
            border-radius: 8px;
            padding: 1vh 1rem;
            margin-bottom: 1.5vh;
            border-left: 3px solid #38A169;
            text-align: left;
        }
        .info-row { display: flex; justify-content: space-between; font-size: clamp(.7rem, .9vw, .8rem); margin-bottom: .5vh; color: #2D3748; }
        .info-row:last-child { margin-bottom: 0; }
        .info-label { color: #718096; font-weight: 500; }
        .info-value { font-weight: 600; color: #0D5A8E; }

        .btn-login {
            display: block;
            width: 100%;
            padding: .8vh;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: clamp(.8rem, 1.1vw, .9rem);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
            margin-bottom: 1.2vh;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .93; }

        .countdown { font-size: clamp(.7rem, .9vw, .8rem); color: #718096; margin-bottom: 1vh; }
        .countdown span { font-weight: 700; color: #1B9AE4; }

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
    <img class="logo-img" src="{{ $vaLogoUrl ?? asset('images/generallink-logo.jpeg') }}" alt="{{ $vaGroupName ?? 'GeneralLink' }} Logo" />
    <div class="brand-name">{{ $vaGroupName ?? 'GeneralLink' }}</div>
    <div class="slogan">{{ __('auth.slogan') }}</div>
</div>

<div class="right-panel">
    <div class="form-box">
        <div class="success-icon">✅</div>

        <h1>{{ __('auth.account_activated_heading') }}</h1>
        <p class="agent-name">{{ __('auth.congratulations_name', ['name' => $vaName ?? 'Agent']) }}</p>
        <p class="subtitle">{{ __('auth.account_activated_subtitle', ['group' => $vaGroupName ?? 'GeneralLink']) }}</p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">{{ __('vendor.account_status_label') }}</span>
                <span class="info-value" style="color:#38A169;">{{ __('auth.active_badge') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('auth.your_role_label') }}</span>
                <span class="info-value">{{ \App\Services\RoleLabelService::label($vaRole ?? 'INTRODUCER') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('auth.affiliate_code_label') }}</span>
                <span class="info-value">{{ $vaQr ?? __('auth.generating_placeholder') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{{ __('auth.activated_on_label') }}</span>
                <span class="info-value">{{ now()->format('d M Y, h:i A') }}</span>
            </div>
        </div>

        <a href="{{ $vaLoginRoute }}" class="btn-login">{{ __('auth.continue_to_login_button') }}</a>

        <p class="countdown">{{ __('auth.redirecting_prefix') }} <span id="timer">8</span> {{ __('auth.redirecting_suffix') }}</p>

        <div class="form-footer">{{ __('auth.footer_full') }}</div>
    </div>
</div>

<script>
    let seconds = 8;
    const timer = document.getElementById('timer');
    const redirectUrl = "{{ $vaLoginRoute }}";
    const interval = setInterval(() => {
        seconds--;
        timer.textContent = seconds;
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = redirectUrl;
        }
    }, 1000);
</script>

@include('partials.ai-assistant-widget', ['guestMode' => true])
</body>
</html>
