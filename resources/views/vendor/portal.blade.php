@extends('layouts.vendor')

@section('page-title', __('vendor.dashboard_title'))

@section('content')
{{-- REBUILT 8 Aug 2026 — Vendor Management Phase 1 (spec Section 12,
     Vendor Dashboard). This used to be a "being redesigned" placeholder
     after the Offer Request feature was removed. Now shows only what
     Phase 1 actually has data for: account/verification status and a
     link to Profile. Deliberately no Campaign/Rebate/Communication cards
     — those are later phases and would be fake, non-working links if
     shown now, which is exactly what confused Chris about the old build. --}}
<div style="height:100%; display:flex; flex-direction:column; padding:20px 28px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:14px;">
        <h1 style="font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; color:#0D5A8E; margin:0 0 2px;">{{ __('vendor.welcome_name', ['name' => $vendor->vendor_name]) }}</h1>
        <p style="font-size:11px; color:#718096; margin:0;">{{ __('vendor.vendor_code_label') }} <strong style="color:#374151;">{{ $vendor->vendor_code }}</strong></p>
    </div>

    <div style="flex:1; min-height:0; display:grid; grid-template-columns:repeat(3,1fr); gap:14px; overflow:hidden;">

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:6px;">
            <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.3px;">{{ __('vendor.account_status_label') }}</div>
            @php
                $statusColor = match($vendor->login_status) { 'ACTIVE' => '#2e7d32', 'AWAITING_PASSWORD' => '#f57c00', 'PENDING' => '#f57c00', 'RESTRICTED' => '#6D28D9', 'REJECTED' => '#e53935', default => '#6b7280' };
                $statusLabel = match($vendor->login_status) { 'ACTIVE' => __('vendor.login_status_active'), 'AWAITING_PASSWORD' => __('vendor.login_status_awaiting_password'), 'PENDING' => __('vendor.login_status_pending'), 'RESTRICTED' => __('vendor.login_status_restricted'), 'REJECTED' => __('vendor.login_status_rejected'), default => $vendor->login_status };
            @endphp
            <div style="font-size:15px; font-weight:700; color:{{ $statusColor }};">{{ $statusLabel }}</div>
            <div style="font-size:9.5px; color:#9ca3af; line-height:1.4;">
                @if($vendor->login_status === 'ACTIVE')
                    {{ __('vendor.account_active_note') }}
                @else
                    {{ __('vendor.contact_admin_note') }}
                @endif
            </div>
        </div>

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:6px;">
            <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.3px;">{{ __('vendor.business_status_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:{{ $vendor->is_active ? '#2e7d32' : '#e53935' }};">{{ $vendor->is_active ? __('growth.status_active') : __('growth.status_inactive') }}</div>
            <div style="font-size:9.5px; color:#9ca3af; line-height:1.4;">
                {{ \App\Http\Controllers\Admin\VendorController::INDUSTRIES[$vendor->industry] ?? $vendor->industry }} &middot; {{ \App\Models\Vendor::TYPES[$vendor->vendor_type] ?? __('vendor.type_not_set') }}
            </div>
        </div>

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:14px; display:flex; flex-direction:column; gap:6px;">
            <div style="font-size:10px; color:#9ca3af; text-transform:uppercase; letter-spacing:.3px;">{{ __('vendor.verification_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:#2e7d32;">{{ __('vendor.documents_verified') }}</div>
            <div style="font-size:9.5px; color:#9ca3af; line-height:1.4;">{{ __('vendor.ssm_verified_note') }}</div>
        </div>

    </div>

    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:14px;">
        <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</span>
        <a href="{{ route('vendor.profile') }}" style="font-size:10px; color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('vendor.view_profile_link') }}</a>
        <a href="{{ route('vendor.profile') }}" style="background:#1B9AE4; color:#fff; text-decoration:none; border-radius:20px; padding:5px 16px; font-size:10.5px; font-weight:600;">{{ __('network.next') }}</a>
    </div>
</div>
@endsection
