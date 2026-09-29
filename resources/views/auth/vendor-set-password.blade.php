<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.vendor_set_password_page_title') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; }
        body { display: flex; align-items: center; justify-content: center; height: 100vh; background: #e0f7fa; }
        .box { width: 100%; max-width: 380px; background: #fff; border-radius: 16px; padding: 28px 26px; box-shadow: 0 8px 32px rgba(0,131,143,0.15); }
        .logo-img { width: 100%; max-width: 180px; display: block; margin: 0 auto 14px; border-radius: 10px; }
        h1 { font-family: 'Rajdhani', sans-serif; font-size: 17px; font-weight: 700; color: #0D5A8E; text-align: center; margin-bottom: 4px; }
        .subtitle { font-size: 11px; color: #38A169; font-weight: 500; text-align: center; margin-bottom: 16px; }
        .alert-box { border-radius: 8px; padding: 6px 10px; font-size: 11px; margin-bottom: 12px; border-left: 3px solid; }
        .alert-error { background:#fde8e8; border-color:#e53935; color:#b71c1c; }
        .field { margin-bottom: 12px; }
        label { display: block; font-size: 11px; font-weight: 600; color: #2D3748; margin-bottom: 4px; }
        input[type="password"] { width: 100%; padding: 8px 10px; border: 1.5px solid #b2ebf2; border-radius: 10px; font-size: 13px; font-family: 'Outfit', sans-serif; color: #2D3748; background: #f7fdff; outline: none; }
        input:focus { border-color: #1B9AE4; background: #fff; }
        .btn { width: 100%; padding: 10px; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; cursor: pointer; margin-top: 6px; }
    </style>
</head>
<body>

<div class="box">
    <img class="logo-img" src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink" />
    <h1>{{ __('vendor.welcome_name', ['name' => $vendorName]) }}</h1>
    <p class="subtitle">{{ __('auth.vendor_set_password_subtitle') }}</p>
    <div style="background:#EFF6FF; border-radius:8px; padding:6px 10px; font-size:10.5px; color:#1565C0; font-weight:600; text-align:center; margin-bottom:12px;">
        {{ __('auth.vendor_login_id_label', ['email' => $vendorEmail]) }}
    </div>

    @if ($errors->any())
    <div class="alert-box alert-error">
        @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('vendor.set-password.post', $token) }}">
        @csrf
        <div class="field">
            <label for="password">{{ __('auth.new_password_label') }}</label>
            <input type="password" id="password" name="password" required autofocus />
        </div>
        <div class="field">
            <label for="password_confirmation">{{ __('auth.confirm_password_label') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required />
        </div>
        <button type="submit" class="btn">{{ __('auth.set_password_continue_button') }}</button>
    </form>
</div>

</body>
</html>
