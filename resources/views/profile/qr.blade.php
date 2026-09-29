@extends('layouts.dashboard')
@section('title', __('profile.my_qr_code_title'))
@section('page-title', __('profile.my_referral_qr_code_page_title'))

@section('content')
<div style="max-width:480px;margin:0 auto;text-align:center">
    <div class="card">
        <div style="margin-bottom:16px">
            <div style="font-size:15px;font-weight:600;color:#1A202C;margin-bottom:4px">{{ $agent->full_name }}</div>
            <div style="font-size:13px;color:#718096">{{ $agent->member_code ?? $agent->agent_code }}</div>
        </div>

        {{-- QR code rendered using Google Charts API --}}
        <div style="display:inline-block;padding:16px;background:#fff;border:1px solid #E2E8F0;border-radius:12px;margin-bottom:16px">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($qrUrl) }}"
                 alt="{{ __('profile.referral_qr_code_alt') }}" width="200" height="200" style="display:block">
        </div>

        <div style="background:#F7FAFC;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#4A5568;word-break:break-all">
            {{ $qrUrl }}
        </div>

        <div style="display:flex;gap:10px;justify-content:center">
            <button onclick="window.print()"
                style="background:#0D5A8E;color:#fff;padding:8px 18px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                <i class="ti ti-printer"></i> {{ __('profile.print_button') }}
            </button>
            <a href="{{ route('profile.index') }}"
                style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;padding:8px 16px;border-radius:8px;font-size:13px;text-decoration:none">
                <i class="ti ti-arrow-left"></i> {{ __('masterfile.back') }}
            </a>
        </div>

        <p style="font-size:12px;color:#A0AEC0;margin-top:14px">
            {{ __('profile.share_qr_code_note') }}
        </p>
    </div>
</div>
@endsection
