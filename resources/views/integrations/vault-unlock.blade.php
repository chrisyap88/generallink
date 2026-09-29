@extends('layouts.dashboard')

@section('page-title', __('integrations.unlock_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; align-items:center; justify-content:center;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:22px; width:100%; max-width:400px;">
        <div style="font-size:10.5px; color:#6b7280; margin-bottom:14px; line-height:1.5;">{{ __('integrations.unlock_intro_note') }}</div>

        @if($errors->any())
        <div style="background:#fde8e8; color:#b71c1c; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:10px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('integrations.vault.unlock.post') }}">
            @csrf
            <input type="password" name="hub_password" autocomplete="current-password" placeholder="{{ __('integrations.hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; outline:none; box-sizing:border-box; margin-bottom:12px;">
            <button type="submit" style="width:100%; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:9px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('integrations.unlock_button') }}</button>
        </form>

        <div style="text-align:center; margin-top:12px; font-size:9.5px; color:#9ca3af;">
            {{ __('integrations.forgot_it_note') }}
        </div>
    </div>

</div>
@endsection
