@extends('layouts.dashboard')

@section('page-title', __('shared.submit_by_email_title'))

@section('content')
<div style="padding:16px; max-width:640px;">
    <div style="background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:18px 22px;">
        <div style="font-size:13px; font-weight:700; color:#374151; margin-bottom:10px;">{{ __('shared.submit_transaction_by_email_heading') }}</div>
        <div style="font-size:11.5px; color:#374151; line-height:1.6; margin-bottom:14px;">
            {!! __('shared.submit_by_email_intro', ['email' => '<strong>admin@generallink.my</strong>', 'button' => e(__('shared.read_document_automatically_button_label'))]) !!}
        </div>
        <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('shared.personal_key_label') }}</div>
        <div style="background:#F9FAFB; border:1px solid #E2E8F0; border-radius:6px; padding:10px 12px; font-family:'Courier New',monospace; font-size:11px; word-break:break-all; margin-bottom:6px;">KEY:{{ $key }}</div>
        <div style="font-size:10px; color:#9ca3af; margin-bottom:16px;">{{ __('shared.personal_key_note') }}</div>

        <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:6px;">{{ __('shared.example_email_label') }}</div>
        <div style="background:#F9FAFB; border:1px solid #E2E8F0; border-radius:6px; padding:10px 12px; font-size:11px; color:#374151; line-height:1.7; margin-bottom:14px;">
            <strong>{{ __('shared.example_email_to_label') }}</strong> admin@generallink.my<br>
            <strong>{{ __('shared.example_email_subject_label') }}</strong> {{ __('shared.example_email_subject_value', ['key' => $key]) }}<br>
            <strong>{{ __('shared.example_email_attachment_label') }}</strong> {{ __('shared.example_email_attachment_note') }}
        </div>

        <div style="background:#eff6ff; border-left:3px solid #1565C0; border-radius:6px; padding:8px 12px; font-size:10.5px; color:#1e40af;">
            {{ __('shared.email_flagged_note') }}
        </div>
    </div>
</div>
@endsection
