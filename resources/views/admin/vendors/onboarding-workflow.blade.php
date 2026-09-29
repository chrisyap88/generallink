@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.onboarding_index_title'))

@section('content')

{{-- NEW 13 Aug 2026 — per Chris: "create another workflow program below
     pending approve in the menu dashboard... Vendor Onboarding and
     Communication Workflow. This program manages the entire onboarding
     lifecycle, from registration through review to approval or
     rejection." This index lists every vendor currently moving through
     that lifecycle (awaiting password setup, awaiting review, or
     recently decided) with a quick read on communication activity and
     open amendment requests; click into a row for the full thread +
     amendment log (see onboarding-workflow-show.blade.php). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_vendors.onboarding_index_title') }}</div>
        <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.onboarding_index_subtitle') }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">
        {{-- CHANGED 13 Aug 2026 per Chris: "incorporate prev and next...
             all information in one screen no scroll" — was
             overflow-y:auto (an inner scrollbar), now overflow:hidden to
             match every other list screen; perPage dropped to 5 above so
             rows never get silently cut off by this. --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <colgroup><col style="width:12%;"><col style="width:26%;"><col style="width:14%;"><col style="width:16%;"><col style="width:16%;"><col style="width:16%;"></colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_date') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_vendor') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_status') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_communication') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_open_amendments') }}</th>
                        <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendors as $v)
                    @php
                        $statusLabel = match($v->login_status) {
                            'PENDING' => __('admin_vendors.status_pending_review'), 'AWAITING_PASSWORD' => __('admin_vendors.status_awaiting_password_setup'),
                            'RESTRICTED' => __('admin_vendors.status_restricted_access'), 'ACTIVE' => __('admin_vendors.status_approved_active'), 'REJECTED' => __('masterfile.status_rejected'), default => $v->login_status,
                        };
                        $statusColor = match($v->login_status) {
                            'PENDING' => '#854d0e', 'AWAITING_PASSWORD' => '#1565C0', 'RESTRICTED' => '#6D28D9', 'ACTIVE' => '#166534', 'REJECTED' => '#b71c1c', default => '#6b7280',
                        };
                        $statusBg = match($v->login_status) {
                            'PENDING' => '#fef9c3', 'AWAITING_PASSWORD' => '#e0f2fe', 'RESTRICTED' => '#f5f3ff', 'ACTIVE' => '#f0fdf4', 'REJECTED' => '#fef2f2', default => '#f3f4f6',
                        };
                        $activity = $lastActivity[$v->vendor_id] ?? null;
                        $openCount = $openAmendments[$v->vendor_id] ?? 0;
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px; vertical-align:top; color:#6b7280;">{{ \Carbon\Carbon::parse($v->created_at)->format('d M Y') }}</td>
                        <td style="padding:6px; vertical-align:top; font-weight:700; color:#263238; word-break:break-word;">{{ $v->vendor_name }}</td>
                        <td style="padding:6px; vertical-align:top;"><span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; color:{{ $statusColor }}; background:{{ $statusBg }};">{{ $statusLabel }}</span></td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563;">
                            @if($activity)
                                {{ __('admin_vendors.message_count', ['count' => $activity->message_count]) }}<br>
                                <span style="font-size:8.5px; color:#9ca3af;">{{ __('admin_vendors.last_activity_label', ['date' => \Carbon\Carbon::parse($activity->last_at)->format('d M Y, h:ia')]) }}</span>
                            @else
                                <span style="color:#9ca3af;">{{ __('admin_vendors.no_messages_plain') }}</span>
                            @endif
                        </td>
                        <td style="padding:6px; vertical-align:top;">
                            @if($openCount > 0)
                                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; color:#b71c1c; background:#fef2f2;">{{ __('admin_vendors.open_count_badge', ['count' => $openCount]) }}</span>
                            @else
                                <span style="color:#9ca3af;">{{ __('admin_vendors.none_word') }}</span>
                            @endif
                        </td>
                        <td style="padding:6px; vertical-align:top; text-align:center;">
                            <a href="{{ route('admin.vendors.onboarding-workflow.show', $v->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.open_button') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_vendors.no_vendors_in_onboarding') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:8px; border-top:1px solid #f3f4f6;">
            @if($vendorsMeta['onPreviousPage'])
            <a href="{{ $vendorsMeta['prevUrl'] }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('growth.page_of', ['current' => $vendorsMeta['currentPage'], 'last' => $vendorsMeta['lastPage']]) }}</span>
            @if($vendorsMeta['hasMorePages'])
            <a href="{{ $vendorsMeta['nextUrl'] }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>

@endsection
