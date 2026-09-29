{{-- NEW 10 Aug 2026 — shared by growth/my-submissions.blade.php (agent)
     and vendor/my-submissions.blade.php (vendor). Shows the submitter's
     own history: what they sent, current status, and Admin's reason if
     Rejected — so nobody has to ask "did you get my flyer?" --}}
@php
    $statusColors = [
        'PENDING_REVIEW' => ['#fef3c7', '#92400e', __('growth.status_pending_review')],
        'ACTIVE'          => ['#d1fae5', '#065f46', __('growth.status_active')],
        'INACTIVE'        => ['#f3f4f6', '#6b7280', __('growth.status_inactive')],
        'REJECTED'        => ['#fee2e2', '#991b1b', __('growth.status_rejected')],
    ];
    $iconMap = ['VIDEO' => '🎬', 'SLIDESHOW' => '📊', 'FLYER' => '🖼', 'LINK' => '🔗'];
@endphp
@if($submissions->isEmpty())
<div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
    <div>
        <div style="font-size:32px; margin-bottom:8px;">📤</div>
        <div>{{ __('growth.not_submitted_anything_yet') }}</div>
    </div>
</div>
@else
<div style="flex:1; overflow-y:auto; min-height:0;">
    @foreach($submissions as $s)
    @php [$bg, $fg, $label] = $statusColors[$s->status] ?? ['#f3f4f6', '#6b7280', $s->status]; @endphp
    <div style="border:1px solid #e0f2fe; border-radius:9px; padding:8px 12px; margin-bottom:8px;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:8px;">
            <div style="font-size:12px; font-weight:700; color:#111827; word-break:break-word; flex:1; min-width:0;">{{ $iconMap[$s->content_type ?? 'VIDEO'] ?? '🎬' }} {{ $s->video_name }}</div>
            <span style="background:{{ $bg }}; color:{{ $fg }}; font-size:9px; font-weight:700; padding:2px 8px; border-radius:20px; white-space:nowrap;">{{ $label }}</span>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:4px 18px; margin-top:5px; font-size:10px; color:#6b7280;">
            <div><span style="color:#9ca3af;">{{ __('growth.category_label') }}</span> {{ $types[$s->video_type] ?? $s->video_type }}</div>
            <div><span style="color:#9ca3af;">{{ __('growth.sent_label') }}</span> {{ \Carbon\Carbon::parse($s->created_at)->format('d M Y') }}</div>
        </div>
        @if($s->status === 'REJECTED' && $s->rejection_reason)
        <div style="margin-top:5px; font-size:10px; color:#991b1b; background:#fef2f2; border-radius:6px; padding:5px 8px;">{{ __('growth.admins_note_label') }} {{ $s->rejection_reason }}</div>
        @endif
        <div style="display:flex; justify-content:flex-end; margin-top:6px;">
            <a href="{{ route('video-library.stream', $s->video_id) }}" target="_blank" style="font-size:10px; color:#1565C0; font-weight:600; text-decoration:none;">{{ __('growth.view_button') }}</a>
        </div>
    </div>
    @endforeach
</div>
@if($submissions->lastPage() > 1)
<div style="padding:8px 0 0; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between; margin-top:4px;">
    @if($submissions->onFirstPage())
        <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('growth.prev') }}</span>
    @else
        <a href="{{ $submissions->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('growth.prev') }}</a>
    @endif
    <span style="font-size:11px; color:#6b7280;">{{ __('growth.page_of_slash', ['current' => $submissions->currentPage(), 'last' => $submissions->lastPage()]) }}</span>
    @if($submissions->hasMorePages())
        <a href="{{ $submissions->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('growth.next') }}</a>
    @else
        <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('growth.next') }}</span>
    @endif
</div>
@endif
@endif
