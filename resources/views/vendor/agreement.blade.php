@extends('layouts.vendor')

@section('page-title', __('vendor.registration_agreement_title'))

@section('content')

{{-- NEW 14 Aug 2026 — per Chris: "build OTP-click flow and complete the
     entire approval process." Digital acceptance of the GLADE Vendor
     Registration Activation Agreement, per that document's own Clause
     10: OTP emailed to the vendor's registered address, typed in here,
     then an affirmative "Accept Agreement" click — same legal effect as
     a handwritten signature under Malaysia's Electronic Commerce Act
     2006. Once accepted, this screen shows the Acceptance Certificate
     instead (Clause 10.4's evidence trail) and the action form is gone
     for good — one vendor, one acceptance. --}}

<div style="height:100%; display:flex; flex-direction:column; padding:14px 18px; box-sizing:border-box; overflow:hidden;">

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:8px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #e0f2fe; border-radius:10px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; padding:10px 14px; border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13px; font-weight:700; color:#0D5A8E;">{{ __('vendor.agreement_heading') }}</div>
            <div style="font-size:9.5px; color:#9ca3af; margin-top:1px;">{{ __('vendor.agreement_no_version', ['num' => $acceptance->agreement_number, 'ver' => $acceptance->agreement_version]) }}</div>
        </div>

        <div style="flex:1; min-height:0; overflow-y:auto; padding:14px;">

            @if($acceptance->accepted_at)
            {{-- ===================== ACCEPTANCE CERTIFICATE ===================== --}}
            <div style="background:#f0fdf4; border:1px solid #a7d7b5; border-radius:8px; padding:14px 16px; max-width:600px; margin:0 auto;">
                <div style="font-size:13px; font-weight:700; color:#166534; margin-bottom:10px;">{{ __('vendor.agreement_accepted_heading') }}</div>
                <div style="font-size:10.5px; color:#166534; line-height:2;">
                    <div>{{ __('vendor.cert_agreement_id_label') }} <strong>{{ $acceptance->acceptance_id }}</strong> / <strong>{{ $acceptance->agreement_number }}</strong></div>
                    <div>{{ __('vendor.cert_vendor_label') }} <strong>{{ $vendor->vendor_name }}</strong></div>
                    <div>{{ __('vendor.cert_version_label') }} <strong>{{ $acceptance->agreement_version }}</strong></div>
                    <div>{{ __('vendor.cert_accepted_by_label') }} <strong>{{ $acceptance->accepted_by_name }}</strong> ({{ $acceptance->accepted_by_email }})</div>
                    <div>{{ __('vendor.cert_accepted_datetime_label') }} <strong>{{ \Carbon\Carbon::parse($acceptance->accepted_at)->format('d M Y, h:ia') }} {{ __('vendor.cert_myt_suffix') }}</strong></div>
                    <div>{{ __('vendor.cert_method_label') }} <strong>{{ __('vendor.cert_method_email_otp') }}</strong></div>
                    <div>{{ __('vendor.cert_otp_verified_label') }} <strong>{{ \Carbon\Carbon::parse($acceptance->otp_verified_at)->format('d M Y, h:ia') }}</strong></div>
                    <div>{{ __('vendor.cert_ip_label') }} <strong>{{ $acceptance->accepted_ip }}</strong></div>
                    <div>{{ __('vendor.cert_device_label') }} <strong style="word-break:break-word;">{{ $acceptance->accepted_device }}</strong></div>
                    <div>{{ __('vendor.cert_hash_label') }} <strong style="font-size:8.5px; word-break:break-all;">{{ $acceptance->document_hash }}</strong></div>
                </div>
                <div style="margin-top:12px;">
                    <a href="{{ route('vendor.agreement.file') }}" target="_blank" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600;">{{ __('vendor.view_signed_agreement_button') }}</a>
                </div>
            </div>

            @else
            {{-- ===================== REVIEW + OTP ACCEPTANCE ===================== --}}
            <div style="max-width:600px; margin:0 auto;">
                <p style="font-size:11px; color:#374151; line-height:1.6;">{{ __('vendor.review_before_accept_note') }}</p>

                <div style="margin-bottom:12px;">
                    <a href="{{ route('vendor.agreement.file') }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:600;">{{ __('vendor.view_full_agreement_button') }}</a>
                </div>

                <table style="width:100%; border-collapse:collapse; margin-bottom:14px; font-size:10.5px;">
                    <tr style="background:#f0f9ff;">
                        <td style="border:1px solid #cbd5e1; padding:6px 8px; font-weight:700;">{{ __('vendor.col_fee_item') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px; font-weight:700;">{{ __('vendor.col_amount') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px; font-weight:700;">{{ __('vendor.col_frequency') }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">{{ __('vendor.activation_fee_label') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ACTIVATION_FEE, 2) }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">{{ __('vendor.frequency_onetime') }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">{{ __('vendor.maintenance_fee_label') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ANNUAL_FEE, 2) }}</td>
                        <td style="border:1px solid #cbd5e1; padding:6px 8px;">{{ __('vendor.frequency_annual') }}</td>
                    </tr>
                </table>

                <div style="background:#f9fafb; border-radius:8px; padding:12px 14px;">
                    <div style="font-size:11px; font-weight:700; color:#263238; margin-bottom:8px;">{{ __('vendor.step1_heading') }}</div>
                    <form method="POST" action="{{ route('vendor.agreement.send-otp') }}" style="margin-bottom:14px;">
                        @csrf
                        <button type="submit" style="background:#0D5A8E; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('vendor.send_code_button', ['email' => $vendor->pic_email ?: $vendor->vendor_email]) }}</button>
                    </form>

                    <div style="font-size:11px; font-weight:700; color:#263238; margin-bottom:8px;">{{ __('vendor.step2_heading') }}</div>
                    <form method="POST" action="{{ route('vendor.agreement.accept') }}">
                        @csrf
                        <div style="display:flex; gap:8px; align-items:center; margin-bottom:10px;">
                            <input type="text" name="otp_code" required maxlength="6" pattern="[0-9]{6}" placeholder="{{ __('vendor.otp_placeholder') }}" style="width:140px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:14px; letter-spacing:3px; text-align:center;">
                        </div>
                        <label style="display:flex; align-items:flex-start; gap:6px; font-size:10px; color:#4b5563; margin-bottom:10px; cursor:pointer;">
                            <input type="checkbox" name="confirm" value="1" required style="margin-top:2px;">
                            <span>{{ __('vendor.agree_terms_label') }}</span>
                        </label>
                        <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('vendor.accept_agreement_button') }}</button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@endsection
