@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.vendor_qa_title'))

@section('content')

{{-- NEW 12 Aug 2026 (Task #106) — per Chris: "remove all popup content,
     new screen with proper tab." Was qaModal-{vendorId}, a popup opened
     from the (also now-removed) detail modal. Same message thread +
     send form, just a real page now. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <a href="{{ route('admin.vendors.approvals.show', $vendor->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('admin_vendors.qa_heading', ['name' => $vendor->vendor_name]) }}</div>
            <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.qa_subtitle') }}</div>
        </div>

        <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:6px; padding-right:2px;">
            @forelse($thread as $m)
            <div style="display:flex; {{ $m->sender_type === 'ADMIN' ? 'justify-content:flex-end;' : '' }}">
                <div style="max-width:80%;">
                    <div style="font-size:10.5px; line-height:1.4; border-radius:10px; padding:6px 10px; word-break:break-word; {{ $m->sender_type === 'ADMIN' ? 'background:#1565C0; color:#fff;' : 'background:#f3f4f6; color:#1a2b3c;' }}">{{ $m->message }}</div>
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px; {{ $m->sender_type === 'ADMIN' ? 'text-align:right;' : '' }}">{{ $m->sender_type === 'ADMIN' ? ($adminNames[$m->sender_admin_id] ?? __('admin_vendors.admin_fallback_name')) : $vendor->vendor_name }} &middot; {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:ia') }}</div>
                </div>
            </div>
            @empty
            <div style="margin:auto; color:#9ca3af; font-size:10.5px;">{{ __('admin_vendors.no_messages_send_below') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('admin.vendors.pending-logins.qa-message', $vendor->vendor_id) }}" style="flex-shrink:0; margin-top:8px; display:flex; flex-direction:column; gap:6px;">
            @csrf
            <textarea name="message" required maxlength="2000" placeholder="{{ __('admin_vendors.message_placeholder_qa') }}" style="width:100%; height:56px; resize:none; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;"></textarea>
            <button type="submit" style="align-self:flex-end; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.send_button') }}</button>
        </form>
    </div>
</div>

@endsection
