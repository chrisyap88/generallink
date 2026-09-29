@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('vendor.rebate_applications_title'))

@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px; box-sizing:border-box;">

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:10px 16px; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">📋 {{ __('vendor.rebate_applications_title') }}</div>
        <form method="GET" action="{{ route('admin.masterfile.rebate-applications') }}">
            <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:4px 8px; font-size:10px;">
                <option value="PENDING" {{ $status === 'PENDING' ? 'selected' : '' }}>{{ __('vendor.admin_pending_review_option') }}</option>
                <option value="APPROVED" {{ $status === 'APPROVED' ? 'selected' : '' }}>{{ __('masterfile.status_approved') }}</option>
                <option value="REJECTED" {{ $status === 'REJECTED' ? 'selected' : '' }}>{{ __('masterfile.status_rejected') }}</option>
                <option value="ALL" {{ $status === 'ALL' ? 'selected' : '' }}>{{ __('vendor.admin_all_option') }}</option>
            </select>
        </form>
    </div>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        @if($applications->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:12px; text-align:center;">
            <div>
                <div style="font-size:32px; margin-bottom:8px;">📋</div>
                <div>{{ __('vendor.no_status_applications_template', ['status' => strtolower($status)]) }}</div>
            </div>
        </div>
        @else
        <div style="flex:1; overflow:hidden; padding:10px 12px; display:flex; flex-direction:column; gap:8px;">
            @foreach($applications as $a)
            @php
                $statusColor = match($a->status) { 'APPROVED' => '#2e7d32', 'REJECTED' => '#e53935', default => '#D97706' };
                $statusBg = match($a->status) { 'APPROVED' => '#e8f5e9', 'REJECTED' => '#fde8e8', default => '#fff8e1' };
            @endphp
            <div style="border:1px solid #e0f2fe; border-radius:9px; padding:10px 14px;">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
                    <div style="min-width:0; flex:1;">
                        <div style="display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
                            <span style="font-size:12px; font-weight:700; color:#111827;">{{ $a->vendor_name }}</span>
                            <span style="font-size:10px; color:#6b7280;">{{ $a->product_name ?? __('vendor.general_vendor_wide') }}</span>
                            <span style="background:#eef2ff; color:#4338ca; font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:10px;">{{ $a->application_number }}{{ $a->version_number > 1 ? ' ' . __('vendor.version_badge', ['num' => $a->version_number]) : '' }}</span>
                            @if($a->rebate_program_number)
                            <span style="background:#e0f2fe; color:#0369a1; font-size:8.5px; font-weight:700; padding:1px 7px; border-radius:10px;">{{ __('vendor.program_badge', ['num' => $a->rebate_program_number]) }}</span>
                            @endif
                        </div>
                        <div style="font-size:10.5px; color:#374151; line-height:1.4; word-break:break-word; margin-top:4px;">{{ $a->rebate_details }}</div>
                        <div style="font-size:9px; color:#9ca3af; margin-top:3px;">{{ __('vendor.submitted_label') }} {{ \Carbon\Carbon::parse($a->created_at)->format('d M Y, h:ia') }}@if($a->valid_from) &middot; {{ __('vendor.valid_word') }} {{ \Carbon\Carbon::parse($a->valid_from)->format('d M Y') }}@if($a->valid_until) – {{ \Carbon\Carbon::parse($a->valid_until)->format('d M Y') }}@endif @endif</div>
                    </div>
                    <span style="background:{{ $statusBg }}; color:{{ $statusColor }}; border-radius:10px; padding:2px 10px; font-size:9.5px; font-weight:700; white-space:nowrap; flex-shrink:0;">{{ $a->status }}</span>
                </div>
                @if($a->status === 'PENDING')
                <div style="display:flex; gap:6px; margin-top:8px;">
                    <form method="POST" action="{{ route('admin.masterfile.rebate-applications.approve', $a->application_id) }}" onsubmit="return confirm({{ json_encode(__('vendor.admin_approve_confirm_template')) }});">
                        @csrf
                        <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('vendor.approve_check_button') }}</button>
                    </form>
                    <button type="button" onclick="document.getElementById('rej-{{ $a->application_id }}').style.display='flex'" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('vendor.reject_cross_button') }}</button>
                    <form id="rej-{{ $a->application_id }}" method="POST" action="{{ route('admin.masterfile.rebate-applications.reject', $a->application_id) }}" style="display:none; gap:6px; flex:1; align-items:center;">
                        @csrf
                        <input type="text" name="reason" required maxlength="255" placeholder="{{ __('vendor.rejection_reason_placeholder') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; outline:none;">
                        <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.confirm_button') }}</button>
                    </form>
                </div>
                @elseif($a->status === 'REJECTED' && $a->rejection_reason)
                <div style="font-size:9.5px; color:#b71c1c; margin-top:6px;">{{ __('vendor.rejection_reason_template', ['reason' => $a->rejection_reason]) }}</div>
                @endif
            </div>
            @endforeach
        </div>
        <div style="padding:8px 12px; border-top:1px solid #f3f4f6; flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
            @if($applications->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $applications->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:11px; color:#6b7280;">{!! __('vendor.showing_page_template', ['first' => '<strong>'.$applications->firstItem().'</strong>', 'last' => '<strong>'.$applications->lastItem().'</strong>', 'totalRecords' => '<strong>'.$applications->total().'</strong>', 'current' => $applications->currentPage(), 'totalPages' => $applications->lastPage()]) !!}</span>
            @if($applications->hasMorePages())
                <a href="{{ $applications->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
