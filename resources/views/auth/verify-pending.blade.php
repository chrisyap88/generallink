<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $brandGroupName ?? 'GeneralLink' }} {{ __('auth.verify_pending_title_suffix') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        /* Left/right split layout — mirrors login.blade.php / set-password.blade.php.
           Fixed 100vh, no scroll ever (hard requirement, 15 Jul 2026). */
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; height: 100vh; background: #e0f7fa; }

        .left-panel {
            flex: 0 0 42%;
            height: 100vh;
            background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 40%, #e0f2f1 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2vh 2vw;
            gap: 1.4vh;
            text-align: center;
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
        }
        .slogan {
            font-style: italic;
            font-size: clamp(.7rem, 1.1vw, .9rem);
            font-weight: 500;
            color: #0D5A8E;
        }
        .welcome-note {
            margin-top: 1vh;
            font-size: clamp(.72rem, 1vw, .85rem);
            color: #0D5A8E;
            line-height: 1.6;
            max-width: 260px;
        }

        .right-panel {
            flex: 1;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 1.2vh 3vw;
        }
        .letter-box { width: 100%; max-width: 440px; }

        h1 {
            font-family: 'Rajdhani', sans-serif;
            font-size: clamp(1.1rem, 1.9vw, 1.4rem);
            font-weight: 700;
            color: #0D5A8E;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 1vh;
        }
        .salutation { font-size: clamp(.74rem, .95vw, .84rem); color: #1a1a1a; margin-bottom: .5vh; }
        .body-text { font-size: clamp(.7rem, .88vw, .78rem); color: #374151; line-height: 1.35; margin-bottom: .6vh; }

        .meta-box {
            background: #f0f9ff;
            border-radius: 6px;
            padding: .4vh .8rem;
            font-size: clamp(.64rem, .82vw, .7rem);
            color: #374151;
            margin-bottom: .8vh;
        }

        .btn-verify {
            display: block;
            width: 100%;
            text-align: center;
            padding: .6vh;
            background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%);
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            font-size: clamp(.76rem, 1.05vw, .86rem);
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(13,90,142,.25);
            margin: .6vh 0 .6vh;
        }
        .btn-verify:hover { opacity: .93; }

        .fallback-toggle {
            display: block;
            font-size: clamp(.62rem, .8vw, .68rem);
            color: #1565C0;
            cursor: pointer;
            user-select: none;
            margin-bottom: .5vh;
        }
        .fallback-box {
            display: none;
            background: #f0f9ff;
            border-radius: 6px;
            padding: .4vh .8rem;
            font-size: clamp(.6rem, .78vw, .66rem);
            color: #6b7280;
            margin-bottom: .6vh;
            word-break: break-all;
        }
        .fallback-box a { color: #1565C0; }

        .security-notice {
            font-size: clamp(.6rem, .78vw, .66rem);
            color: #92400e;
            background: #fff8e1;
            border-radius: 6px;
            padding: .4vh .8rem;
            margin-bottom: .7vh;
        }

        .signature { font-size: clamp(.7rem, .88vw, .78rem); color: #374151; margin-bottom: .7vh; }

        .form-footer {
            text-align: center;
            font-size: clamp(.56rem, .72vw, .62rem);
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #b0bec5;
        }

        .back-link { display: block; text-align: center; font-size: clamp(.66rem, .85vw, .72rem); color: #1B9AE4; text-decoration: none; margin-bottom: .6vh; }
    </style>
</head>
<body>

<div class="left-panel">
    <img class="logo-img" src="{{ $brandLogoUrl ?? asset('images/generallink-logo.jpeg') }}" alt="{{ $brandGroupName ?? 'GeneralLink' }} Logo" />
    <div class="brand-name">{{ $brandGroupName ?? 'GeneralLink' }}</div>
    <div class="slogan">{{ __('auth.slogan') }}</div>
    <div class="welcome-note">{{ __('auth.verify_pending_welcome_note', ['group' => $brandGroupName ?? 'GeneralLink']) }}</div>
</div>

<div class="right-panel">
    <div class="letter-box">
        <h1>{{ __('auth.activate_account_heading') }}</h1>

        <p class="salutation">{{ __('auth.dear_name', ['name' => $agent->full_name ?? 'Agent']) }}</p>

        <p class="body-text">
            {!! __('auth.verify_pending_body1', ['group' => e($brandGroupName ?? 'GeneralLink'), 'role' => '<strong>'.e($roleLabel ?? \App\Services\RoleLabelService::label('INTRODUCER')).'</strong>']) !!}
        </p>

        <p class="body-text">
            {{ __('auth.verify_pending_body2') }}
        </p>

        <div class="meta-box">
            <strong>{{ __('auth.registered_by_label') }}</strong> {{ $createdByName ?? __('auth.self_registered_fallback') }}<br>
            <strong>{{ __('auth.date_time_label') }}</strong> {{ $createdAt ?? now()->format('d M Y, h:i A') }}
        </div>

        @if($verifyLink)
        <a href="{{ $verifyLink }}" class="btn-verify">{{ __('auth.verify_email_button') }}</a>

        <div class="fallback-box">
            {{ __('auth.fallback_copy_link_note') }}<br>
            <a href="{{ $verifyLink }}">{{ $verifyLink }}</a>
        </div>
        @else
        <div class="fallback-box">
            {!! __('auth.verify_pending_no_session_note', ['email' => '<strong>'.e(session('email') ?? __('auth.your_inbox_fallback')).'</strong>']) !!}
        </div>
        @endif

        <p class="security-notice">{{ __('auth.security_notice_24h') }}</p>

        <p class="signature">
            {{ __('auth.kind_regards_label') }}<br>
            <strong>{{ $brandGroupName ?? 'GeneralLink' }} {{ __('auth.admin_director_suffix') }}</strong>
        </p>

        <div class="form-footer">{{ __('auth.footer_titlecase') }}</div>
    </div>
</div>

@include('partials.ai-assistant-widget', ['guestMode' => true])
@include('partials.ai-guidance-overlay')
</body>
</html>
