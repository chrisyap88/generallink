@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('member_file.join_qr'))
@section('content')
{{-- NEW 28 Sep 2026 — per Chris (member file item 31): the entity's own QR code to print. --}}
@include('admin.member-file._style')
<div class="mf-page">
    <div class="mf-title">{{ __('member_file.join_qr') }} — {{ $node->node_name }}</div>
    <div class="mf-box" style="display:flex; align-items:center; justify-content:center; gap:24px;">
        <img src="{{ $qr }}" alt="QR" style="height:min(320px, 100%); max-height:100%;">
        <div style="max-width:380px;">
            <div style="font-size:13px; font-weight:700;">{{ $node->node_name }}</div>
            <div class="mf-sub" style="font-size:10.5px;">{{ $node->group_name }}</div>
            <div style="font-size:11px; margin-top:10px; color:#374151;">{{ __('member_file.join_qr_help') }}</div>
            <div style="font-size:10px; margin-top:8px; word-break:break-all; color:#1565C0;">{{ $url }}</div>
            <button type="button" class="mf-btn" style="margin-top:12px;" onclick="window.print()">{{ __('member_file.print') }}</button>
        </div>
    </div>
    <div class="mf-bar"><a href="{{ url()->previous() }}" class="mf-btn">{{ __('masterfile.prev') }}</a><span></span><span class="mf-btn" style="visibility:hidden;">{{ __('masterfile.next') }}</span></div>
</div>
@endsection
