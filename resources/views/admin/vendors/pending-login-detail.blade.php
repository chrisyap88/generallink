@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.vendor_registration_detail_title'))

@section('content')

{{-- NEW 12 Aug 2026 (Task #106) — per Chris: "you still have this stupid
     pop up window and i say remove all [popup] content, i say new screen
     with proper tab." This replaces the old detailModal-{vendorId}
     overlay that used to live inline on pending-logins.blade.php. Same
     content (identity/approval-progress header, Approve/Reject, and the
     Profile/SSM Documents/Video-URL-Slideshow tab bar) — just a real
     page now instead of a JS-toggled popup. The tab bar itself stays
     (that was never the complaint); only the popup wrapper is gone. Q&A
     and Forward to Director are their own pages too (links below); Risk
     Assessment Results already got this treatment first. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        {{-- CHANGED 12 Aug 2026 per Chris: "put this inside profile tab" —
             the vendor identity block (name/industry/email/phone/
             registered date/entity type/approval progress/risk dot) used
             to sit here as its own header row above the tabs; it now
             renders as the first thing inside the Profile tab pane
             itself (below). --}}

        {{-- REMOVED 14 Aug 2026 per Chris: "i dont want Approve reject
             header here." This screen is now pure document/profile
             viewing — no action buttons. Approve/Reject/Q&A/Forward to
             Director moved to their own program, Vendor Approvals
             (admin.vendors.approvals*), reached from the dashboard menu
             right below Vendor Onboarding Workflow. This slim bar just
             shows the current status and links straight there when
             something is actually actionable, so nothing is lost —
             just relocated. --}}
        @php
            $pldStatusLabel = match($vendor->login_status) { 'PENDING' => __('admin_vendors.status_pending_review'), 'AWAITING_PASSWORD' => __('admin_vendors.status_awaiting_password'), 'RESTRICTED' => __('admin_vendors.status_restricted_ready'), 'ACTIVE' => __('admin_vendors.status_active'), 'REJECTED' => __('masterfile.status_rejected'), default => $vendor->login_status };
            $pldStatusColor = match($vendor->login_status) { 'PENDING' => '#854d0e', 'AWAITING_PASSWORD' => '#1565C0', 'RESTRICTED' => '#6D28D9', 'ACTIVE' => '#2e7d32', 'REJECTED' => '#b71c1c', default => '#6b7280' };
            $pldStatusBg = match($vendor->login_status) { 'PENDING' => '#fef9c3', 'AWAITING_PASSWORD' => '#e0f2fe', 'RESTRICTED' => '#f5f3ff', 'ACTIVE' => '#f0fdf4', 'REJECTED' => '#fef2f2', default => '#f3f4f6' };
        @endphp
        <div style="flex-shrink:0; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span style="font-size:9.5px; font-weight:700; padding:5px 12px; border-radius:6px; white-space:nowrap; color:{{ $pldStatusColor }}; background:{{ $pldStatusBg }};">{{ $pldStatusLabel }}</span>
            @if(in_array($vendor->login_status, ['PENDING', 'AWAITING_PASSWORD', 'RESTRICTED'], true))
            <a href="{{ route('admin.vendors.approvals.show', $vendor->vendor_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.go_to_vendor_approvals_button') }}</a>
            @endif
        </div>

        {{-- Tab bar. CHANGED 12 Aug 2026 per Chris: "ALL Tap must in same
             row same line" — flex-wrap is explicitly nowrap, every label
             is white-space:nowrap so a long one can never force a wrap,
             and the row scrolls horizontally as a last resort rather
             than ever breaking onto a second line. Prev moved OUT of
             this row per Chris's very next message, "all Prev is on the
             bottom left corner" — it now lives in its own bar at the
             bottom of the screen (below), matching the Prev/Next bar
             convention used on every list screen in the app. --}}
        <div style="display:flex; align-items:center; flex-wrap:nowrap; gap:3px; flex-shrink:0; margin-bottom:6px; overflow-x:auto; overflow-y:hidden;">
            <button type="button" id="tabBtn-profile" onclick="pvShowTab('profile')" style="background:#1565C0; color:#fff; border:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.tab_profile') }}</button>
            <button type="button" id="tabBtn-ssm" onclick="pvShowTab('ssm')" style="background:#F7FAFC; color:#6b7280; border:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.tab_ssm_documents') }}{{ $vendor->entity_type ? __('admin_vendors.ssm_verified_suffix', ['verified' => $vVerifiedCount, 'total' => count($vRequired)]) : '' }}</button>
            <button type="button" id="tabBtn-media" onclick="pvShowTab('media')" style="background:#F7FAFC; color:#6b7280; border:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.tab_video_url_slideshow') }}{{ $vMedia->count() ? ' (' . $vMedia->count() . ')' : '' }}</button>
            @if($dd)
            @php $ddRisk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd); @endphp
            <a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}" style="background:#F7FAFC; color:#6b7280; text-decoration:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.risk_assessment_tab_link', ['score' => $ddRisk['score']]) }}</a>
            @endif
        </div>

        <div style="flex:1; min-height:0; border:1px solid #E2E8F0; border-radius:0 6px 6px 6px; padding:10px; overflow-y:auto;">

            {{-- TAB: Profile --}}
            <div id="tabPane-profile">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid #f3f4f6;">
                    <div style="min-width:0;">
                        <div style="font-size:14px; font-weight:700; color:#263238;">{{ $vendor->vendor_name }}</div>
                        <div style="font-size:9.5px; color:#6b7280; margin-top:2px; word-break:break-word;">{{ __('admin_vendors.profile_summary_line', ['industry' => \App\Http\Controllers\Admin\VendorController::INDUSTRIES[$vendor->industry] ?? $vendor->industry, 'email' => $vendor->vendor_email, 'phone' => $vendor->vendor_phone, 'date' => \Carbon\Carbon::parse($vendor->created_at)->format('d M Y, h:ia')]) }}</div>
                        @if($vendor->entity_type)
                        <div style="font-size:9px; color:#9ca3af; margin-top:1px;">{{ __('admin_vendors.entity_colon', ['type' => \App\Services\VendorDocumentChecklistService::ENTITY_TYPES[$vendor->entity_type] ?? $vendor->entity_type]) }}</div>
                        @endif
                        {{-- NEW 13 Aug 2026 — per Chris: "add another
                             contact as authorized Director." Only shown
                             when it differs from Contact 1 (the common
                             case needs no extra line) — this is who will
                             be asked to digitally accept the Vendor
                             Registration Activation Agreement. --}}
                        @php $authSig = $vendor->authorizedSignatory(); @endphp
                        @if(!$authSig['is_contact1'])
                        <div style="font-size:9px; color:#6D28D9; margin-top:1px; word-break:break-word;">{{ __('admin_vendors.authorised_signatory_line', ['name' => $authSig['name'], 'designation' => $authSig['designation'], 'email' => $authSig['email']]) }}</div>
                        @endif
                        @if($requiredApprovals === 2)
                        <div style="font-size:9px; margin-top:3px; {{ $vendor->approved_at_1 ? 'color:#2e7d32;' : 'color:#9ca3af;' }}">
                            @if($vendor->approved_at_1)
                                {!! __('admin_vendors.first_approval_note', ['name' => $approverName ?? __('admin_vendors.admin_fallback_name')]) !!}
                            @else
                                {{ __('admin_vendors.awaiting_1st_approval') }}
                            @endif
                        </div>
                        @endif
                    </div>
                    @if($dd)
                    @php
                        $ddRisk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd);
                    @endphp
                    <div style="flex-shrink:0; text-align:right; white-space:nowrap;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:{{ $ddRisk['recommendation_color'] }}; margin-right:3px;"></span><span style="font-size:9px; color:{{ $ddRisk['recommendation_color'] }};">{{ $ddRisk['score'] }}/100 &middot; {{ $ddRisk['band'] }}</span></div>
                    @endif
                </div>

                {{-- NEW 14 Aug 2026 — per Chris: "keep a copy even if is
                     not approved... reference record for future
                     reference if similar application submit again." Every
                     registration (approved, rejected, or still pending)
                     stays on file — this just surfaces any prior one that
                     shares the same email, phone, or company name. --}}
                {{-- NEW 14 Aug 2026 — per Chris's OTP-acceptance build. --}}
                <div style="font-size:9.5px; margin-bottom:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    @if($agreementAcceptance && $agreementAcceptance->accepted_at)
                    <span style="color:#166534; font-weight:600;">{{ __('admin_vendors.agreement_accepted_note', ['date' => \Carbon\Carbon::parse($agreementAcceptance->accepted_at)->format('d M Y, h:ia'), 'name' => $agreementAcceptance->accepted_by_name]) }}</span>
                    @else
                    <span style="color:#9ca3af;">{{ __('admin_vendors.agreement_not_accepted_note') }}</span>
                    @endif

                    {{-- NEW 14 Aug 2026 (3rd pass) — per Chris's SOP: "if
                         chrisyap@mybbs.com say didnt receive it is only
                         again Admin director can resend." Only shows once
                         the letter has genuinely been sent before, and
                         only to a Director Admin. --}}
                    @if($welcomeGate->director_approved_at && $myDepartment === 'DIRECTOR')
                    <form method="POST" action="{{ route('admin.vendors.approvals.resend-letter', $vendor->vendor_id) }}" onsubmit="return confirm({{ json_encode(__('admin_vendors.confirm_resend_welcome_letter', ['name' => $vendor->vendor_name])) }});">
                        @csrf
                        <button type="submit" style="background:#F7FAFC; color:#1565C0; border:1px solid #93c5fd; border-radius:12px; padding:2px 10px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.resend_welcome_letter_button') }}</button>
                    </form>
                    @endif
                </div>

                @if($priorApplications->isNotEmpty())
                <div style="background:#FFF8E1; border:1px solid #D97706; border-radius:6px; padding:8px 10px; margin-bottom:10px;">
                    <div style="font-size:9.5px; font-weight:700; color:#7a5c00; margin-bottom:4px;">{{ __('admin_vendors.prior_applications_heading', ['count' => $priorApplications->count()]) }}</div>
                    @foreach($priorApplications as $pa)
                    <div style="font-size:9px; color:#7a5c00; padding:2px 0;">
                        &middot; {{ $pa->vendor_name }} — {{ $pa->login_status }}{{ $pa->rejection_reason ? ' (' . $pa->rejection_reason . ')' : '' }}, {{ __('admin_vendors.submitted_word') }} {{ \Carbon\Carbon::parse($pa->created_at)->format('d M Y') }}
                        <a href="{{ route('admin.vendors.pending-logins.show', $pa->vendor_id) }}" style="color:#1565C0; margin-left:4px;">{{ __('admin_vendors.view_arrow_link') }}</a>
                    </div>
                    @endforeach
                </div>
                @endif

                @if($vendor->entity_type)
                    @if($profileDoc)
                    <div style="border:1px solid #f3f4f6; border-radius:8px; padding:8px 10px; margin-bottom:8px; background:{{ $profileDoc->verification_status === 'VERIFIED' ? '#f0fdf4' : ($profileDoc->verification_status === 'REJECTED' ? '#fef2f2' : '#f9fafb') }};">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
                            <div style="min-width:0; flex:1;">
                                <div style="font-size:10.5px; font-weight:600; color:#263238; word-break:break-word;">{{ $profileDoc->document_label }}</div>
                                <div style="font-size:9px; color:#9ca3af; margin-top:1px;">{{ $profileDoc->file_name }}</div>
                            </div>
                            <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; {{ $profileDoc->verification_status === 'VERIFIED' ? 'background:#dcfce7;color:#166534;' : ($profileDoc->verification_status === 'REJECTED' ? 'background:#fee2e2;color:#991b1b;' : 'background:#fef9c3;color:#854d0e;') }}">{{ $profileDoc->verification_status }}</span>
                        </div>
                        <div style="margin-top:6px;">
                            {{-- CHANGED 13 Aug 2026 per Chris: file views now
                                 open in an in-page viewer with a Close button
                                 (see fileViewerOpen() below) instead of a new
                                 browser tab, so this screen's state is never
                                 lost. --}}
                            <button type="button" onclick="fileViewerOpen(@js(route('admin.vendors.pending-logins.document-file', [$vendor->vendor_id, $profileDoc->vendor_document_id])), @js($profileDoc->file_name))" style="background:#e0f2fe; color:#1565C0; border:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.view_file_button') }}</button>
                        </div>
                    </div>
                    @else
                    <div style="font-size:10px; color:#9ca3af; padding:6px 0;">{{ __('admin_vendors.no_profile_document_note') }}</div>
                    @endif
                @else
                    @if($vendor->company_profile_document_path)
                    <div style="margin-bottom:8px;"><a href="{{ route('admin.vendors.pending-logins.document', [$vendor->vendor_id, 'profile']) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('admin_vendors.view_company_profile_link') }}</a></div>
                    @else
                    <div style="font-size:10px; color:#9ca3af; padding:6px 0;">{{ __('admin_vendors.no_company_profile_doc') }}</div>
                    @endif
                @endif
                @if($vendor->fb_page_url)
                <div><a href="{{ $vendor->fb_page_url }}" target="_blank" rel="noopener" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('admin_vendors.view_website_fb_link') }}</a></div>
                @endif
            </div>

            {{-- TAB: SSM Documents. REDESIGNED 12 Aug 2026 per Chris: "all
                 ssm in one screen, change verify to Accept reduce the box
                 size and put to right justify same row with pending,
                 Accept, Reject." Each document is now ONE compact row —
                 label/filename on the left, status badge + View File +
                 Accept + Reject all right-justified together on that
                 same row (was a separate action row below, which is what
                 made each card tall enough to force scrolling). "Verify"
                 is relabeled "Accept" everywhere it's user-facing
                 (button text and the status badge display text); the
                 underlying verification_status column/value stays
                 VERIFIED — only the label shown to Admin changed. --}}
            <div id="tabPane-ssm" style="display:none;">
                @if($vendor->entity_type)
                    {{-- CHANGED 13 Aug 2026 per Chris: "i upload 3 why you
                         say 2... all must show." The Business/Company
                         Profile document used to be excluded from this tab
                         entirely (shown only in the Profile tab, which has
                         no Accept/Reject controls at all) — a fix from 12
                         Aug made back when a document with several
                         re-uploaded versions could only show ONE file here.
                         That version problem is now solved generically for
                         every document via the ‹ N/total › stepper below,
                         so the special-case exclusion no longer serves a
                         purpose and only had the side effect of making one
                         of the required documents impossible to verify.
                         Removed — the profile document now shows and can
                         be Accepted/Rejected here like every other one; the
                         Profile tab keeps its own read-only preview. --}}
                    {{-- REDESIGNED 13 Aug 2026 per Chris: "why you only to
                         view one file in this screen where i upload some
                         many file, you show another option ask me to
                         choose which file to view in a row form no scroll
                         and have prev button." Every re-upload creates a
                         NEW row under the same document_key (versioning —
                         old files are never overwritten), so one checklist
                         item can have several files on file. This used to
                         list every one of those as its own separate row —
                         now grouped into ONE row per document_key, with a
                         compact ‹ N/total › stepper to flip through every
                         version and a single "View File" button that opens
                         whichever one is currently selected. Accept/Reject
                         always act on the latest (current) version — older
                         versions are kept only as history. --}}
                    @php
                        $vDocsGrouped = $vDocs
                            ->groupBy('document_key')
                            ->map(fn ($g) => $g->sortByDesc('created_at')->values());
                        // FIXED 13 Aug 2026 per Chris: "i verify many time
                        // and should have 3 ssm why show 2 all must show" —
                        // this used to loop over $vDocsGrouped, which only
                        // has an entry for keys the vendor actually
                        // uploaded, so a required checklist item they never
                        // uploaded just silently disappeared instead of
                        // showing as outstanding. Now loops over the FULL
                        // entity-type checklist ($vChecklist — including the
                        // profile-tagged item, see note above) so every
                        // required document always has a row — filled in if
                        // uploaded, a clear "Not Uploaded Yet" row if not.
                        $vChecklistRows = collect($vChecklist);
                        // NEW 13 Aug 2026 per Chris: "no scroll" — showing
                        // every required document (some entity types have
                        // 10-15) no longer fits this box without a real
                        // scrollbar, which breaks house rule. Same fix as
                        // everywhere else in the app: paginate instead of
                        // scroll, in fixed groups of 3 rows, with its own
                        // Prev/Next pager at the bottom of this tab —
                        // client-side (no page reload) since it's just
                        // showing/hiding rows already on the page.
                        $vDocRowIndex = 0;
                        $vRowsPerPage = 3;
                    @endphp
                    @forelse($vChecklistRows as $item)
                    @php
                        $gkey = $item['key'];
                        $group = $vDocsGrouped[$gkey] ?? null;
                        $vPageIdx = intdiv($vDocRowIndex, $vRowsPerPage);
                        $vDocRowIndex++;
                    @endphp
                    <div data-docpage="{{ $vPageIdx }}" style="{{ $vPageIdx !== 0 ? 'display:none;' : '' }}">
                    @if($group)
                    @php
                        $doc = $group->first(); // latest version — the one Accept/Reject applies to
                        $docStatusLabel = $doc->verification_status === 'VERIFIED' ? __('admin_vendors.accepted_badge') : $doc->verification_status;
                        $isAccepted = $doc->verification_status === 'VERIFIED';
                        $isRejected = $doc->verification_status === 'REJECTED';
                        // Accept shows solid green when it IS the current
                        // decision, else a muted "shadow" outline; Reject
                        // mirrors this the other way — per Chris: "show
                        // Accept in green and red in shadow mode indicate
                        // already accept, if user unclick accept then
                        // reject red back to normal red."
                        $acceptStyle = $isRejected ? 'background:#f3f4f6; color:#166534; border:1px solid #a7d7b5;' : 'background:#2e7d32; color:#fff; border:1px solid #2e7d32;';
                        $rejectStyle = $isAccepted ? 'background:#f3f4f6; color:#b71c1c; border:1px solid #f3b4b4;' : 'background:#e53935; color:#fff; border:1px solid #e53935;';
                        $fileOptions = $group->values()->map(fn ($f, $i) => [
                            'url' => route('admin.vendors.pending-logins.document-file', [$vendor->vendor_id, $f->vendor_document_id]),
                            'name' => $f->file_name,
                            'label' => $i === 0 ? 'Latest' : 'Version ' . ($group->count() - $i),
                        ])->all();
                    @endphp
                    <div style="border:1px solid #f3f4f6; border-radius:6px; padding:6px 8px; margin-bottom:5px; background:{{ $isAccepted ? '#f0fdf4' : ($isRejected ? '#fef2f2' : '#f9fafb') }};">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:nowrap;">
                            <div style="min-width:0; flex:1;">
                                <div style="font-size:10px; font-weight:600; color:#263238; word-break:break-word;">{{ $doc->document_label }}</div>
                                <div style="font-size:8.5px; color:#9ca3af; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="ssmFileName-{{ $gkey }}">{{ $fileOptions[0]['name'] }}{{ count($fileOptions) > 1 ? ' · ' . $fileOptions[0]['label'] : '' }}</div>
                            </div>
                            <div style="flex-shrink:0; display:flex; align-items:center; gap:4px;">
                                @if(count($fileOptions) > 1)
                                <button type="button" onclick="ssmStep('{{ $gkey }}', -1)" style="background:#eef2f7; color:#546E7A; border:none; border-radius:4px; width:16px; height:19px; font-size:10px; font-weight:700; cursor:pointer; line-height:1; padding:0;">&lsaquo;</button>
                                <span id="ssmStepLabel-{{ $gkey }}" style="font-size:8.5px; color:#6b7280; white-space:nowrap; min-width:26px; text-align:center;">1/{{ count($fileOptions) }}</span>
                                <button type="button" onclick="ssmStep('{{ $gkey }}', 1)" style="background:#eef2f7; color:#546E7A; border:none; border-radius:4px; width:16px; height:19px; font-size:10px; font-weight:700; cursor:pointer; line-height:1; padding:0;">&rsaquo;</button>
                                @endif
                                <button type="button" id="ssmViewBtn-{{ $gkey }}" data-files="{{ json_encode($fileOptions) }}" data-idx="0" onclick="ssmViewCurrent('{{ $gkey }}')" style="background:#e0f2fe; color:#1565C0; border:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">&#128196; View File</button>
                                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; {{ $isAccepted ? 'background:#dcfce7;color:#166534;' : ($isRejected ? 'background:#fee2e2;color:#991b1b;' : 'background:#fef9c3;color:#854d0e;') }}">{{ $docStatusLabel }}</span>
                                <form method="POST" action="{{ route('admin.vendors.pending-logins.document-verify', [$vendor->vendor_id, $doc->vendor_document_id]) }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="decision" value="VERIFIED">
                                    <button type="submit" style="{{ $acceptStyle }} border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.accept_button') }}</button>
                                </form>
                                <button type="button" onclick="var n=prompt({{ json_encode(__('admin_vendors.reject_document_prompt')) }}); if(n!==null){ document.getElementById('rejnote-{{ $doc->vendor_document_id }}').value=n; document.getElementById('rejform-{{ $doc->vendor_document_id }}').submit(); }" style="{{ $rejectStyle }} border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.reject_icon_button') }}</button>
                                <form id="rejform-{{ $doc->vendor_document_id }}" method="POST" action="{{ route('admin.vendors.pending-logins.document-verify', [$vendor->vendor_id, $doc->vendor_document_id]) }}" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="decision" value="REJECTED">
                                    <input type="hidden" id="rejnote-{{ $doc->vendor_document_id }}" name="note" value="">
                                </form>
                            </div>
                        </div>
                        @if($doc->ai_check_status && $doc->ai_check_status !== 'PENDING')
                        <div style="font-size:8.5px; margin-top:4px; padding:3px 6px; border-radius:5px; {{ $doc->ai_check_status === 'VERIFIED' ? 'background:#eff6ff;color:#1e40af;' : ($doc->ai_check_status === 'FAILED' ? 'background:#fff7ed;color:#9a3412;' : 'background:#f3f4f6;color:#6b7280;') }}">
                            {{ $doc->ai_check_status === 'VERIFIED' ? __('admin_vendors.ai_verified_note') : ($doc->ai_check_status === 'FAILED' ? __('admin_vendors.ai_name_mismatch_note') : __('admin_vendors.ai_unable_read_note')) }}
                            @if($doc->ai_check_note) — {{ $doc->ai_check_note }} @endif
                        </div>
                        @elseif($doc->ai_check_status === 'PENDING')
                        <div style="font-size:8.5px; margin-top:4px; color:#9ca3af;">{{ __('admin_vendors.ai_verification_pending_note') }}</div>
                        @endif
                        @if($doc->verification_note)
                        <div style="font-size:9px; color:#b71c1c; margin-top:3px;">{{ __('admin_vendors.note_colon', ['note' => $doc->verification_note]) }}</div>
                        @endif
                    </div>
                    @else
                    {{-- Required checklist item with no upload on file yet. --}}
                    <div style="border:1px dashed #d1d5db; border-radius:6px; padding:6px 8px; margin-bottom:5px; background:#fafafa;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:nowrap;">
                            <div style="min-width:0; flex:1;">
                                <div style="font-size:10px; font-weight:600; color:#6b7280; word-break:break-word;">{{ $item['label'] }}</div>
                                <div style="font-size:8.5px; color:#9ca3af; margin-top:1px;">{{ __('admin_vendors.not_uploaded_yet') }}</div>
                            </div>
                            <div style="flex-shrink:0;">
                                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; {{ $item['tier'] === 'MANDATORY' ? 'background:#fee2e2;color:#991b1b;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ \App\Services\VendorDocumentChecklistService::TIER_LABELS[$item['tier']] }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
                    </div>
                    @empty
                    <div style="font-size:10px; color:#9ca3af; padding:10px 0;">{{ __('admin_vendors.no_documents_required') }}</div>
                    @endforelse

                    {{-- Any uploaded document whose key isn't on the
                         current checklist (e.g. an older key from before a
                         checklist change) — still shown so a real upload
                         can never silently disappear from this screen. --}}
                    @php
                        $vChecklistKeys = $vChecklistRows->pluck('key')->all();
                        $vExtraGroups = $vDocsGrouped->except($vChecklistKeys);
                    @endphp
                    @if($vExtraGroups->isNotEmpty())
                    @php $vPageIdx = intdiv($vDocRowIndex, $vRowsPerPage); $vDocRowIndex++; @endphp
                    <div data-docpage="{{ $vPageIdx }}" style="{{ $vPageIdx !== 0 ? 'display:none;' : '' }}">
                    <div style="font-size:9px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin:8px 0 4px;">{{ __('admin_vendors.other_files_on_file') }}</div>
                    @foreach($vExtraGroups as $gkey => $group)
                    @php
                        $doc = $group->first();
                        $docStatusLabel = $doc->verification_status === 'VERIFIED' ? __('admin_vendors.accepted_badge') : $doc->verification_status;
                        $isAccepted = $doc->verification_status === 'VERIFIED';
                        $isRejected = $doc->verification_status === 'REJECTED';
                        $acceptStyle = $isRejected ? 'background:#f3f4f6; color:#166534; border:1px solid #a7d7b5;' : 'background:#2e7d32; color:#fff; border:1px solid #2e7d32;';
                        $rejectStyle = $isAccepted ? 'background:#f3f4f6; color:#b71c1c; border:1px solid #f3b4b4;' : 'background:#e53935; color:#fff; border:1px solid #e53935;';
                        $fileOptions = $group->values()->map(fn ($f, $i) => [
                            'url' => route('admin.vendors.pending-logins.document-file', [$vendor->vendor_id, $f->vendor_document_id]),
                            'name' => $f->file_name,
                            'label' => $i === 0 ? 'Latest' : 'Version ' . ($group->count() - $i),
                        ])->all();
                    @endphp
                    <div style="border:1px solid #f3f4f6; border-radius:6px; padding:6px 8px; margin-bottom:5px; background:{{ $isAccepted ? '#f0fdf4' : ($isRejected ? '#fef2f2' : '#f9fafb') }};">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; flex-wrap:nowrap;">
                            <div style="min-width:0; flex:1;">
                                <div style="font-size:10px; font-weight:600; color:#263238; word-break:break-word;">{{ $doc->document_label }}</div>
                                <div style="font-size:8.5px; color:#9ca3af; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" id="ssmFileName-{{ $gkey }}">{{ $fileOptions[0]['name'] }}{{ count($fileOptions) > 1 ? ' · ' . $fileOptions[0]['label'] : '' }}</div>
                            </div>
                            <div style="flex-shrink:0; display:flex; align-items:center; gap:4px;">
                                @if(count($fileOptions) > 1)
                                <button type="button" onclick="ssmStep('{{ $gkey }}', -1)" style="background:#eef2f7; color:#546E7A; border:none; border-radius:4px; width:16px; height:19px; font-size:10px; font-weight:700; cursor:pointer; line-height:1; padding:0;">&lsaquo;</button>
                                <span id="ssmStepLabel-{{ $gkey }}" style="font-size:8.5px; color:#6b7280; white-space:nowrap; min-width:26px; text-align:center;">1/{{ count($fileOptions) }}</span>
                                <button type="button" onclick="ssmStep('{{ $gkey }}', 1)" style="background:#eef2f7; color:#546E7A; border:none; border-radius:4px; width:16px; height:19px; font-size:10px; font-weight:700; cursor:pointer; line-height:1; padding:0;">&rsaquo;</button>
                                @endif
                                <button type="button" id="ssmViewBtn-{{ $gkey }}" data-files="{{ json_encode($fileOptions) }}" data-idx="0" onclick="ssmViewCurrent('{{ $gkey }}')" style="background:#e0f2fe; color:#1565C0; border:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">&#128196; View File</button>
                                <span style="font-size:9px; font-weight:700; padding:2px 8px; border-radius:10px; white-space:nowrap; {{ $isAccepted ? 'background:#dcfce7;color:#166534;' : ($isRejected ? 'background:#fee2e2;color:#991b1b;' : 'background:#fef9c3;color:#854d0e;') }}">{{ $docStatusLabel }}</span>
                                <form method="POST" action="{{ route('admin.vendors.pending-logins.document-verify', [$vendor->vendor_id, $doc->vendor_document_id]) }}" style="display:inline;">
                                    @csrf
                                    <input type="hidden" name="decision" value="VERIFIED">
                                    <button type="submit" style="{{ $acceptStyle }} border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.accept_button') }}</button>
                                </form>
                                <button type="button" onclick="var n=prompt({{ json_encode(__('admin_vendors.reject_document_prompt')) }}); if(n!==null){ document.getElementById('rejnote-{{ $doc->vendor_document_id }}').value=n; document.getElementById('rejform-{{ $doc->vendor_document_id }}').submit(); }" style="{{ $rejectStyle }} border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.reject_icon_button') }}</button>
                                <form id="rejform-{{ $doc->vendor_document_id }}" method="POST" action="{{ route('admin.vendors.pending-logins.document-verify', [$vendor->vendor_id, $doc->vendor_document_id]) }}" style="display:none;">
                                    @csrf
                                    <input type="hidden" name="decision" value="REJECTED">
                                    <input type="hidden" id="rejnote-{{ $doc->vendor_document_id }}" name="note" value="">
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                    </div>
                    @endif

                    {{-- Own Prev/Next pager for this tab's rows — per
                         Chris: "no scroll." Hidden entirely when everything
                         already fits on one page. --}}
                    @php $vDocTotalPages = (int) ceil(max($vDocRowIndex, 1) / $vRowsPerPage); @endphp
                    @if($vDocTotalPages > 1)
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; padding-top:6px; border-top:1px solid #f3f4f6;">
                        <button type="button" id="ssmDocPrevBtn" onclick="ssmDocPage(-1)" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.prev') }}</button>
                        <span id="ssmDocPageLabel" style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => 1, 'last' => $vDocTotalPages]) }}</span>
                        <button type="button" id="ssmDocNextBtn" onclick="ssmDocPage(1)" data-total="{{ $vDocTotalPages }}" style="background:#1565C0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('network.next') }}</button>
                    </div>
                    @endif
                @else
                    <a href="{{ route('admin.vendors.pending-logins.document', [$vendor->vendor_id, 'ssm']) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('admin_vendors.view_ssm_certificate_link') }}</a>
                @endif
            </div>

            {{-- TAB: Video/URL/Slideshow --}}
            <div id="tabPane-media" style="display:none;">
                @forelse($vMedia as $m)
                <div style="border:1px solid #f3f4f6; border-radius:8px; padding:8px 10px; margin-bottom:6px; background:#f9fafb;">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                        <div style="min-width:0; flex:1;">
                            <div style="font-size:10.5px; font-weight:600; color:#263238;">{{ match($m->media_type) { 'VIDEO' => __('admin_vendors.media_type_video'), 'SLIDESHOW' => __('admin_vendors.media_type_slideshow'), 'FLYER' => __('admin_vendors.media_type_flyer'), 'LINK' => __('admin_vendors.media_type_link'), default => $m->media_type } }}</div>
                            @if($m->file_name)
                            <div style="font-size:9px; color:#9ca3af; margin-top:1px; word-break:break-word;">{{ $m->file_name }}</div>
                            @elseif($m->external_url)
                            <div style="font-size:9px; color:#9ca3af; margin-top:1px; word-break:break-word;">{{ $m->external_url }}</div>
                            @endif
                        </div>
                        @if($m->media_type === 'LINK')
                        <a href="{{ $m->external_url }}" target="_blank" rel="noopener" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; white-space:nowrap;">{{ __('admin_vendors.open_link_button') }}</a>
                        @else
                        <a href="{{ route('admin.vendors.pending-logins.media-file', [$vendor->vendor_id, $m->media_id]) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 9px; font-size:9px; font-weight:600; white-space:nowrap;">{{ __('admin_vendors.view_file_button') }}</a>
                        @endif
                    </div>
                </div>
                @empty
                <div style="font-size:10px; color:#9ca3af; padding:10px 0;">{{ __('admin_vendors.no_media_note') }}</div>
                @endforelse
            </div>

        </div>

        {{-- NEW 12 Aug 2026 per Chris: "all Prev is on the bottom left
             corner" — same bottom-left Prev-bar convention every other
             list/detail screen in the app uses. --}}
        <div style="flex-shrink:0; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendors.pending-logins') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
        </div>
    </div>
