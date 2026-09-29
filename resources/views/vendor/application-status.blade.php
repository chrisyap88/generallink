@extends('layouts.vendor')

@section('page-title', __('vendor.my_application_title'))

@section('content')

{{-- NEW 13 Aug 2026 — the ONE screen a RESTRICTED-status vendor can
     reach (see RestrictVendorPortalAccess): their application status,
     the same communication thread the admin-side Vendor Onboarding
     Workflow and the emailed token-link page both use, and any open
     amendment requests, with the ability to reply and attach a file
     without needing an emailed link anymore. --}}

<div style="height:100%; display:flex; flex-direction:column; padding:14px 18px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; background:#fff3cd; border:1px solid #ffe69c; border-radius:8px; padding:8px 12px; margin-bottom:10px;">
        <div style="font-size:12px; font-weight:700; color:#856404;">{{ __('vendor.awaiting_approval_heading') }}</div>
        <div style="font-size:10.5px; color:#856404; margin-top:2px;">{{ __('vendor.awaiting_approval_note', ['name' => $vendor->vendor_name]) }}</div>
    </div>

    @if($amendments->isNotEmpty())
    <div style="flex-shrink:0; background:#fef9c3; border-radius:8px; padding:8px 12px; margin-bottom:10px;">
        <div style="font-size:10.5px; font-weight:700; color:#854d0e; margin-bottom:4px;">{{ __('vendor.open_requests_heading') }}</div>
        @foreach($amendments as $a)
        <div style="font-size:10px; color:#4b5563; margin-bottom:2px;">&bull; <strong>{{ $a->item_label }}</strong> — {{ $a->request_note }}</div>
        @endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #e0f2fe; border-radius:10px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; padding:10px 14px; border-bottom:1px solid #f3f4f6; font-size:12px; font-weight:700; color:#0D5A8E;">{{ __('vendor.messages_heading') }}</div>

        <div style="flex:1; min-height:0; overflow-y:auto; padding:12px 14px; display:flex; flex-direction:column; gap:8px;">
            @forelse($thread as $m)
            <div style="display:flex; {{ $m->sender_type === 'VENDOR' ? 'justify-content:flex-end;' : '' }}">
                <div style="max-width:78%;">
                    @if($m->message_type === 'AMENDMENT_REQUEST')
                    <div style="font-size:8.5px; font-weight:700; color:#b45309; margin-bottom:2px;">{{ __('vendor.change_requested_tag') }}</div>
                    @endif
                    <div style="font-size:11.5px; line-height:1.4; border-radius:12px; padding:7px 11px; word-break:break-word; {{ $m->sender_type === 'VENDOR' ? 'background:#1B9AE4; color:#fff;' : 'background:#f0f9ff; color:#1a2b3c;' }}">{{ $m->message }}</div>
                    @if($m->attachment_path)
                    <div style="margin-top:3px; {{ $m->sender_type === 'VENDOR' ? 'text-align:right;' : '' }}">
                        <a href="{{ route('vendor.application-status.attachment', $m->message_id) }}" target="_blank" style="display:inline-block; font-size:9.5px; font-weight:600; color:#0D5A8E; background:#e0f2fe; border-radius:5px; padding:2px 8px; text-decoration:none;">&#128206; {{ $m->attachment_file_name }}</a>
                    </div>
                    @endif
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px; {{ $m->sender_type === 'VENDOR' ? 'text-align:right;' : '' }}">{{ $m->sender_type === 'VENDOR' ? __('help-desk.you') : ($adminNames[$m->sender_admin_id] ?? 'GeneralLink') }} &middot; {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:ia') }}</div>
                </div>
            </div>
            @empty
            <div style="margin:auto; color:#9ca3af; font-size:11px;">{{ __('vendor.no_messages_yet') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('vendor.application-status.message') }}" enctype="multipart/form-data" style="flex-shrink:0; border-top:1px solid #f3f4f6; padding:10px 14px; display:flex; flex-direction:column; gap:6px;">
            @csrf
            <textarea name="message" maxlength="2000" required placeholder="{{ __('vendor.message_placeholder') }}" style="width:100%; height:48px; resize:none; border:1.5px solid #b2ebf2; border-radius:8px; padding:7px 9px; font-size:11px; font-family:'Outfit',sans-serif; color:#2D3748; background:#f7fdff; outline:none;"></textarea>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="font-size:9.5px; max-width:55%;">
                <button type="submit" style="flex-shrink:0; padding:6px 20px; background:linear-gradient(90deg,#1B9AE4 0%,#0D5A8E 100%); color:#fff; border:none; border-radius:8px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('help-desk.send') }}</button>
            </div>
        </form>
    </div>
</div>

@endsection
