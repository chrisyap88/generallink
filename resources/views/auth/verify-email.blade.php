<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('auth.verify_email_page_title') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center"
      style="background: linear-gradient(135deg, #e0f7fa 0%, #b2ebf2 50%, #e0f2f1 100%);">

    <div class="w-full max-w-lg mx-4 bg-white rounded-3xl shadow-2xl overflow-hidden">

        <div class="p-8 text-center"
             style="background: linear-gradient(160deg, #e0f7fa 0%, #b2ebf2 100%);">
            <img src="{{ asset('images/generallink-logo.jpg') }}"
                 alt="GeneralLink Logo"
                 class="w-48 mx-auto rounded-2xl shadow-lg">
        </div>

        <div class="p-8 text-center">

            <div class="w-20 h-20 mx-auto mb-6 rounded-full flex items-center justify-center"
                 style="background: #e0f7fa;">
                <span style="font-size: 40px;">📧</span>
            </div>

            <h2 class="text-2xl font-bold mb-2" style="color: #0D5A8E;">
                {{ __('auth.check_your_email_heading') }}
            </h2>

            <p class="text-sm mb-2" style="color: #38A169; font-weight: 600;">
                {{ __('auth.verify_email_welcome', ['name' => auth()->user()->name]) }}
            </p>

            <p class="text-sm mb-6" style="color: #38A169; font-weight: 600;">
                {{ __('auth.registration_submitted_note') }}
            </p>

            <div class="p-4 rounded-xl mb-6 text-left"
                 style="background: #f0fbff; border-left: 4px solid #1B9AE4;">
                <p class="text-sm mb-3" style="color: #0D5A8E;">
                    {{ __('auth.verification_sent_to_label') }}
                </p>
                <p class="text-sm font-bold mb-3" style="color: #1B9AE4;">
                    {{ auth()->user()->email }}
                </p>
                <p class="text-sm font-semibold mb-3" style="color: #0D5A8E;">
                    {{ __('auth.follow_steps_label') }}
                </p>
                <p class="text-sm" style="color: #444; line-height: 2;">
                    {!! __('auth.verify_email_steps') !!}
                </p>
            </div>

            <div class="p-4 rounded-xl mb-6"
                 style="background: #fff8e8; border-left: 4px solid #D97706;">
                <p class="text-sm" style="color: #D97706;">
                    {!! __('auth.verify_link_expiry_note') !!}
                </p>
            </div>

            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit"
                        class="w-full py-3 rounded-xl text-white font-bold text-sm tracking-wide mb-4"
                        style="background: linear-gradient(135deg, #1B9AE4, #0D5A8E);">
                    {{ __('auth.resend_verification_button') }}
                </button>
            </form>

            <a href="{{ route('login') }}"
               class="text-sm hover:underline"
               style="color: #1B9AE4;">
                {{ __('auth.back_to_login_link') }}
            </a>

        </div>

        <div class="p-4 text-center" style="background: #f8f9fa; border-top: 1px solid #e0e0e0;">
            <p class="text-xs" style="color: #888;">
                {{ __('auth.footer') }}
            </p>
        </div>

    </div>

@include('partials.ai-assistant-widget', ['guestMode' => true])
</body>
</html>