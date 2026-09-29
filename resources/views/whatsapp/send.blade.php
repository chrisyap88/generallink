@extends('layouts.dashboard')

@section('page-title', __('whatsapp.page_title'))

@section('content')
{{-- REBUILT 6 Aug 2026 (v3, compact) — per Chris: previous versions of this
     page kept getting cut off on his shorter browser window. Every element
     here is now deliberately smaller/tighter than other pages (less
     padding, less line-height, one-line hints instead of paragraphs, PIN
     register box collapsed to a single inline row) specifically so the
     whole page — including both Send Message and the one-time Register
     box — fits with zero scrolling even on a short window. Don't add back
     multi-line help text or generous spacing here without checking it
     still fits without scroll. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 14px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('integrations.category', 'communication') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600; white-space:nowrap;">{{ __('whatsapp.manage_connection_link') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:9.5px; margin-bottom:4px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:9.5px; margin-bottom:4px; line-height:1.5;">{{ session('error') }}</div>
    @endif
    @error('to')
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:9.5px; margin-bottom:4px;">{{ $message }}</div>
    @enderror
    @error('message')
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:9.5px; margin-bottom:4px;">{{ $message }}</div>
    @enderror
    @error('pin')
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:9.5px; margin-bottom:4px;">{{ $message }}</div>
    @enderror

    <div style="flex:1; min-height:0; background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:12px 14px; overflow-y:auto; overflow-x:hidden; display:flex; flex-direction:column;">

        @if(!$connected)
        <div style="display:flex; flex-direction:column; align-items:flex-start; gap:8px; max-width:520px;">
            <span style="background:#eef2f7; color:#374151; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:600;">{{ __('whatsapp.not_connected_badge') }}</span>
            <div style="font-size:11.5px; color:#374151; line-height:1.6;">
                {!! __('whatsapp.not_connected_note', ['hub' => '<strong>' . __('whatsapp.integration_hub_path') . '</strong>', 'test_button' => '<strong>' . __('whatsapp.test_connection_button_label') . '</strong>']) !!}
            </div>
            <a href="{{ route('integrations.category', 'communication') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600;">{{ __('whatsapp.connect_whatsapp_button') }}</a>
        </div>
        @else
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px; flex-shrink:0;">
            <span style="background:#e8f5e9; color:#1b5e20; border-radius:20px; padding:2px 10px; font-size:9.5px; font-weight:700;">{{ __('whatsapp.connected_badge') }}</span>
            @if($lastTestedAt)
            <span style="font-size:9px; color:#9ca3af;">{{ __('whatsapp.last_tested_suffix', ['time' => \Carbon\Carbon::parse($lastTestedAt)->diffForHumans()]) }}</span>
            @endif
        </div>

        <div style="display:flex; gap:14px; align-items:flex-start; flex-wrap:nowrap;">
            <form method="POST" action="{{ route('whatsapp.send') }}" style="display:flex; flex-direction:column; gap:7px; width:320px; max-width:56%; flex-shrink:0;">
                @csrf
                <div>
                    <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('whatsapp.field_recipient_phone_number') }}</label>
                    <input type="text" name="to" value="{{ old('to') }}" placeholder="{{ __('whatsapp.recipient_phone_placeholder') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:11.5px; outline:none; box-sizing:border-box;">
                    <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('whatsapp.recipient_phone_hint') }}</div>
                </div>
                <div>
                    <label style="display:block; font-size:10.5px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('whatsapp.field_message') }}</label>
                    <textarea name="message" id="waMessageBody" rows="2" maxlength="1000" required placeholder="{{ __('whatsapp.message_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:11.5px; outline:none; resize:none; font-family:inherit; box-sizing:border-box;">{{ old('message') }}</textarea>
                    @include('partials.carolyn-write-helper', [
                        'uid' => 'wa',
                        'bodyFieldId' => 'waMessageBody',
                        'contentType' => 'whatsapp_message',
                        'assistUrl' => route('ai-write-assist'),
                    ])
                </div>
                <button type="submit" style="align-self:flex-start; flex-shrink:0; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('whatsapp.send_message_button') }}</button>
            </form>

            {{-- One-time registration Meta requires before a WhatsApp number
                 can send at all (Meta error 133010 otherwise). Collapsed to
                 one inline row (input + button) to keep this compact —
                 explanation is a single short line, not a paragraph. --}}
            <div style="width:230px; max-width:44%; flex-shrink:0; background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:9px 11px;">
                <div style="font-size:10.5px; font-weight:700; color:#374151; margin-bottom:3px;">{{ __('whatsapp.one_time_register_title') }}</div>
                <div style="font-size:9px; color:#6b7280; margin-bottom:6px; line-height:1.4;">{{ __('whatsapp.one_time_register_note') }}</div>
                <form method="POST" action="{{ route('whatsapp.register') }}" style="display:flex; gap:6px;">
                    @csrf
                    <input type="text" name="pin" inputmode="numeric" maxlength="6" placeholder="123456" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; outline:none; box-sizing:border-box;">
                    <button type="submit" style="flex-shrink:0; background:#00838f; color:#fff; border:none; border-radius:6px; padding:6px 12px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('whatsapp.register_button') }}</button>
                </form>
            </div>
        </div>
        @endif
    </div>

</div>
@endsection
