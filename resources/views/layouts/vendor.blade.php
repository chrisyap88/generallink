<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>GeneralLink {{ __('vendor.vendor_portal_title') }} – @yield('page-title', __('vendor.default_page_title'))</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Rajdhani:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100vh; width: 100vw; overflow: hidden; font-family: 'Outfit', sans-serif; background: #f4f9fb; }
        .vp-topbar { height: 50px; background: linear-gradient(90deg, #1B9AE4 0%, #0D5A8E 100%); display: flex; align-items: center; justify-content: space-between; padding: 0 18px; }
        .vp-brand { display: flex; align-items: center; gap: 8px; color: #fff; font-family: 'Rajdhani', sans-serif; font-weight: 700; font-size: 15px; }
        .vp-brand img { height: 26px; border-radius: 4px; }
        .vp-nav { display: flex; align-items: center; gap: 4px; }
        .vp-nav a { color: rgba(255,255,255,.85); text-decoration: none; font-size: 11.5px; font-weight: 600; padding: 6px 14px; border-radius: 20px; }
        .vp-nav a.active, .vp-nav a:hover { background: rgba(255,255,255,.18); color: #fff; }
        .vp-nav form button { background: rgba(0,0,0,.15); color: #fff; border: none; border-radius: 20px; padding: 6px 14px; font-size: 11.5px; font-weight: 600; cursor: pointer; font-family: 'Outfit', sans-serif; }
        .vp-body { height: calc(100vh - 50px); overflow: hidden; }
    </style>
</head>
<body>

<div class="vp-topbar">
    <div class="vp-brand">
        <img src="{{ asset('images/generallink-logo.jpeg') }}" alt="GeneralLink" />
        {{ __('vendor.vendor_portal_title') }}
    </div>
    <div class="vp-nav">
        {{-- CHANGED 13 Aug 2026 — per Chris's restricted-access design: a
             RESTRICTED vendor (documents verified, awaiting final
             approval) only ever sees My Application here — every other
             link would just bounce them back via RestrictVendorPortalAccess,
             so showing them at all would be misleading. Full nav returns
             automatically once Admin's approval flips them to ACTIVE. --}}
        @if(auth('vendor')->user()?->login_status === 'RESTRICTED')
        <a href="{{ route('vendor.application-status') }}" class="{{ request()->routeIs('vendor.application-status*') ? 'active' : '' }}">{{ __('vendor.my_application_title') }}</a>
        @else
        {{-- REMOVED 8 Aug 2026 per Chris: "remove everything ... i want to
             redo and revamp" — Submit Offer / My Submissions removed along
             with the rest of the Offer Request feature. Dashboard link
             kept — Vendor Portal login itself is untouched. --}}
        <a href="{{ route('vendor.portal.index') }}" class="{{ request()->routeIs('vendor.portal.index') ? 'active' : '' }}">{{ __('vendor.dashboard_label') }}</a>
        <a href="{{ route('vendor.profile') }}" class="{{ request()->routeIs('vendor.profile') ? 'active' : '' }}">{{ __('vendor.my_profile_title') }}</a>
        {{-- NEW 10 Aug 2026 — per Chris: "all agents including vendor is
             allow to send attachment...marketing documents...to admin." --}}
        <a href="{{ route('vendor.content-submission.create') }}" class="{{ request()->routeIs('vendor.content-submission.create') ? 'active' : '' }}">{{ __('vendor.submit_content_nav') }}</a>
        <a href="{{ route('vendor.content-submission.index') }}" class="{{ request()->routeIs('vendor.content-submission.index') ? 'active' : '' }}">{{ __('vendor.my_submissions_title') }}</a>
        {{-- NEW 12 Aug 2026 — proper redo of vendor rebate program applications, per Chris. --}}
        <a href="{{ route('vendor.rebate-applications.index') }}" class="{{ request()->routeIs('vendor.rebate-applications.*') ? 'active' : '' }}">{{ __('vendor.rebate_applications_nav') }}</a>
        {{-- NEW 14 Aug 2026 — per Chris: "build OTP-click flow and
             complete the entire approval process." --}}
        <a href="{{ route('vendor.agreement') }}" class="{{ request()->routeIs('vendor.agreement*') ? 'active' : '' }}">{{ __('vendor.registration_agreement_title') }}</a>
        @endif
        <form method="POST" action="{{ route('vendor.logout') }}">@csrf<button type="submit">{{ __('vendor.logout_button') }}</button></form>
    </div>
</div>

<div class="vp-body">
    @if(session('success'))
    <div style="background:#e8f5e9; color:#1b5e20; border-left:3px solid #38A169; padding:6px 18px; font-size:11px;">{{ session('success') }}</div>
    @endif
    @if(session('info'))
    <div style="background:#e0f2fe; color:#0D5A8E; border-left:3px solid #1B9AE4; padding:6px 18px; font-size:11px;">{{ session('info') }}</div>
    @endif
    @if ($errors->any())
    <div style="background:#fde8e8; color:#b71c1c; border-left:3px solid #e53935; padding:6px 18px; font-size:11px;">
        @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif
    @yield('content')
</div>

@include('partials.carolyn-write-helper-script')
@stack('scripts')
</body>
</html>
