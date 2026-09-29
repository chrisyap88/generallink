@extends('layouts.vendor')

@section('page-title', __('vendor.my_submissions_title'))

@section('content')
<div style="height:100%; display:flex; flex-direction:column; padding:20px 24px; box-sizing:border-box;">

    <p style="font-size:11px; color:#718096; margin-bottom:14px;">{{ __('vendor.my_offers_intro') }}</p>

    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @php
                $offerStatusLabels = [
                    'APPROVED' => __('masterfile.status_approved'),
                    'REJECTED' => __('masterfile.status_rejected'),
                ];
            @endphp
            @forelse($requests as $r)
            @php
                $statusColor = match($r->status) { 'APPROVED' => '#2e7d32', 'REJECTED' => '#e53935', default => '#D97706' };
                $statusBg = match($r->status) { 'APPROVED' => '#e8f5e9', 'REJECTED' => '#fde8e8', default => '#fff8e1' };
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <div style="font-size:12px; font-weight:700; color:#263238;">{{ $r->title }}</div>
                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 10px; font-size:9.5px; font-weight:700; white-space:nowrap;">{{ $offerStatusLabels[$r->status] ?? __('finance.status_pending') }}</span>
                </div>
                <div style="font-size:9.5px; color:#9ca3af; margin-top:3px;">{{ __('vendor.submitted_label') }} {{ \Carbon\Carbon::parse($r->created_at)->format('d M Y') }} &middot; {{ __('vendor.expires_label') }} {{ \Carbon\Carbon::parse($r->expiry_date)->format('d M Y') }}</div>
                @if($r->status === 'REJECTED' && $r->rejection_reason)
                <div style="font-size:10px; color:#b71c1c; margin-top:4px;">{{ __('vendor.reason_label') }} {{ $r->rejection_reason }}</div>
                @endif
            </div>
            @empty
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('vendor.no_offers_yet') }}</div>
            @endforelse
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($requests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $requests->previousPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('growth.page_of', ['current' => $requests->currentPage(), 'last' => $requests->lastPage()]) }}</span>
            @if($requests->hasMorePages())
                <a href="{{ $requests->nextPageUrl() }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
