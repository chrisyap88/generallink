@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.compliance_log_title'))

@section('content')

{{-- NEW 14 Aug 2026 — full Clause 10.4 evidence record for one vendor,
     plus the complete append-only OTP send/verify history behind it
     (vendor_agreement_otp_log). The evidence block is fixed-height; the
     log table gets its own internal scroll — same established exception
     already used for SSM Documents / Amendment Log elsewhere, since a
     vendor who requested the code several times will have more rows
     than fit on one screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        <div style="flex-shrink:0; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_vendors.acceptance_evidence_heading', ['name' => $acceptance->vendor_name]) }}</div>
            <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('vendor.agreement_no_version', ['num' => $acceptance->agreement_number, 'ver' => $acceptance->agreement_version]) }}</div>
        </div>

        <div style="flex-shrink:0; background:#f0fdf4; border:1px solid #a7d7b5; border-radius:8px; padding:10px 12px; margin-bottom:10px;">
            <div style="font-size:10.5px; color:#166534; line-height:1.9;">
                <div>{{ __('vendor.cert_accepted_by_label') }} <strong>{{ $acceptance->accepted_by_name }}</strong> ({{ $acceptance->accepted_by_email }})</div>
                <div>{{ __('vendor.cert_accepted_datetime_label') }} <strong>{{ \Carbon\Carbon::parse($acceptance->accepted_at)->format('d M Y, h:ia') }} {{ __('vendor.cert_myt_suffix') }}</strong></div>
                <div>{{ __('vendor.cert_otp_verified_label') }} <strong>{{ \Carbon\Carbon::parse($acceptance->otp_verified_at)->format('d M Y, h:ia') }}</strong></div>
                <div>{{ __('vendor.cert_ip_label') }} <strong>{{ $acceptance->accepted_ip }}</strong></div>
                <div>{{ __('vendor.cert_device_label') }} <strong style="word-break:break-word;">{{ $acceptance->accepted_device }}</strong></div>
                <div>{{ __('vendor.cert_hash_label') }} <strong style="font-size:8.5px; word-break:break-all;">{{ $acceptance->document_hash }}</strong></div>
            </div>
            <div style="margin-top:8px;">
                <a href="{{ route('admin.vendors.approvals.agreement-file') }}" target="_blank" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('vendor.view_signed_agreement_button') }}</a>
            </div>
        </div>

        <div style="flex-shrink:0; font-size:10px; font-weight:700; color:#374151; margin-bottom:4px;">{{ __('admin_vendors.otp_history_heading', ['count' => $otpLog->count()]) }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto; border:1px solid #e2e8f0; border-radius:6px;">
            @if($otpLog->isEmpty())
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('admin_vendors.no_otp_events') }}</div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:16%;"><col style="width:22%;"><col style="width:16%;"><col style="width:26%;"><col style="width:20%;">
                </colgroup>
                <thead>
                    <tr style="background:#f9fafb; position:sticky; top:0;">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_time') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_event') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_sent_to') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_ip_device') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($otpLog as $e)
                    @php
                        $evLabel = match($e->event_type) {
                            'OTP_SENT' => __('admin_vendors.otp_event_sent'),
                            'OTP_VERIFY_SUCCESS' => __('admin_vendors.otp_event_verify_success'),
                            'OTP_VERIFY_FAILED' => __('admin_vendors.otp_event_verify_failed'),
                            'OTP_VERIFY_EXPIRED' => __('admin_vendors.otp_event_verify_expired'),
                            'OTP_VERIFY_TOO_MANY_ATTEMPTS' => __('admin_vendors.otp_event_verify_too_many'),
                            default => $e->event_type,
                        };
                        $evColor = $e->event_type === 'OTP_VERIFY_SUCCESS' ? '#166534' : ($e->event_type === 'OTP_SENT' ? '#1565C0' : '#b71c1c');
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; vertical-align:top; color:#6b7280;">{{ \Carbon\Carbon::parse($e->created_at)->format('d M Y, h:ia:s') }}</td>
                        <td style="padding:5px 6px; vertical-align:top; font-weight:700; color:{{ $evColor }};">{{ $evLabel }}</td>
                        <td style="padding:5px 6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $e->recipient_email }}</td>
                        <td style="padding:5px 6px; vertical-align:top; color:#6b7280; word-break:break-word;">{{ $e->ip_address }}<br><span style="font-size:8.5px;">{{ $e->device }}</span></td>
                        <td style="padding:5px 6px; vertical-align:top; color:#9ca3af; word-break:break-word;">{{ $e->note ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        <div style="flex-shrink:0; padding-top:10px; margin-top:8px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendor-agreements.index') }}" style="background:#F7FAFC; color:#374151; text-decoration:none; border:1px solid #d1d5db; border-radius:20px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('admin_vendors.back_to_compliance_log') }}</a>
        </div>
    </div>
</div>

@endsection