</div>

{{-- NEW 13 Aug 2026 per Chris: "when a file view on the screen you
     should have a close button then back to profile screen you should
     maintain all the form in this screen" — File "View File" buttons no
     longer open a new browser tab; they open this in-page overlay
     instead, so closing it returns to exactly the same tab/scroll/state
     on this screen, nothing reloads. Placed outside every tab pane
     (which toggle display:none) so it still renders regardless of which
     tab is active. Handles images directly and PDFs via an iframe. --}}
<div id="fileViewerOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.75); z-index:9999; align-items:center; justify-content:center; padding:24px; box-sizing:border-box;">
    <div style="background:#fff; border-radius:8px; width:100%; max-width:820px; height:100%; max-height:88vh; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:6px 10px; border-bottom:1px solid #f3f4f6;">
            <span id="fileViewerTitle" style="font-size:10.5px; font-weight:600; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
            <button type="button" onclick="fileViewerClose()" style="background:#f3f4f6; color:#374151; border:none; border-radius:6px; padding:4px 12px; font-size:11px; font-weight:600; cursor:pointer; flex-shrink:0; margin-left:8px;">{{ __('admin_vendors.close_button') }}</button>
        </div>
        <div id="fileViewerBody" style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center; overflow:auto; background:#111827;"></div>
    </div>
