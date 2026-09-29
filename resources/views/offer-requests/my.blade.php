@extends('layouts.dashboard')

@section('page-title', __('offer_requests.my_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('offer-requests.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('offer_requests.submit_offer_link') }}</a>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($requests as $r)
            @php
                $statusColor = match($r->status) { 'APPROVED' => '#2e7d32', 'REJECTED' => '#e53935', default => '#D97706' };
                $statusBg = match($r->status) { 'APPROVED' => '#e8f5e9', 'REJECTED' => '#fde8e8', default => '#fff8e1' };
                $offerStatusLabels = ['APPROVED' => __('points.status_approved'), 'REJECTED' => __('points.status_rejected'), 'PENDING' => __('points.status_pending')];
            @endphp
            <div style="border:1px solid #f3f4f6; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
                <div style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <div style="font-size:11.5px; font-weight:700; color:#263238;">{{ $r->title }} <span style="font-weight:400; color:#9ca3af;">&middot; {{ $r->vendor_name }}</span></div>
                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 10px; font-size:9px; font-weight:700; white-space:nowrap;">{{ $offerStatusLabels[$r->status] ?? $r->status }}</span>
                </div>
                <div style="font-size:9px; color:#9ca3af; margin-top:3px;">{!! __('offer_requests.submitted_expires_line', ['submitted' => \Carbon\Carbon::parse($r->created_at)->format('d M Y'), 'expires' => \Carbon\Carbon::parse($r->expiry_date)->format('d M Y')]) !!}</div>
                @if($r->status === 'REJECTED' && $r->rejection_reason)
                <div style="font-size:10px; color:#b71c1c; margin-top:4px;">{{ __('offer_requests.reason_colon_label') }} {{ $r->rejection_reason }}</div>
                @endif
            </div>
            @empty
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('offer_requests.not_submitted_offers_note') }}</div>
            @endforelse
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($requests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $requests->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('customer_kpi.page_x_of_y', ['current' => $requests->currentPage(), 'last' => $requests->lastPage()]) }}</span>
            @if($requests->hasMorePages())
                <a href="{{ $requests->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
