@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.vendor_approvals_title'))

@section('content')

{{-- NEW 14 Aug 2026 — per Chris: "i dont want Approve reject header
     [on Vendor Registration Detail]... separate program in menu
     dashboard after workflow." This screen carries exactly what used to
     sit on top of Vendor Registration Detail — identity summary, risk
     score, and the Approve/Reject/Q&A/Forward-to-Director actions —
     with links out to the full document review (Registration Detail)
     and the 9-category Risk Assessment Results screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        @if(session('success'))
        <div style="flex-shrink:0; background:#e8f5e9; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:8px;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div style="flex-shrink:0; background:#fde8e8; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10px; margin-bottom:8px;">{{ session('error') }}</div>
        @endif

        <div style="flex:1; min-height:0; overflow-y:auto;">

            {{-- Identity block — same content that used to open the Profile tab on Registration Detail. --}}
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid #f3f4f6;">
                <div style="min-width:0;">
                    <div style="font-size:14px; font-weight:700; color:#263238;">{{ $vendor->vendor_name }}</div>
                    <div style="font-size:9.5px; color:#6b7280; margin-top:2px; word-break:break-word;">{{ __('admin_vendors.profile_summary_line', ['industry' => \App\Http\Controllers\Admin\VendorController::INDUSTRIES[$vendor->industry] ?? $vendor->industry, 'email' => $vendor->vendor_email, 'phone' => $vendor->vendor_phone, 'date' => \Carbon\Carbon::parse($vendor->created_at)->format('d M Y, h:ia')]) }}</div>
                    @if($vendor->entity_type)
                    <div style="font-size:9px; color:#9ca3af; margin-top:1px;">{{ __('admin_vendors.entity_colon', ['type' => \App\Services\VendorDocumentChecklistService::ENTITY_TYPES[$vendor->entity_type] ?? $vendor->entity_type]) }}</div>
                    @endif
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
                @php $ddRisk = \App\Services\VendorDueDiligenceService::scoreBreakdown($dd); @endphp
                <div style="flex-shrink:0; text-align:right; white-space:nowrap;"><span style="display:inline-block; width:7px; height:7px; border-radius:50%; background:{{ $ddRisk['recommendation_color'] }}; margin-right:3px;"></span><span style="font-size:9px; color:{{ $ddRisk['recommendation_color'] }};">{{ $ddRisk['score'] }}/100 &middot; {{ $ddRisk['band'] }}</span></div>
                @endif
            </div>

            {{-- Quick links out to document review / risk report — the actual review happens there; this screen is for the decision. --}}
            <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:10px;">
                <a href="{{ route('admin.vendors.pending-logins.show', $vendor->vendor_id) }}" style="background:#F7FAFC; color:#374151; text-decoration:none; border:1px solid #d1d5db; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.view_full_registration_button') }}</a>
                @if($dd)
                <a href="{{ route('admin.vendors.pending-logins.risk-assessment', $vendor->vendor_id) }}" style="background:#F7FAFC; color:#374151; text-decoration:none; border:1px solid #d1d5db; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.view_risk_assessment_button') }}</a>
                @endif
            </div>

            {{-- NEW 14 Aug 2026 — per Chris: "keep a copy even if is not
                 approved... reference record for future reference if
                 similar application submit again." Surfaced right here,
                 at the moment of decision. --}}
            @if(isset($priorApplications) && $priorApplications->isNotEmpty())
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

            {{-- Action row — moved here 14 Aug 2026 from Registration Detail, unchanged behaviour/routes. --}}
            @if($vendor->login_status === 'AWAITING_PASSWORD')
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; padding-top:10px; border-top:1px solid #f3f4f6;">
                <span style="background:#e0f2fe; color:#0D5A8E; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('admin_vendors.awaiting_password_info') }}</span>
                <form method="POST" action="{{ route('admin.vendors.pending-logins.resend-verification', $vendor->vendor_id) }}" onsubmit="return confirm({{ json_encode(__('admin_vendors.confirm_resend_verification', ['email' => $vendor->vendor_email])) }});">
                    @csrf
                    <button type="submit" style="background:#fff; color:#0D5A8E; border:1px solid #0D5A8E; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.resend_verification_button') }}</button>
                </form>
                <button type="button" onclick="document.getElementById('rejectForm').style.display='flex'" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.reject_button') }}</button>
                <a href="{{ route('admin.vendors.pending-logins.qa', $vendor->vendor_id) }}" style="background:#EFF6FF; color:#1565C0; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.qa_link_label') }} {{ $vThreadCount ? '(' . $vThreadCount . ')' : '' }}</a>
            </div>
            <form id="rejectForm" method="POST" action="{{ route('admin.vendors.pending-logins.reject', $vendor->vendor_id) }}" style="display:none; gap:6px; margin-top:8px; align-items:center;">
                @csrf
                <input type="text" name="reason" required maxlength="255" placeholder="{{ __('admin_vendors.reject_reason_placeholder_vendor') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; outline:none;">
                <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.confirm_reject_button') }}</button>
            </form>
            @else
            <div style="display:flex; gap:6px; flex-wrap:wrap; padding-top:10px; border-top:1px solid #f3f4f6;">
                @if($requiredApprovals === 2 && $vendor->approved_at_1 && $vendor->approved_by_1 === $currentAdminId)
                <span title="{{ __('admin_vendors.awaiting_other_admin_tooltip') }}" style="background:#c4c9d0; color:#fff; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600;">{{ __('admin_vendors.awaiting_other_admin_badge') }}</span>
                @else
                {{-- CHANGED 14 Aug 2026 per Chris: "when click approved
                     green it will show like a payment voucher form with
                     the proper professional cover letter." Approve no
                     longer submits directly — it opens the Approval
                     Letter preview first (approval-letter.blade.php),
                     which is what actually posts to this same approve()
                     action once confirmed. --}}
                <a href="{{ route('admin.vendors.approvals.letter', $vendor->vendor_id) }}" style="background:#2e7d32; color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:700; display:inline-block;">&#10003; {{ $requiredApprovals === 2 && $vendor->approved_at_1 ? __('admin_vendors.give_2nd_approval') : __('masterfile.approve_button') }}</a>
                @endif
                <button type="button" onclick="document.getElementById('rejectForm').style.display='flex'" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.reject_button') }}</button>
                <a href="{{ route('admin.vendors.pending-logins.qa', $vendor->vendor_id) }}" style="background:#EFF6FF; color:#1565C0; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.qa_link_label') }} {{ $vThreadCount ? '(' . $vThreadCount . ')' : '' }}</a>
                <a href="{{ route('admin.vendors.pending-logins.forward-director-form', $vendor->vendor_id) }}" style="background:#F5F3FF; color:#6D28D9; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; display:inline-block;">{{ __('admin_vendors.forward_to_director_link') }}</a>
            </div>
            <form id="rejectForm" method="POST" action="{{ route('admin.vendors.pending-logins.reject', $vendor->vendor_id) }}" style="display:none; gap:6px; margin-top:8px; align-items:center;">
                @csrf
                <input type="text" name="reason" required maxlength="255" placeholder="{{ __('admin_vendors.reject_reason_placeholder_vendor') }}" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px; outline:none;">
                <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.confirm_reject_button') }}</button>
            </form>
            @endif
        </div>

        <div style="flex-shrink:0; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendors.approvals') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
        </div>
    </div>
</div>

@endsection