</div>

<script>
function fileViewerOpen(url, name) {
    var overlay = document.getElementById('fileViewerOverlay');
    var body = document.getElementById('fileViewerBody');
    var title = document.getElementById('fileViewerTitle');
    var ext = (name || url).split('.').pop().toLowerCase().split('?')[0];
    title.textContent = name || '';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
        body.innerHTML = '<img src="' + url + '" style="max-width:100%; max-height:100%; object-fit:contain;">';
    } else {
        body.innerHTML = '<iframe src="' + url + '" style="width:100%; height:100%; border:none; background:#fff;"></iframe>';
    }
    overlay.style.display = 'flex';
}
function fileViewerClose() {
    document.getElementById('fileViewerOverlay').style.display = 'none';
    document.getElementById('fileViewerBody').innerHTML = '';
}
// NEW 13 Aug 2026 per Chris: "show another option ask me to choose
// which file to view in a row form no scroll and have prev button" —
// each SSM document row can have several uploaded versions (every
// re-upload adds a new one, never overwrites); this steps through them
// without leaving the row or the page.
function ssmStep(key, delta) {
    var btn = document.getElementById('ssmViewBtn-' + key);
    var files = JSON.parse(btn.getAttribute('data-files'));
    var idx = parseInt(btn.getAttribute('data-idx'), 10);
    idx = (idx + delta + files.length) % files.length;
    btn.setAttribute('data-idx', idx);
    var lbl = document.getElementById('ssmStepLabel-' + key);
    if (lbl) { lbl.textContent = (idx + 1) + '/' + files.length; }
    var nameEl = document.getElementById('ssmFileName-' + key);
    if (nameEl) { nameEl.textContent = files[idx].name + (files.length > 1 ? ' · ' + files[idx].label : ''); }
}
function ssmViewCurrent(key) {
    var btn = document.getElementById('ssmViewBtn-' + key);
    var files = JSON.parse(btn.getAttribute('data-files'));
    var idx = parseInt(btn.getAttribute('data-idx'), 10);
    fileViewerOpen(files[idx].url, files[idx].name);
}

