@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.vendor_approvals_title'))

@section('content')

{{-- NEW 14 Aug 2026 — per Chris: "when click approved green it will
     show like a payment voucher form with the proper professional cover
     letter... show them all login email address and temporary login
     password and introduce our AI Agent Carolyn and explain about the
     fees and prepare a sample agreement for me to preview." This is that
     preview — nothing is approved yet just by viewing this page; the
     "Confirm & Send Approval" button at the bottom posts to the real
     approve() action, which finalizes the vendor AND emails this exact
     letter (see approval-letter-email.blade.php, same content, email-
     safe markup) with the agreement PDF attached.

     Login credentials note: this shows the login EMAIL only, not a
     "temporary password" — by this final-approval step the vendor has
     already set their own password (during the earlier AWAITING_PASSWORD
     -> RESTRICTED step), so there is no temporary password left to
     disclose without undermining that self-set-password design. Flagged
     to Chris in chat alongside this build.

     REDESIGNED 14 Aug 2026 — per Chris: "move the approval box end of
     the letter and show in one screen in A4 portrait layout, if screen
     not enough make it like a print preview style can zoom in and out."
     The letter is now a fixed 794x?px "page" (A4 proportions at 96dpi)
     rendered inside a grey print-preview backdrop, auto-scaled with a
     transform so the WHOLE page is visible on load without scrolling —
     same idea as a PDF viewer's "Fit to Screen." Zoom +/- buttons let
     Chris magnify it to actually read the fine print; if he zooms in
     past what fits, the backdrop area (only that area, not the whole
     screen) scrolls — same established exception already used for the
     SSM Documents / Amendment Log tabs elsewhere. The Sales/Finance/
     Director tick-box now sits at the very end of the page, right after
     the letter's closing signature — clearly marked "Internal Use Only"
     since it is NOT part of what actually gets emailed to the vendor
     (approval-letter-email.blade.php has no such box). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        <div style="flex-shrink:0; margin-bottom:8px; padding-bottom:8px; border-bottom:1px solid #f3f4f6; display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('admin_vendors.approval_letter_preview_heading') }}</div>
                <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.approval_letter_preview_note', ['name' => $vendor->vendor_name]) }}</div>
            </div>
            {{-- Print-preview style zoom controls, magnifying-glass icon included per Chris's request. --}}
            <div style="display:flex; align-items:center; gap:4px; flex-shrink:0;">
                <span style="font-size:12px;">&#128269;</span>
                <button type="button" onclick="glZoomBy(-0.1)" style="width:22px; height:22px; border:1px solid #d1d5db; background:#F7FAFC; border-radius:4px; font-size:12px; font-weight:700; cursor:pointer; color:#374151;">&minus;</button>
                <span id="gl-zoom-pct" style="font-size:9.5px; color:#374151; width:36px; text-align:center;">100%</span>
                <button type="button" onclick="glZoomBy(0.1)" style="width:22px; height:22px; border:1px solid #d1d5db; background:#F7FAFC; border-radius:4px; font-size:12px; font-weight:700; cursor:pointer; color:#374151;">+</button>
                <button type="button" onclick="glFitZoom()" style="border:1px solid #d1d5db; background:#F7FAFC; border-radius:4px; padding:3px 8px; font-size:9px; font-weight:600; cursor:pointer; color:#374151; margin-left:4px;">{{ __('admin_vendors.fit_to_screen_button') }}</button>
            </div>
        </div>

        {{-- Print-preview backdrop — this area (and only this area)
             scrolls if the zoomed page is taller/wider than it. --}}
        <div id="gl-a4-wrap" style="flex:1; min-height:0; overflow:auto; background:#e5e7eb; border:1px solid #d1d5db; border-radius:6px; padding:18px 0 26px 0; display:flex; flex-direction:column; align-items:center;">

            <div id="gl-a4-page" style="width:794px; flex-shrink:0; background:#fff; box-shadow:0 2px 12px rgba(0,0,0,.18); padding:56px 64px; box-sizing:border-box; transform-origin:top center; font-family:'Times New Roman', serif; color:#1a1a1a; font-size:12px; line-height:1.6;">

                <div style="text-align:center; margin-bottom:4px;">
                    <div style="font-size:20px; font-weight:700; letter-spacing:2px; color:#0D5A8E;">GLADE</div>
                    <div style="font-size:9.5px; color:#6b7280;">{{ __('admin_vendors.letter_operated_by') }}</div>
                    <div style="font-size:9px; color:#9ca3af;">No 5, Jalan Aman Perdana 7C/KU5, Taman Aman Perdana, 41050 Meru, Kapar, Selangor</div>
                </div>

                <div style="text-align:center; background:#0D5A8E; color:#fff; font-weight:700; letter-spacing:1px; padding:6px; margin:14px 0; font-size:12px;">{{ __('admin_vendors.letter_welcome_heading') }}</div>

                <div style="margin-bottom:10px;">{{ __('admin_vendors.letter_date_label') }} {{ now()->format('d F Y') }}</div>

                <div style="margin-bottom:2px;"><strong>{{ __('admin_vendors.letter_to_label') }}</strong> {{ $vendor->vendor_name }}</div>
                @if($vendor->vendor_address)
                <div style="margin-bottom:2px; margin-left:36px; white-space:pre-line;">{{ $vendor->vendor_address }}</div>
                @endif
                <div style="margin-bottom:2px;"><strong>{{ __('admin_vendors.letter_attention_label') }}</strong> {{ $vendor->pic_name ?: __('admin_vendors.letter_the_management') }}{{ $vendor->pic_designation ? ' (' . $vendor->pic_designation . ')' : '' }}</div>
                @php $ccNames = array_values(array_filter([$vendor->contact2_name ?? null, $vendor->contact3_name ?? null])); @endphp
                @if(!empty($ccNames))
                <div style="margin-bottom:10px;"><strong>{{ __('admin_vendors.letter_cc_label') }}</strong> {{ implode(', ', $ccNames) }}</div>
                @endif

                <div style="margin-bottom:14px;"><strong>{{ __('admin_vendors.letter_subject') }}</strong></div>

                <p>{{ __('admin_vendors.letter_dear', ['name' => $vendor->pic_name ?: __('admin_vendors.letter_sir_madam')]) }}</p>

                {!! '<p>' . __('admin_vendors.letter_intro_para', ['name' => '<strong>' . e($vendor->vendor_name) . '</strong>']) . '</p>' !!}

                <p><strong>{{ __('admin_vendors.letter_your_account_heading') }}</strong><br>
                {{ __('admin_vendors.letter_login_email_label') }} {{ $vendor->pic_email ?: $vendor->vendor_email }}<br>
                {{ __('admin_vendors.letter_password_note') }}</p>

                <p><strong>{{ __('admin_vendors.letter_carolyn_heading') }}</strong><br>
                {{ __('admin_vendors.letter_carolyn_para') }}</p>

                <p><strong>{{ __('admin_vendors.letter_fees_heading') }}</strong></p>
                <table style="width:100%; border-collapse:collapse; margin-bottom:10px; font-size:11.5px;">
                    <tr style="background:#f0f9ff;">
                        <td style="border:1px solid #cbd5e1; padding:5px 8px; font-weight:700;">{{ __('vendor.col_fee_item') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px; font-weight:700;">{{ __('vendor.col_amount') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px; font-weight:700;">{{ __('vendor.col_frequency') }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">{{ __('vendor.activation_fee_label') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ACTIVATION_FEE, 2) }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">{{ __('vendor.frequency_onetime') }}</td>
                    </tr>
                    <tr>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">{{ __('vendor.maintenance_fee_label') }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">RM {{ number_format(\App\Http\Controllers\Admin\VendorLoginApprovalController::ANNUAL_FEE, 2) }}</td>
                        <td style="border:1px solid #cbd5e1; padding:5px 8px;">{{ __('vendor.frequency_annual') }}</td>
                    </tr>
                </table>

                {!! '<p>' . __('admin_vendors.letter_payment_terms_preview') . '</p>' !!}

                <p>{{ __('admin_vendors.letter_questions_para') }}</p>

                <p style="margin-top:20px;">{{ __('admin_vendors.letter_yours_sincerely') }}<br>
                <img src="{{ asset('images/ywj-signature.png') }}" alt="Signature" style="height:46px; margin:2px 0 -6px 0; display:block;"><br>
                <strong>Yap Wai Jyh</strong><br>
                {{ __('admin_vendors.letter_signatory_title') }}<br>
                Email: chrisyap@mybbs.com.my &middot; HP: 012-2252275 / 016-6621311</p>

                <div style="margin-top:16px; padding-top:8px; border-top:1px solid #e2e8f0; font-size:9.5px; color:#9ca3af;">{{ __('admin_vendors.letter_enclosed_prefix') }} <a href="{{ route('admin.vendors.approvals.agreement-file') }}" target="_blank" style="color:#1565C0;">{{ __('admin_vendors.view_download_link') }}</a></div>

                {{-- MOVED 14 Aug 2026 (was above the letter) — per Chris:
                     "move the approval box end of the letter." Clearly
                     marked as internal-only, sans-serif, boxed off from
                     the formal letter text above it so it never reads as
                     part of the letter itself. --}}
                <div style="margin-top:22px; padding-top:14px; border-top:2px dashed #cbd5e1; font-family:Arial, sans-serif;">
                    <div style="font-size:9px; font-weight:700; color:#9ca3af; letter-spacing:0.5px; margin-bottom:2px;">{{ __('admin_vendors.internal_use_only_heading') }}</div>
                    {{-- CHANGED 14 Aug 2026 (2nd pass) — per Chris: "sales
                         admin or finance admin either one approve then
                         admin director approve, not director approve
                         first." Two stages, not three independent boxes:
                         Stage 1 = either Sales or Finance (whichever goes
                         first satisfies it, the other becomes "not
                         required"); Stage 2 = Director, locked until
                         Stage 1 is done. --}}
                    <div style="font-size:8.5px; color:#9ca3af; margin-bottom:8px;">{{ __('admin_vendors.gate_stage_note') }}</div>
                    <div style="display:flex; gap:8px;">
                        @foreach(['SALES' => __('admin_vendors.dept_sales_admin'), 'FINANCE' => __('admin_vendors.dept_finance_admin'), 'DIRECTOR' => __('admin_vendors.dept_director_admin')] as $dept => $label)
                            @php
                                $atCol = strtolower($dept) . '_approved_at';
                                $byCol = strtolower($dept) . '_approved_by';
                                $done = $gate->$atCol;
                                $stage1Done = $gate->sales_approved_at || $gate->finance_approved_at;
                                $notRequired = false;
                                $locked = false;
                                if (!$done) {
                                    if ($dept === 'SALES' && $gate->finance_approved_at) { $notRequired = true; }
                                    if ($dept === 'FINANCE' && $gate->sales_approved_at) { $notRequired = true; }
                                    if ($dept === 'DIRECTOR' && !$stage1Done) { $locked = true; }
                                }
                                $canApproveThis = !$done && !$notRequired && !$locked && $myDepartment === $dept;
                            @endphp
                            <div style="flex:1; border:1px solid {{ $done ? '#a7d7b5' : '#e2e8f0' }}; background:{{ $done ? '#f0fdf4' : '#fafafa' }}; border-radius:6px; padding:6px 8px;">
                                <div style="font-size:9.5px; font-weight:700; color:#374151;">{{ $label }}</div>
                                @if($done)
                                    <div style="font-size:8.5px; color:#166534; margin-top:2px;">&#10003; {{ $gateNames[$gate->$byCol] ?? __('admin_vendors.admin_fallback_name') }}<br>{{ \Carbon\Carbon::parse($done)->format('d M, h:ia') }}</div>
                                @elseif($notRequired)
                                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.not_required_stage1') }}</div>
                                @elseif($locked)
                                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.waiting_sales_finance') }}</div>
                                @elseif($canApproveThis)
                                    {{-- CHANGED 14 Aug 2026 (4th pass) —
                                         plain sign-off for all three,
                                         Director included. The actual send
                                         is its own separate, always-
                                         retryable button in the footer
                                         below (Director-only). --}}
                                    <form method="POST" action="{{ route('admin.vendors.approvals.gate', [$vendor->vendor_id, $dept]) }}" style="margin-top:3px;">
                                        @csrf
                                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:14px; padding:3px 10px; font-size:8.5px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.approve_as_label', ['label' => $label]) }}</button>
                                    </form>
                                @else
                                    <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('admin_vendors.awaiting_signoff') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- CHANGED 14 Aug 2026 (4th pass) — per Chris's SOP: "Finance and
             sales admin cannot send confirmation letter ONLY admin
             director can send." The actual send is this separate,
             explicit button — Director-only, and it stays available for
             as long as the vendor hasn't been finalized yet (this screen
             only ever loads for a PENDING/RESTRICTED vendor in the first
             place, so seeing this button here always means "not sent
             yet" — safe to click any number of times if a previous
             attempt didn't go through). --}}
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:10px; margin-top:8px; border-top:1px solid #f3f4f6;">
            <a href="{{ route('admin.vendors.approvals.show', $vendor->vendor_id) }}" style="background:#F7FAFC; color:#374151; text-decoration:none; border:1px solid #d1d5db; border-radius:20px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('admin_vendors.cancel_back_button') }}</a>
            @if($gateComplete && $myDepartment === 'DIRECTOR')
            <form method="POST" action="{{ route('admin.vendors.pending-logins.approve', $vendor->vendor_id) }}" onsubmit="return confirm({{ json_encode(__('admin_vendors.confirm_send_letter', ['name' => $vendor->vendor_name])) }});">
                @csrf
                <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('admin_vendors.send_welcome_letter_button') }}</button>
            </form>
            @elseif($gateComplete)
            <span style="background:#e0f2fe; color:#1565C0; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:700;">{{ __('admin_vendors.signoff_complete_note') }}</span>
            @else
            <span style="background:#f3f4f6; color:#9ca3af; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:700;">{{ __('admin_vendors.awaiting_signoff_above_note') }}</span>
            @endif
        </div>
    </div>
</div>

<script>
    window.glZoom = 1;

    function glSetZoom(z) {
        z = Math.max(0.25, Math.min(1.5, z));
        window.glZoom = z;
        var page = document.getElementById('gl-a4-page');
        page.style.transform = 'scale(' + z + ')';
        // Reserve the page's real (unscaled) footprint scaled down, so the
        // grey backdrop doesn't leave a giant empty gap below a shrunk page.
        page.style.marginBottom = (page.scrollHeight * (z - 1)) + 'px';
        document.getElementById('gl-zoom-pct').textContent = Math.round(z * 100) + '%';
    }

    function glZoomBy(delta) {
        glSetZoom((window.glZoom || 1) + delta);
    }

    // "Fit to Screen" — same idea as a PDF viewer: shrink (or grow) the
    // A4 page just enough that the whole thing is visible with no
    // scrolling, based on the backdrop's actual current size.
    function glFitZoom() {
        var wrap = document.getElementById('gl-a4-wrap');
        var page = document.getElementById('gl-a4-page');
        page.style.transform = 'scale(1)';
        page.style.marginBottom = '0px';
        var pageHeight = page.scrollHeight;
        var wRatio = (wrap.clientWidth - 24) / 794;
        var hRatio = (wrap.clientHeight - 24) / pageHeight;
        glSetZoom(Math.min(wRatio, hRatio, 1));
    }

    document.addEventListener('DOMContentLoaded', glFitZoom);
    window.addEventListener('resize', glFitZoom);
</script>

@endsection
