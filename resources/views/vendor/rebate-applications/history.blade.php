@extends('layouts.vendor')

@section('page-title', __('vendor.application_history_title'))

@section('content')
<div style="height:100%; display:flex; flex-direction:column; padding:16px 24px; box-sizing:border-box; gap:10px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#0D5A8E;">{{ __('vendor.version_history_heading', ['num' => $applicationNumber]) }}</div>
        <a href="{{ route('vendor.rebate-applications.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:600;">{{ __('ai.back_link') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        {{-- CHANGED 13 Aug 2026 per Chris: was one unpaginated list inside
             a fixed overflow:hidden box — a program revised many times
             would have older versions silently invisible with no way to
             reach them. Now paginated (6/page) with a bottom Prev/Next
             bar, same pattern as every other list screen. --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            @php
                $historyStatusLabels = [
                    'APPROVED' => __('masterfile.status_approved'),
                    'REJECTED' => __('masterfile.status_rejected'),
                ];
            @endphp
            @foreach($versions as $ver)
            @php
                $statusColor = match($ver->status) { 'APPROVED' => '#2e7d32', 'REJECTED' => '#e53935', default => '#D97706' };
                $statusBg = match($ver->status) { 'APPROVED' => '#e8f5e9', 'REJECTED' => '#fde8e8', default => '#fff8e1' };
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:9px 12px; margin-bottom:7px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <span style="font-size:11px; font-weight:700; color:#263238;">{{ __('vendor.version_label', ['num' => $ver->version_number]) }}{{ $ver->superseded_at ? __('vendor.version_superseded_suffix') : __('vendor.version_current_suffix') }}</span>
                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 10px; font-size:9.5px; font-weight:700; white-space:nowrap;">{{ $historyStatusLabels[$ver->status] ?? __('finance.status_pending') }}</span>
                </div>
                <div style="font-size:10px; color:#374151; margin-top:4px; line-height:1.4; word-break:break-word;">{{ $ver->rebate_details }}</div>
                <div style="font-size:9px; color:#9ca3af; margin-top:4px;">{{ $ver->product_name ?? __('vendor.general_vendor_wide') }} &middot; {{ __('vendor.submitted_label') }} {{ \Carbon\Carbon::parse($ver->created_at)->format('d M Y, h:ia') }}</div>
            </div>
            @endforeach
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            @if($versions->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $versions->previousPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('growth.page_of', ['current' => $versions->currentPage(), 'last' => $versions->lastPage()]) }}</span>
            @if($versions->hasMorePages())
                <a href="{{ $versions->nextPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
