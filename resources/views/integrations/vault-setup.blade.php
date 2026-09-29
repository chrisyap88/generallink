@extends('layouts.dashboard')

@section('page-title', __('integrations.setup_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; align-items:center; justify-content:center;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:22px; width:100%; max-width:440px;">
        <div style="font-size:10.5px; color:#6b7280; margin-bottom:14px; line-height:1.5;">{{ __('integrations.setup_intro_note') }}</div>

        <div style="background:#fff8e1; color:#8d6e00; border-radius:6px; padding:8px 10px; font-size:10px; margin-bottom:14px; line-height:1.5;">{{ __('integrations.setup_warning_note') }}</div>

        @if($errors->any())
        <div style="background:#fde8e8; color:#b71c1c; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:10px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('integrations.vault.setup.post') }}">
            @csrf
            <label style="font-size:10.5px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('integrations.hub_password_label') }}</label>
            <input type="password" name="hub_password" autocomplete="new-password" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; outline:none; box-sizing:border-box; margin-bottom:4px;">
            <div style="font-size:9.5px; color:#9ca3af; margin-bottom:10px;">{{ __('integrations.password_requirements_note_symbols') }}</div>

            <label style="font-size:10.5px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('integrations.confirm_hub_password_label') }}</label>
            <input type="password" name="hub_password_confirmation" autocomplete="new-password" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; font-size:12px; outline:none; box-sizing:border-box; margin-bottom:16px;">

            <button type="submit" style="width:100%; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:9px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('integrations.setup_unlock_button') }}</button>
        </form>
    </div>

</div>
@endsection
