@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_reports.vl_content_library'))

{{-- REBUILT 10 Aug 2026 per Chris — two rounds of feedback:
     (1) "your add video should have it won screen" — Add Video moved to
         its own screen (video-library/create.blade.php), this screen is
         list-only, full width now.
     (2) "the word delete truncated and...what admin can identify it is
         for the vendor vendor name, date submit and the purpose and the
         expired date." The old 6-column table crushed everything into
         narrow cells (that's what truncated "Delete"/"Deactivate" and
         would have made an 8-9 column table unreadable once Vendor/
         Purpose/Submitted/Expiry were added). Switched to one card per
         video instead of a table row — every field gets real room, a
         real Delete button now exists (with a confirm prompt, since it
         also removes the file from disk), and a "★ Now Live" tag shows
         Admin exactly which Introduction video is actually playing on
         the login pages when more than one is marked Active. --}}
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:6px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- NEW 10 Aug 2026 — per Chris: agent/vendor submissions ("send
         attachment...to admin") land here as Pending Review. --}}
    @if($pendingCount > 0 && !request('status'))
    <div style="background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:6px 12px; color:#92400e; font-size:11px; font-weight:600; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
        <span>⏳ {{ __('admin_reports.vl_items_waiting_review', ['count' => $pendingCount]) }}</span>
        <a href="{{ route('admin.video-library.index', ['status' => 'PENDING_REVIEW']) }}" style="color:#92400e; text-decoration:underline;">{{ __('admin_reports.vl_review_now') }}</a>
    </div>
    @endif

    {{-- Folder Path Settings — compact, one row --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:8px 14px; flex-shrink:0;">
        <form method="POST" action="{{ route('admin.video-library.folder-path') }}" style="display:flex; align-items:center; gap:8px;">
            @csrf
            <span style="font-size:11px; font-weight:700; color:#1565C0; white-space:nowrap;">📁 {{ __('admin_reports.vl_content_storage_folder') }}</span>
            <input type="text" name="folder_path" value="{{ old('folder_path', $folderPath) }}" placeholder="{{ __('admin_reports.vl_folder_path_placeholder') }}" required style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:11px; outline:none; box-sizing:border-box;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_reports.vl_save_folder') }}</button>
            @if($folderPath)
                @if($folderReady)
                <span style="background:#d1fae5; color:#065f46; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px; white-space:nowrap;">✓ {{ __('admin_reports.vl_ready') }}</span>
                @else
                <span style="background:#fee2e2; color:#991b1b; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px; white-space:nowrap;">⚠ {{ __('admin_reports.vl_not_writable') }}</span>
                @endif
            @else
            <span style="background:#fef3c7; color:#92400e; font-size:9.5px; font-weight:600; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ __('admin_reports.vl_not_set_up_yet') }}</span>
            @endif
        </form>
        <div style="font-size:9.5px; color:#6b7280; margin-top:3px;">{{ __('admin_reports.vl_folder_desc') }}</div>
    </div>

    {{-- Content List — card per item, full width --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:8px;">
            <div style="font-size:13px; font-weight:700; color:#1565C0; white-space:nowrap;">📚 {{ __('admin_reports.vl_content_library') }}</div>
            <div style="display:flex; gap:8px; align-items:center;">
                <form method="GET" style="display:flex; gap:6px; align-items:center;">
                    <select name="content_type" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; outline:none;">
                        <option value="">{{ __('admin_reports.vl_all_types') }}</option>
                        @foreach($contentTypes as $key => $label)
                        <option value="{{ $key }}" {{ request('content_type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="type" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; outline:none;">
                        <option value="">{{ __('admin_reports.vl_all_categories') }}</option>
                        @foreach($types as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; outline:none;">
                        <option value="">{{ __('network.all_status_option') }}</option>
                        @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}{{ $key === 'PENDING_REVIEW' && $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.video-library.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; white-space:nowrap;">➕ {{ __('admin_reports.vl_add_content') }}</a>
            </div>
        </div>

        @if($videos->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
            <div>
                <div style="font-size:32px; margin-bottom:8px;">📚</div>
                <div>{{ __('admin_reports.vl_no_content_yet') }}</div>
                <div style="font-size:10px; margin-top:4px;"><a href="{{ route('admin.video-library.create') }}" style="color:#1565C0; font-weight:600;">{{ __('admin_reports.vl_add_first_item') }}</a></div>
            </div>
        </div>
        @else
        <div style="flex:1; overflow-y:auto; min-height:0; padding:8px 12px;">
            @php
                $vlStatusColors = [
                    'ACTIVE'          => ['#d1fae5', '#065f46'],
                    'INACTIVE'        => ['#f3f4f6', '#6b7280'],
                    'PENDING_REVIEW'  => ['#fef3c7', '#92400e'],
                    'REJECTED'        => ['#fee2e2', '#991b1b'],
                ];
            @endphp
            @foreach($videos as $video)
            @php [$vlBg, $vlFg] = $vlStatusColors[$video->status] ?? ['#f3f4f6', '#6b7280']; @endphp
            <div style="border:1px solid {{ $video->status === 'PENDING_REVIEW' ? '#fde68a' : '#e0f2fe' }}; border-radius:9px; padding:8px 12px; margin-bottom:8px; background:{{ $video->is_expired ? '#fafafa' : '#fff' }};">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px;">
                    <div style="font-size:12px; font-weight:700; color:#111827; word-break:break-word; flex:1; min-width:0;">{{ ['VIDEO' => '🎬', 'SLIDESHOW' => '📊', 'FLYER' => '🖼', 'LINK' => '🔗'][$video->content_type ?? 'VIDEO'] ?? '🎬' }} {{ $video->video_name }}</div>
                    <div style="display:flex; gap:4px; flex-shrink:0; flex-wrap:wrap; justify-content:flex-end;">
                        @if($video->is_featured_intro)
                        <span style="background:#dbeafe; color:#1e40af; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap;">★ {{ __('admin_reports.vl_now_live') }}</span>
                        @endif
                        @if($video->is_expired)
                        <span style="background:#fee2e2; color:#991b1b; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ __('admin_reports.vl_expired') }}</span>
                        @endif
                        <span style="background:{{ $vlBg }}; color:{{ $vlFg }}; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ $statuses[$video->status] ?? $video->status }}</span>
                    </div>
                </div>

                <div style="display:flex; flex-wrap:wrap; gap:4px 18px; margin-top:5px; font-size:10px; color:#6b7280;">
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_category_colon') }}</span> {{ $types[$video->video_type] ?? $video->video_type }}</div>
                    @if($video->video_type === 'FEATURE_GUIDE')
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_explains_colon') }}</span> {{ \App\Http\Controllers\Admin\VideoLibraryController::FEATURE_KEYS[$video->feature_key] ?? $video->feature_key }}</div>
                    @else
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_vendor_colon') }}</span> {{ $video->vendor_name ?? __('admin_reports.vl_corporate_video_note') }}</div>
                    @endif
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_ownership_colon') }}</span> {{ $video->ownership ?: '—' }}</div>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:4px 18px; margin-top:3px; font-size:10px; color:#6b7280;">
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_submitted_colon') }}</span> {{ $video->submitted_date ? \Carbon\Carbon::parse($video->submitted_date)->format('d M Y') : \Carbon\Carbon::parse($video->created_at)->format('d M Y') }}</div>
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_expires_colon') }}</span> {{ $video->expiry_date ? \Carbon\Carbon::parse($video->expiry_date)->format('d M Y') : __('admin_reports.vl_no_expiry') }}</div>
                    @if($video->purpose)
                    <div style="word-break:break-word;"><span style="color:#9ca3af;">{{ __('admin_reports.vl_purpose_colon') }}</span> {{ $video->purpose }}</div>
                    @endif
                </div>

                {{-- NEW 10 Aug 2026 — "drill down the source" per Chris:
                     who uploaded it (survives Admin staff turnover, since
                     agents are soft-deleted not removed) + where it came
                     from + a deep-link to that record's full change log,
                     so a brand-new Admin can trace any video's history
                     without ever having seen the original email/WhatsApp
                     message themselves. --}}
                <div style="display:flex; flex-wrap:wrap; align-items:center; gap:4px 18px; margin-top:3px; font-size:10px; color:#6b7280;">
                    @if($video->source_type === 'VENDOR_SUBMITTED')
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_sent_by_colon') }}</span> {{ $video->vendor_name ?? __('admin_reports.vl_unknown_vendor') }} {{ __('admin_reports.vl_vendor_suffix') }}</div>
                    @else
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_added_by_colon') }}</span> {{ $video->uploaded_by_name ?? __('admin_reports.vl_unknown_staff_removed') }}</div>
                    @endif
                    <div><span style="color:#9ca3af;">{{ __('admin_reports.vl_source_colon') }}</span> {{ $sourceTypes[$video->source_type] ?? $video->source_type }}{{ $video->source_note ? ' — ' . $video->source_note : '' }}</div>
                    <a href="{{ route('admin.masterfile.audit-logs', ['table_name' => 'video_library', 'record_id' => $video->video_id]) }}" style="color:#1565C0; font-weight:600; text-decoration:none; white-space:nowrap;">🕓 {{ __('admin_reports.vl_view_full_history') }}</a>
                </div>
                @if($video->status === 'REJECTED' && $video->rejection_reason)
                <div style="margin-top:5px; font-size:10px; color:#991b1b; background:#fef2f2; border-radius:6px; padding:5px 8px;">{{ __('admin_reports.vl_note_to_sender_colon') }} {{ $video->rejection_reason }}</div>
                @endif

                <div style="display:flex; justify-content:flex-end; gap:6px; margin-top:7px;">
                    @if(($video->content_type ?? 'VIDEO') === 'VIDEO')
                    <button type="button" onclick="playVLVideo('{{ route('video-library.stream', $video->video_id) }}','{{ addslashes($video->video_name) }}')" style="background:#eff6ff; color:#1565C0; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">▶ {{ __('admin_reports.vl_preview') }}</button>
                    @else
                    <a href="{{ route('video-library.stream', $video->video_id) }}" target="_blank" style="background:#eff6ff; color:#1565C0; text-decoration:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; white-space:nowrap;">{{ $video->content_type === 'LINK' ? '🔗 ' . __('admin_reports.vl_open_link') : '👁 ' . __('admin_reports.vl_view') }}</a>
                    @endif
                    @if($video->status === 'PENDING_REVIEW')
                    <form method="POST" action="{{ route('admin.video-library.approve', $video->video_id) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" style="background:#d1fae5; color:#065f46; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">✓ {{ __('admin_reports.vk_approve') }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.video-library.reject', $video->video_id) }}" style="display:inline;" onsubmit="return vlPromptReject(this)">
                        @csrf
                        <input type="hidden" name="rejection_reason" class="vlRejectReason">
                        <button type="submit" style="background:#fee2e2; color:#991b1b; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">✕ {{ __('admin_reports.vl_reject') }}</button>
                    </form>
                    @elseif($video->status === 'ACTIVE' || $video->status === 'INACTIVE')
                    <form method="POST" action="{{ route('admin.video-library.toggle', $video->video_id) }}" style="display:inline;">
                        @csrf @method('PATCH')
                        <button type="submit" style="background:{{ $video->status === 'ACTIVE' ? '#fee2e2' : '#d1fae5' }}; color:{{ $video->status === 'ACTIVE' ? '#991b1b' : '#065f46' }}; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ $video->status === 'ACTIVE' ? __('admin_reports.vl_deactivate') : __('admin_reports.vl_activate') }}</button>
                    </form>
                    @endif
                    <form method="POST" action="{{ route('admin.video-library.destroy', $video->video_id) }}" style="display:inline;" onsubmit="return confirm('{{ addslashes(__('admin_reports.vl_confirm_delete', ['name' => $video->video_name])) }}');">
                        @csrf @method('DELETE')
                        <button type="submit" style="background:#fff; color:#991b1b; border:1px solid #fecaca; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">🗑 {{ __('admin_reports.vl_delete') }}</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @if($videos->lastPage() > 1)
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($videos->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $videos->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{!! __('admin_reports.vl_showing_records', ['first' => $videos->firstItem(), 'last' => $videos->lastItem(), 'total' => $videos->total(), 'current' => $videos->currentPage(), 'lastPage' => $videos->lastPage()]) !!}</span>
            @if($videos->hasMorePages())
                <a href="{{ $videos->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @else
        <div style="padding:6px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; font-size:10px; color:#9ca3af; text-align:center;">{{ __('admin_reports.vl_items_total', ['count' => $videos->total()]) }}</div>
        @endif
        @endif
    </div>
</div>

{{-- Preview modal — used by the ▶ Preview buttons above --}}
<div id="vlModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:99999; align-items:center; justify-content:center;">
    <div style="background:#000; border-radius:10px; overflow:hidden; max-width:80vw; max-height:80vh; position:relative;">
        <button type="button" onclick="closeVLVideo()" style="position:absolute; top:6px; right:6px; background:rgba(255,255,255,.9); border:none; border-radius:50%; width:26px; height:26px; font-size:14px; font-weight:700; cursor:pointer; z-index:1;">✕</button>
        <div style="padding:8px 12px; background:#111; color:#fff; font-size:11px; font-weight:600;" id="vlModalTitle"></div>
        <video id="vlModalVideo" controls style="max-width:80vw; max-height:70vh; display:block;"></video>
    </div>
</div>

@push('scripts')
<script>
const VL_I18N = {
    rejectPrompt: @json(__('admin_reports.vl_reject_prompt')),
};
function playVLVideo(url, title) {
    document.getElementById('vlModalTitle').textContent = title;
    var v = document.getElementById('vlModalVideo');
    v.src = url;
    document.getElementById('vlModal').style.display = 'flex';
    v.play().catch(function(){});
}
function closeVLVideo() {
    var v = document.getElementById('vlModalVideo');
    v.pause();
    v.src = '';
    document.getElementById('vlModal').style.display = 'none';
}
// NEW 10 Aug 2026 — per Chris: rejecting always needs a reason, since
// the sender is notified with it. Cancelling the prompt (or leaving it
// blank) cancels the reject entirely, rather than submitting empty.
function vlPromptReject(form) {
    var reason = prompt(VL_I18N.rejectPrompt);
    if (reason === null || reason.trim() === '') { return false; }
    form.querySelector('.vlRejectReason').value = reason.trim();
    return true;
}
</script>
@endpush
@endsection