// NEW 13 Aug 2026 per Chris: "no scroll" — some entity types have far
// more required documents than fit in this box, so rows are grouped
// into pages of 3 ([data-docpage] on each row's wrapper, set in Blade)
// and this just shows/hides them client-side — no page reload, no real
// scrollbar, Prev/Next only, same convention as every list screen.
var PLD_I18N = { pageOfTemplate: @json(__('growth.page_of')) };
var ssmDocPageState = 0;
function ssmDocPage(delta) {
    var nextBtn = document.getElementById('ssmDocNextBtn');
    var total = nextBtn ? parseInt(nextBtn.getAttribute('data-total'), 10) : 1;
    ssmDocPageState = Math.max(0, Math.min(total - 1, ssmDocPageState + delta));
    document.querySelectorAll('#tabPane-ssm [data-docpage]').forEach(function (row) {
        row.style.display = (parseInt(row.getAttribute('data-docpage'), 10) === ssmDocPageState) ? '' : 'none';
    });
    var label = document.getElementById('ssmDocPageLabel');
    if (label) { label.textContent = PLD_I18N.pageOfTemplate.replace(':current', ssmDocPageState + 1).replace(':last', total); }
    var prevBtn = document.getElementById('ssmDocPrevBtn');
    if (prevBtn) { prevBtn.style.background = ssmDocPageState > 0 ? '#1565C0' : '#1565C0'; }
    if (nextBtn) { nextBtn.style.background = ssmDocPageState < total - 1 ? '#1565C0' : '#1565C0'; }
}

function pvShowTab(tab) {
    ['profile', 'ssm', 'media'].forEach(function(t) {
        var pane = document.getElementById('tabPane-' + t);
        var btn = document.getElementById('tabBtn-' + t);
        if (pane) { pane.style.display = (t === tab) ? 'block' : 'none'; }
        if (btn) {
            btn.style.background = (t === tab) ? '#1565C0' : '#F7FAFC';
            btn.style.color = (t === tab) ? '#fff' : '#6b7280';
        }
    });
}

// NEW 12 Aug 2026 per Chris: "why i click in ssm document accept it
// automatically go back previous screen." Accepting/rejecting a
// document is a full page reload (a real form POST), and every tab used
// to default back to Profile on load — reopen whichever tab the URL
// says to (?tab=ssm), set by the controller's redirect after Accept/
// Reject, so Admin lands back exactly where they were working.
(function () {
    var wantedTab = new URLSearchParams(window.location.search).get('tab');
    if (wantedTab === 'ssm' || wantedTab === 'media') {
        pvShowTab(wantedTab);
    }
})();
</script>

@endsection
