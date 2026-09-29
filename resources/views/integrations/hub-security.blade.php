@extends('layouts.dashboard')

@section('page-title', __('integrations.security_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; align-items:center; justify-content:center; overflow-y:auto;">

    <div style="width:100%; max-width:460px;">

        @if(session('success'))
        <div style="background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:10px;">{{ session('success') }}</div>
        @endif

        @if(!$hasVault)
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; font-size:11px; color:#374151; line-height:1.6;">
            {{ __('integrations.no_vault_note') }}
            <a href="{{ route('integrations.vault.setup') }}" style="color:#1565C0; font-weight:600;">{{ __('integrations.set_it_up_link') }}</a>{{ __('integrations.before_connect_suffix') }}
        </div>
        @else

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; margin-bottom:12px;">
            <div style="font-size:12px; font-weight:700; color:#263238; margin-bottom:3px;">{{ __('integrations.change_password_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px;">{{ __('integrations.change_password_note') }}</div>
            @error('current_hub_password') <div style="color:#dc2626; font-size:10.5px; margin-bottom:8px;">{{ $message }}</div> @enderror
            <form method="POST" action="{{ route('integrations.security.change') }}">
                @csrf
                <input type="password" name="current_hub_password" placeholder="{{ __('integrations.current_hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:8px;">
                <input type="password" name="new_hub_password" placeholder="{{ __('integrations.new_hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:4px;">
                <div style="font-size:9.5px; color:#9ca3af; margin-bottom:8px;">{{ __('integrations.password_requirements_note') }}</div>
                <input type="password" name="new_hub_password_confirmation" placeholder="{{ __('integrations.confirm_new_hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:10px;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('integrations.change_password_button') }}</button>
            </form>
        </div>

        <div style="background:#fff; border:1px solid #f3d4d4; border-radius:8px; padding:16px;">
            <div style="font-size:12px; font-weight:700; color:#b71c1c; margin-bottom:3px;">{{ __('integrations.forgot_password_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px;">{{ __('integrations.forgot_password_note') }}</div>
            @error('login_password') <div style="color:#dc2626; font-size:10.5px; margin-bottom:8px;">{{ $message }}</div> @enderror
            <form method="POST" action="{{ route('integrations.security.reset') }}" onsubmit="return confirm('{{ __('integrations.reset_confirm_js') }}');">
                @csrf
                <input type="password" name="login_password" placeholder="{{ __('integrations.login_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:8px;">
                <input type="password" name="new_hub_password" placeholder="{{ __('integrations.new_hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:4px;">
                <div style="font-size:9.5px; color:#9ca3af; margin-bottom:8px;">{{ __('integrations.password_requirements_note') }}</div>
                <input type="password" name="new_hub_password_confirmation" placeholder="{{ __('integrations.confirm_new_hub_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11.5px; outline:none; box-sizing:border-box; margin-bottom:10px;">
                <button type="submit" style="background:#fff; color:#b71c1c; border:1px solid #b71c1c; border-radius:6px; padding:7px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('integrations.reset_erase_button') }}</button>
            </form>
        </div>
        @endif
    </div>

</div>
@endsection
