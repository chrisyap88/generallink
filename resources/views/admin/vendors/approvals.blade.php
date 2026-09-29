@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.vendor_approvals_title'))

@section('content')

{{-- REDESIGNED 14 Aug 2026 — per Chris: "will look like show first
     screen like workflow but will approved button on the right with
     Green color." Matches Vendor Onboarding Workflow's list layout
     (plain title/subtitle header block, same table/badge styling)
     instead of the Pending Vendor Logins look this screen borrowed at
     first. Approve is now the prominent green primary action per row —
     clicking it opens the Approval Letter preview (see
     approval-letter.blade.php) before anything is actually approved.
     Reject/Q&A/Forward to Director still live one click away via
     Details. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_vendors.vendor_approvals_title') }}</div>
        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.vendor_approvals_subtitle') }}</div>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            @if($pendingVendors->isEmpty())
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_vendors.no_pending_approval_action') }}</div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <colgroup>
                    <col style="width:10%;"><col style="width:20%;"><col style="width:14%;"><col style="width:20%;">
                    <col style="width:14%;"><col style="width:22%;">
                </colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_date') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_vendor') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_status') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_contact1') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_risk_score') }}</th>
                        <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingVendors as $v)
                    @php
                        $dd = $assessments[$v->vendor_id] ?? null;
                        $vsLabel = match($v->login_status) { 'PENDING' => __('admin_vendors.status_pending_review'), 'AWAITING_PASSWORD' => __('admin_vendors.status_awaiting_password_setup'), 'RESTRICTED' => __('admin_vendors.status_restricted_access'), default => $v->login_status };
                        $vsColor = match($v->login_status) { 'PENDING' => '#854d0e', 'AWAITING_PASSWORD' => '#1565C0', 'RESTRICTED' => '#6D28D9', default => '#6b7280' };
                        $vsBg = match($v->login_status) { 'PENDING' => '#fef9c3', 'AWAITING_PASSWORD' => '#e0f2fe', 'RESTRICTED' => '#f5f3ff', default => '#f3f4f6' };
                        // Approve is only meaningful once the vendor can
                        // actually be finalized — AWAITING_PASSWORD has
                        // nothing to approve yet (see approve()'s own
                        // guard), so that row only gets Details.
                        $canApprove = in_array($v->login_status, ['PENDING', 'RESTRICTED'], true);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px; vertical-align:top; color:#6b7280;">{{ \Carbon\Carbon::parse($v->created_at)->format('d M Y') }}</td>
                        <td style="padding:6px; vertical-align:top; font-weight:700; color:#263238; word-break:break-word;">{{ $v->vendor_name }}</td>
                        <td style="padding:6px; vertical-align:top;"><span style="font-size:8.5px; font-weight:700; padding:2px 6px; border-radius:8px; white-space:nowrap; color:{{ $vsColor }}; background:{{ $vsBg }};">{{ $vsLabel }}</span></td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $v->pic_name }}<br><span style="font-size:9px; color:#9ca3af;">{{ $v->pic_phone }}</span></td>
                        <td style="padding:6px; vertical-align:top;">
                            @if($dd)
                            @php $ddRisk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd); @endphp
                            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:{{ $ddRisk['recommendation_color'] }}; margin-right:3px;"></span><span style="font-size:8.5px; color:{{ $ddRisk['recommendation_color'] }};">{{ $ddRisk['score'] }}/100 &middot; {{ $ddRisk['band'] }}</span>
                            @else
                            <span style="font-size:8.5px; color:#9ca3af;">{{ __('admin_vendors.not_run') }}</span>
                            @endif
                        </td>
                        <td style="padding:6px; vertical-align:top; text-align:center; white-space:nowrap;">
                            @if($canApprove)
                            <a href="{{ route('admin.vendors.approvals.letter', $v->vendor_id) }}" style="background:#2e7d32; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:700; white-space:nowrap; display:inline-block; margin-right:4px;">&#10003; {{ __('masterfile.approve_button') }}</a>
                            @endif
                            <a href="{{ route('admin.vendors.approvals.show', $v->vendor_id) }}" style="background:#F7FAFC; color:#374151; text-decoration:none; border:1px solid #d1d5db; border-radius:6px; padding:5px 10px; font-size:9.5px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.details_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        @if($pendingVendors->hasPages() || $pendingVendors->total() > 0)
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            @if($pendingVendors->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pendingVendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_vendors.approvals_page_of', ['current' => $pendingVendors->currentPage(), 'last' => $pendingVendors->lastPage(), 'total' => $pendingVendors->total()]) }}</span>
            @if($pendingVendors->hasMorePages())
                <a href="{{ $pendingVendors->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>

</div>

@endsection
