@extends('layouts.dashboard')

@section('page-title', __('notification_preferences.page_title'))

@section('content')
{{-- NEW 8 Aug 2026 — GLADE Ecosystem Engagement, Phase 1 (Task #84). Lets
     an agent choose which Notice Board categories get proactively pushed
     to them, and through which channels — multiple choice on both, per
     Chris. Portal (the Notice Board itself) is always on and not shown
     as a choice. Telegram/LINE/WeChat/SMS are shown now (per Chris: pick
     once, never redo it) but marked "Coming soon" until built. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('notification_preferences.intro_note') }}</div>
        </div>
        <a href="{{ route('notice-board.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('notification_preferences.notice_board_link') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:5px;">{{ session('success') }}</div>
    @endif

    <div style="flex:1; min-height:0; background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:14px 16px; overflow-y:auto; overflow-x:hidden;">
        <form method="POST" action="{{ route('notification-preferences.update') }}">
            @csrf

            <div style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:8px;">{{ __('notification_preferences.categories_heading') }}</div>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
                @php
                $catLabels = ['IMPORTANT_UPDATE' => __('notice_board.category_important_update'), 'PROMOTION' => __('notice_board.category_promotion'), 'HOLIDAY_FESTIVE' => __('notice_board.category_holiday_festive_greeting'), 'CONTACT_INFO' => __('notice_board.category_contact_info'), 'GENERAL' => __('notice_board.category_general')];
                @endphp
                @foreach($catLabels as $key => $label)
                <label style="display:flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:20px; padding:6px 14px; font-size:11px; color:#374151; cursor:pointer;">
                    <input type="checkbox" name="categories[]" value="{{ $key }}" {{ in_array($key, $selectedCategories) ? 'checked' : '' }}>
                    {{ $label }}
                </label>
                @endforeach
            </div>

            <div style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:8px;">{{ __('notification_preferences.channels_heading') }}</div>
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:6px;">
                <span style="display:flex; align-items:center; gap:6px; background:#eef2f7; border:1px solid #e5e7eb; border-radius:20px; padding:6px 14px; font-size:11px; color:#6b7280;">
                    <input type="checkbox" checked disabled> {{ __('notification_preferences.portal_always_on_label') }}
                </span>
                @php $chLabels = ['EMAIL' => __('notification_preferences.channel_email'), 'WHATSAPP' => __('notification_preferences.channel_whatsapp'), 'SMS' => __('notification_preferences.channel_sms')]; @endphp
                @foreach($chLabels as $key => $label)
                <label style="display:flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:20px; padding:6px 14px; font-size:11px; color:#374151; cursor:pointer;">
                    <input type="checkbox" name="channels[]" value="{{ $key }}" {{ in_array($key, $selectedChannels) ? 'checked' : '' }}>
                    {{ $label }}
                </label>
                @endforeach
                @php $soonLabels = ['TELEGRAM' => __('notification_preferences.channel_telegram'), 'LINE' => __('notification_preferences.channel_line'), 'WECHAT' => __('notification_preferences.channel_wechat')]; @endphp
                @foreach($soonLabels as $key => $label)
                <label style="display:flex; align-items:center; gap:6px; background:#f8fafc; border:1px solid #e5e7eb; border-radius:20px; padding:6px 14px; font-size:11px; color:#374151; cursor:pointer;">
                    <input type="checkbox" name="channels[]" value="{{ $key }}" {{ in_array($key, $selectedChannels) ? 'checked' : '' }}>
                    {{ $label }}
                    <span style="background:#fff8e1; color:#8d6e00; border-radius:10px; padding:1px 6px; font-size:8.5px; font-weight:700;">{{ __('notification_preferences.coming_soon_badge') }}</span>
                </label>
                @endforeach
            </div>
            <div style="font-size:9px; color:#9ca3af; margin-bottom:18px;">{{ __('notification_preferences.coming_soon_safe_note') }}</div>

            <div style="font-size:11.5px; font-weight:700; color:#374151; margin-bottom:8px;">{{ __('notification_preferences.max_pushes_per_week_heading') }}</div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:20px;">
                <input type="number" name="frequency_cap_per_week" min="1" max="50" value="{{ $frequencyCap }}" placeholder="{{ __('notification_preferences.no_limit_placeholder') }}" style="width:100px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box;">
                <span style="font-size:10px; color:#9ca3af;">{{ __('notification_preferences.no_limit_hint_note') }}</span>
            </div>

            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('notification_preferences.save_preferences_button') }}</button>
        </form>
    </div>

</div>
@endsection
