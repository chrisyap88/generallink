@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.pending_login_approval_title'))

@section('content')

{{-- NEW 8 Aug 2026 (Task #92) — self-registered vendors awaiting Admin
     approval before they can sign in to the Vendor Portal. Same no-scroll
     compact style as WhatsApp Audit Log / GLADE Analytics.

     REDESIGNED 12 Aug 2026 per Chris: "each pending vendor cannot in a
     stacked card, it will display as few columns like date submission,
     vendor name, nature of business contact 1 name and details, state,
     follow view details, show profile folder, attached files folder
     ssm, attached folder video/url/slide show card and show all the
     attachment as folder tap. all in one screen no scroll." The old
     one-stacked-card-per-vendor list is now this compact row table.

     REDESIGNED AGAIN 12 Aug 2026 (Task #106) per Chris: "you still have
     this stupid pop up window and i say remove all popup content, i say
     new screen with proper tab." "View Details" used to open a JS
     overlay modal built inline on this same page — it's now a plain
     link to a real page (pending-login-detail.blade.php, via
     VendorLoginApprovalController::show()) with its own Profile/SSM
     Documents/Video-URL-Slideshow tab bar, Q&A screen, Forward-to-
     Director screen, and (already done earlier the same day) its own
     Risk Assessment Results screen. This list screen is now purely the
     table + pagination + the Approval Guide tooltip. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    {{-- CHANGED 12 Aug 2026 per Chris: extra right padding above (76px vs
         the usual 16px) so the row's "View Details" button and the
         pagination "Next" button never sit under the fixed Carolyn
         widget (bottom:20px; right:20px; 42px circle) — that overlap was
         what he saw as "truncated below". --}}
    <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('admin.vendors.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('admin_vendors.vendor_maintenance_link') }}</a>

        {{-- REDESIGNED 12 Aug 2026 per Chris: "dont show the yellow box as
             default, show as tips when cursor point to the radio button
             call approval guide." The 5-tip checklist no longer sits
             permanently on screen — it only appears as a hover tooltip
             off this "Approval Guide" trigger. --}}
        <div style="position:relative;">
            <span onmouseenter="document.getElementById('approvalGuideTip').style.display='block'" onmouseleave="document.getElementById('approvalGuideTip').style.display='none'" style="cursor:default; background:#FFF8E1; border:1px solid #D97706; color:#92400E; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.approval_guide_trigger') }}</span>
            <div id="approvalGuideTip" style="display:none; position:absolute; top:28px; right:0; width:380px; max-width:70vw; background:#fff8e1; border:1px solid #D97706; border-radius:8px; padding:8px 10px; box-shadow:0 8px 24px rgba(0,0,0,.18); z-index:60;">
                <div style="font-size:9.5px; font-weight:700; color:#7a5c00; margin-bottom:3px;">{{ __('admin_vendors.approval_guide_heading') }}</div>
                <div style="font-size:9.5px; color:#7a5c00; line-height:1.5;">
                    <div>{!! __('admin_vendors.approval_guide_tip1') !!}</div>
                    <div>{!! __('admin_vendors.approval_guide_tip2') !!}</div>
                    <div>{!! __('admin_vendors.approval_guide_tip3') !!}</div>
                    <div>{!! __('admin_vendors.approval_guide_tip4') !!}</div>
                    <div>{!! __('admin_vendors.approval_guide_tip5') !!}</div>
                </div>
            </div>
        </div>
    </div>

    @if(session('success'))
    <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    {{-- HIDDEN 12 Aug 2026 per Chris: "no need to show this. you only
         show when in approval screen which i will advise later." Backend
         (routes/columns/logic) is untouched — only the two settings
         forms are commented out of this list screen's render. They will
         resurface once Chris specs the dedicated approval screen.
    <form method="POST" action="{{ route('admin.vendors.approval-settings') }}" style="flex-shrink:0; display:flex; align-items:center; gap:8px; background:#EFF6FF; border-left:3px solid #1565C0; border-radius:6px; padding:6px 10px; margin-bottom:8px;">
        @csrf
        <span style="font-size:9.5px; color:#1e3a5f; font-weight:600;">Admin approvals required per vendor:</span>
        <select name="required_count" onchange="this.form.submit();" style="border:1px solid #d1d5db; border-radius:5px; padding:2px 6px; font-size:9.5px; background:#fff;">
            <option value="1" {{ $requiredApprovals === 1 ? 'selected' : '' }}>1 (single Admin)</option>
            <option value="2" {{ $requiredApprovals === 2 ? 'selected' : '' }}>2 (two different Admins)</option>
        </select>
        <span style="font-size:9px; color:#6b7280;">Changing this only takes effect for approvals given from now on.</span>
    </form>

    <form method="POST" action="{{ route('admin.vendors.review-director-email') }}" style="flex-shrink:0; display:flex; align-items:center; gap:8px; background:#F5F3FF; border-left:3px solid #6D28D9; border-radius:6px; padding:6px 10px; margin-bottom:8px;">
        @csrf
        <span style="font-size:9.5px; color:#4c1d95; font-weight:600;">Director's email (for "Forward to Director"):</span>
        <input type="email" name="director_email" value="{{ $directorEmail }}" placeholder="director@company.com" style="border:1px solid #d1d5db; border-radius:5px; padding:2px 6px; font-size:9.5px; width:220px;">
        <button type="submit" style="background:#6D28D9; color:#fff; border:none; border-radius:5px; padding:3px 10px; font-size:9px; font-weight:600; cursor:pointer;">Save</button>
    </form>
    --}}

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            @if($pendingVendors->isEmpty())
            <div style="padding:24px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_vendors.no_pending_approval') }}</div>
            @else
            <table style="width:100%; border-collapse:collapse; font-size:10px; table-layout:fixed;">
                <colgroup>
                    <col style="width:9%;"><col style="width:16%;"><col style="width:12%;"><col style="width:18%;">
                    <col style="width:19%;"><col style="width:10%;"><col style="width:16%;">
                </colgroup>
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_date_submitted') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_vendor_name') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_status') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_nature_of_business') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_contact1') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_state') }}</th>
                        <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingVendors as $v)
                    @php
                        $dd = $assessments[$v->vendor_id] ?? null;
                        // NEW 13 Aug 2026 per Chris: "show as long as long
                        // not approved" — this list now mixes PENDING,
                        // AWAITING_PASSWORD and RESTRICTED rows, so each
                        // needs its own status badge to stay readable.
                        $vsLabel = match($v->login_status) { 'PENDING' => __('admin_vendors.status_pending_review'), 'AWAITING_PASSWORD' => __('admin_vendors.status_awaiting_password'), 'RESTRICTED' => __('admin_vendors.status_restricted_ready'), default => $v->login_status };
                        $vsColor = match($v->login_status) { 'PENDING' => '#854d0e', 'AWAITING_PASSWORD' => '#1565C0', 'RESTRICTED' => '#6D28D9', default => '#6b7280' };
                        $vsBg = match($v->login_status) { 'PENDING' => '#fef9c3', 'AWAITING_PASSWORD' => '#e0f2fe', 'RESTRICTED' => '#f5f3ff', default => '#f3f4f6' };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px; vertical-align:top; color:#6b7280;">{{ \Carbon\Carbon::parse($v->created_at)->format('d M Y') }}</td>
                        <td style="padding:6px; vertical-align:top;">
                            <div style="font-weight:700; color:#263238; word-break:break-word;">{{ $v->vendor_name }}</div>
                            @if($dd)
                            @php
                                $ddRisk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd);
                            @endphp
                            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:{{ $ddRisk['recommendation_color'] }}; margin-right:3px;"></span><span style="font-size:8.5px; color:{{ $ddRisk['recommendation_color'] }};">{{ $ddRisk['score'] }}/100 &middot; {{ $ddRisk['band'] }}</span>
                            @endif
                        </td>
                        <td style="padding:6px; vertical-align:top;"><span style="font-size:8.5px; font-weight:700; padding:2px 6px; border-radius:8px; white-space:nowrap; color:{{ $vsColor }}; background:{{ $vsBg }};">{{ $vsLabel }}</span></td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $v->nature_of_business ?: '—' }}</td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $v->pic_name }}<br><span style="font-size:9px; color:#9ca3af;">{{ $v->pic_phone }} &middot; {{ $v->pic_email ?? $v->vendor_email }}</span></td>
                        <td style="padding:6px; vertical-align:top; color:#4b5563; word-break:break-word;">{{ $v->vendor_state ?: '—' }}</td>
                        <td style="padding:6px; vertical-align:top; text-align:center;">
                            <a href="{{ route('admin.vendors.pending-logins.show', $v->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; white-space:nowrap; display:inline-block;">{{ __('admin_vendors.view_details_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>

        {{-- NEW 8 Aug 2026 — real pagination, same bottom blue Prev/Next
             pattern used on every other list screen in the app. --}}
        @if($pendingVendors->hasPages() || $pendingVendors->total() > 0)
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            @if($pendingVendors->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pendingVendors->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('admin_vendors.pending_page_of', ['current' => $pendingVendors->currentPage(), 'last' => $pendingVendors->lastPage(), 'total' => $pendingVendors->total()]) }}</span>
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
