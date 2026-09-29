@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.or_page_title'))

@section('content')

{{-- NEW 8 Aug 2026 (Task #95) — Admin approval queue for vendor/agent-
     submitted offers. Auto-checks shown per Chris's confirmed criteria;
     content accuracy/compliance is Admin's own manual read, enforced
     with a required "I've reviewed this" checkbox before Approve is
     clickable. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('admin.notice-board.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('admin_ops.or_notice_board_link') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($requests as $r)
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:10px 12px; margin-bottom:8px; background:#f9fafb;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; flex-wrap:wrap;">
                    <div style="min-width:0;">
                        <div style="font-size:11.5px; font-weight:700; color:#263238;">{{ $r->title }} <span style="font-weight:400; color:#9ca3af;">&middot; {{ $r->vendor_name }}</span></div>
                        <div style="font-size:10px; color:#4b5563; margin-top:3px; max-width:520px;">{{ \Illuminate\Support\Str::limit($r->body, 200) }}</div>
                        <div style="font-size:9px; color:#9ca3af; margin-top:4px;">{{ $r->submitter_label }} &middot; {{ __('admin_ops.or_submitted_label') }} {{ \Carbon\Carbon::parse($r->created_at)->format('d M Y') }} &middot; {{ __('admin_ops.or_expires_label') }} {{ \Carbon\Carbon::parse($r->expiry_date)->format('d M Y') }}</div>
                        <div style="display:flex; gap:6px; margin-top:6px; flex-wrap:wrap;">
                            <span style="background:{{ $r->vendor_is_active ? '#e8f5e9' : '#fde8e8' }}; color:{{ $r->vendor_is_active ? '#1b5e20' : '#b71c1c' }}; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ $r->vendor_is_active ? __('admin_ops.or_vendor_active_badge') : __('admin_ops.or_vendor_inactive_badge') }}</span>
                            <span style="background:{{ $r->has_duplicate_active ? '#fde8e8' : '#e8f5e9' }}; color:{{ $r->has_duplicate_active ? '#b71c1c' : '#1b5e20' }}; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ $r->has_duplicate_active ? __('admin_ops.or_duplicate_offer_badge') : __('admin_ops.or_no_duplicate_offer_badge') }}</span>
                            <span style="background:#e8f5e9; color:#1b5e20; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:700;">{{ __('admin_ops.or_expiry_date_set_badge') }}</span>
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px; flex-shrink:0; align-items:flex-end;">
                        <form method="POST" action="{{ route('admin.offer-requests.approve', $r->request_id) }}" onsubmit="return confirm({{ json_encode(__('admin_ops.or_approve_confirm', ['title' => $r->title])) }});">
                            @csrf
                            <label style="display:flex; align-items:center; gap:4px; font-size:8.5px; color:#6b7280; margin-bottom:4px; cursor:pointer;">
                                <input type="checkbox" required> {{ __('admin_ops.or_reviewed_confirm_label') }}
                            </label>
                            <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer; width:100%;">{{ __('growth.approve_button') }}</button>
                        </form>
                        <button type="button" onclick="document.getElementById('reject-{{ $r->request_id }}').style.display='flex'" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer; width:100%;">{{ __('growth.reject_button') }}</button>
                    </div>
                </div>
                <form id="reject-{{ $r->request_id }}" method="POST" action="{{ route('admin.offer-requests.reject', $r->request_id) }}" style="display:none; gap:6px; margin-top:8px; align-items:center;">
                    @csrf
                    <input type="text" name="reason" required maxlength="255" placeholder="{{ __('admin_ops.or_reject_reason_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; outline:none;">
                    <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('growth.confirm_reject_button') }}</button>
                </form>
            </div>
            @empty
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_ops.or_no_requests') }}</div>
            @endforelse
        </div>
    </div>

</div>
@endsection
