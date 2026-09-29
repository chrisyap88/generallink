@extends('layouts.dashboard')

@section('page-title', __('growth.channel_connections_title'))

@section('content')

{{-- NEW 25 Jul 2026 (task #209) — Growth & Outreach Center. Tick which
     social/messaging channels you plan to use. Ticking does NOT send
     anything by itself — Broadcast Campaigns will only actually be able
     to send through a channel once it's enabled here AND you've filled
     in real API details from a provider (Meta, Twilio, 360dialog, etc.)
     once you subscribe. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
        <div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.channels_subtitle') }}</div>
        </div>
        @include('partials.feature-video-widget', ['featureKey' => 'CHANNEL_CONNECTIONS'])
    </div>

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:6px;">✅ {{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.growth.channels.update') }}" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <div style="flex:1; min-height:0; overflow-y:auto; display:grid; grid-template-columns:1fr 1fr; gap:10px; padding-bottom:8px;">
            @foreach($channels as $c)
            <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-size:12px; font-weight:700; color:#111827; cursor:pointer;">
                        <input type="checkbox" name="channels[{{ $c->channel_id }}][is_enabled]" value="1" {{ $c->is_enabled ? 'checked' : '' }} style="width:15px; height:15px;">
                        {{ $c->channel_name }}
                    </label>
                    @if($c->is_connected)
                    <span style="background:#f0fdf4; color:#166534; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.connected') }}</span>
                    @else
                    <span style="background:#f3f4f6; color:#6b7280; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:20px;">{{ __('growth.not_connected') }}</span>
                    @endif
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; margin-bottom:6px;">
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.provider_label') }}</div>
                        <input type="text" name="channels[{{ $c->channel_id }}][provider_name]" value="{{ $c->provider_name }}" placeholder="{{ __('growth.fill_in_once_subscribed') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.account_phone_id_label') }}</div>
                        <input type="text" name="channels[{{ $c->channel_id }}][account_identifier]" value="{{ $c->account_identifier }}" placeholder="{{ __('growth.fill_in_once_subscribed') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('growth.api_key_label', ['saved_note' => $c->has_api_key ? __('growth.key_already_saved_note') : '']) }}</div>
                    <input type="password" name="channels[{{ $c->channel_id }}][api_key]" placeholder="{{ $c->has_api_key ? __('growth.key_saved_placeholder') : __('growth.fill_in_once_subscribed') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:10.5px; box-sizing:border-box;" autocomplete="off">
                </div>
            </div>
            @endforeach
        </div>

        <div style="flex-shrink:0; padding-top:8px; display:flex; align-items:center; justify-content:space-between;">
            <a href="{{ route('admin.dashboard') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; display:inline-block;">{{ __('growth.prev') }}</a>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 22px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('growth.save_channel_settings_button') }}</button>
        </div>
    </form>

</div>
@endsection
