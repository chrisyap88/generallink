@extends('layouts.dashboard')

@section('page-title', __('sales_transactions.create_title'))

@section('content')
{{-- FIXED 19 Jul 2026 — genuine pre-existing bug found while adding the
     backToListRow toggle: $hasPriorInput was referenced further down in
     this file (reviewSection/step3Section display gating) but was NEVER
     actually passed from SalesTransactionController@create — only
     'agent', 'rolePrefix', 'vendors' are passed there. Every plain page
     load should have hit "Undefined variable $hasPriorInput" the same way
     this new line just did. Defining it here instead of in the
     controller (so it works for both a fresh GET and a failed-validation
     redirect back with old input) — true only when there's actual
     previously-submitted form data or validation errors to restore. --}}
@php
    $hasPriorInput = $errors->any() || old('vendor_id') || old('document_reference_number') || old('premium_amount') || old('new_customer_name');
@endphp
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px 0; box-sizing:border-box;">

    <div style="flex-shrink:0;">
        {{-- FIXED 19 Jul 2026 — per Chris: this breadcrumb is redundant
             once the Prev/Next/Submit/Cancel row is on screen, and just
             eats vertical space. Only shown on the initial consent/upload
             screen (before Next is clicked) — hidden by JS the moment the
             tabs + footer row take over, shown again if the agent goes
             back to "Change document". --}}
        <div id="backToListRow" style="display:{{ $hasPriorInput ? 'none' : 'flex' }}; align-items:baseline; gap:12px; margin-bottom:6px;">
            <a href="{{ route($rolePrefix . '.sales-transactions.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('sales_transactions.back_to_list_link') }}</a>
        </div>

        @if($errors->any())
        <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:6px 10px; font-size:11px; color:#b71c1c; margin-bottom:6px;">
            @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif

        {{-- REMOVED 19 Jul 2026 — Chris asked to drop the top instruction
             banner entirely. --}}

        {{-- REMOVED 19 Jul 2026 — per Chris: the screen should stay as-is
             after a successful read, so the upload box no longer
             collapses away and this "Change Document" summary bar isn't
             needed (the file input right above stays visible and usable
             the whole time). --}}
    </div>

    <form method="POST" action="{{ route($rolePrefix . '.sales-transactions.store') }}" enctype="multipart/form-data" style="flex:1 1 auto; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <input type="hidden" name="customer_id" id="customerIdHidden" value="{{ old('customer_id') }}">

        <div style="flex:1 1 auto; min-height:0; overflow-y:auto; padding-bottom:4px;">

            {{-- NEW 19 Jul 2026 — per Chris: a consent declaration must be
                 confirmed BEFORE any document is uploaded/read, since the
                 document is sent to the Claude API for AI-assisted
                 processing. Everything else on this screen stays as-is —
                 this is purely a new first gate in front of the existing
                 upload step. Enforced both in the browser (Read Document
                 stays disabled until checked) and server-side (both
                 extractDocument() and store() reject the request if this
                 wasn't ticked, in case JS is bypassed). --}}
            <div id="consentCard" style="background:#fff8e1; border:1px solid #F5D98B; border-radius:8px; padding:10px 14px; margin-bottom:10px;">
                <div style="font-size:11px; font-weight:700; color:#92400e; margin-bottom:6px;">{{ __('sales_transactions.step1_declaration_heading') }}</div>
                <label style="display:flex; align-items:flex-start; gap:8px; font-size:10.5px; color:#374151; cursor:pointer;">
                    <input type="checkbox" name="consent_declaration" id="consentCheckbox" value="1" required {{ old('consent_declaration') ? 'checked' : '' }} style="margin-top:2px; flex-shrink:0;">
                    <span>{{ __('sales_transactions.consent_declaration_text') }}</span>
                </label>
            </div>

            {{-- PROOF DOCUMENT — moved to the FIRST position on 19 Jul 2026
                 per feedback: upload + auto-read now happens before the
                 agent ever looks at Customer/Policy fields, so scrolling
                 down afterwards shows everything already filled in and
                 highlighted — no more scrolling back up to check. --}}
            <div id="uploadCard" style="background:#eff6ff; border:1px solid #B2EBF2; border-radius:8px; padding:10px 14px; margin-bottom:10px; opacity:{{ old('consent_declaration') ? '1' : '.5' }}; pointer-events:{{ old('consent_declaration') ? 'auto' : 'none' }};">
                <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('sales_transactions.step2_upload_heading') }}</div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                    <div>
                        <label style="font-size:9px; font-weight:600; color:#6b7280; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_document_type_label') }}</label>
                        <select name="document_type" required style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <option value="RECEIPT">{{ __('sales_transactions.doc_type_receipt') }}</option>
                            <option value="SALES_INVOICE">{{ __('sales_transactions.doc_type_sales_invoice') }}</option>
                            <option value="POLICY_DOCUMENT">{{ __('sales_transactions.doc_type_policy_document') }}</option>
                            <option value="OTHER">{{ __('sales_transactions.doc_type_other') }}</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:9px; font-weight:600; color:#6b7280; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_upload_photo_pdf_label') }}</label>
                        {{-- FIXED 19 Jul 2026 — removed accept=".jpg,.jpeg,.png,.pdf"
                             per Chris: that made the OS file picker default to only
                             showing those file types. Now it defaults to "All Files".
                             Server-side validation (mimes:jpg,jpeg,png,pdf) still
                             rejects anything else after upload, so this only changes
                             what's visible in the browse dialog, not what's accepted. --}}
                        <input type="file" name="document" id="documentInput" required style="width:100%; font-size:10px;">
                    </div>
                </div>
                <div style="margin-top:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" id="extractBtn" disabled style="background:#B45309; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10.5px; font-weight:700; cursor:pointer; opacity:.5;">{{ __('sales_transactions.read_document_button') }}</button>
                    <span id="extractStatus" style="font-size:10px; color:#6b7280;"></span>
                </div>
                {{-- FIXED 19 Jul 2026 — per Chris: stay on the same screen
                     after a successful read instead of collapsing the
                     upload box away. This message now shows right here,
                     directly below the Read Document button, instead of
                     floating in a separate box at the bottom of the
                     screen with a big empty gap in between. --}}
                <div id="inlineReadStatus" style="display:none; margin-top:8px; background:#c8e6c9; color:#1b5e20; border-radius:6px; padding:5px 8px; font-size:11px; font-weight:600;"></div>
            </div>

            {{-- NEW 19 Jul 2026 — per Chris: the create screen should only
                 show the upload step at first. The Customer/Policy/Renewal
                 tabs (and their Prev/Next) now stay hidden inside this
                 wrapper until the agent explicitly clicks the "Next"
                 button that appears after Read Document finishes.
                 EXCEPTION: if the form was just re-rendered after a failed
                 submission (validation errors, or old() input already
                 present), show it immediately — otherwise the agent would
                 be stuck back at the upload step with no way to fix the
                 fields they already filled in. --}}
            @php
                $hasPriorInput = $errors->any() || old('document_reference_number') || old('new_customer_name') || old('vendor_id');
            @endphp
            <div id="reviewSection" style="display:{{ $hasPriorInput ? 'block' : 'none' }};">

            {{-- FIXED 19 Jul 2026 — per Chris: this review screen must show
                 ONLY the 3 tabs at the very top — no separate lines above
                 them. The old standalone "Back to upload" link and the
                 "Step 3" instruction line each ate a full row of vertical
                 space before the agent ever saw a tab; both are now
                 folded into the SAME row as the tab bar (link floated
                 right of the tabs, instruction text dropped since the
                 in-panel "locked green / yellow / red" legend already
                 covers it), so the tab bar is the first and only thing at
                 the top of this screen. --}}
            {{-- TABS — 19 Jul 2026: replaced the single scrolling list with
                 two folder-style tabs (Customer / Sales Transaction) per
                 feedback, so after "Read Document" the agent can click
                 each tab to review that group of fields in its own proper
                 form, instead of scrolling through everything mixed
                 together. Both tabs' fields still submit together —
                 this only changes which one is visible at a time. --}}
            {{-- REORDERED 19 Jul 2026 — Sales Transaction/Policy first,
                 Customer second, Renewal Reminder third, per Chris. --}}
            <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
                <button type="button" class="stTabBtn" data-tab="policyCard" id="tabBtnPolicy"
                        style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">
                    {{ __('sales_transactions.tab_policy') }} <span id="polVisitedTick" style="display:none; color:#38A169;">&#10003;</span> <span id="polFilledBadge" style="display:none; background:#38A169; color:#fff; border-radius:9px; padding:1px 6px; font-size:9px; margin-left:4px;"></span>
                </button>
                <button type="button" class="stTabBtn" data-tab="customerCard" id="tabBtnCustomer"
                        style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">
                    {{ __('sales_transactions.tab_customer') }} <span id="custVisitedTick" style="display:none; color:#38A169;">&#10003;</span> <span id="custFilledBadge" style="display:none; background:#38A169; color:#fff; border-radius:9px; padding:1px 6px; font-size:9px; margin-left:4px;"></span>
                </button>
                {{-- NEW 19 Jul 2026 — lets the submitting agent see, before
                     they even hit Submit, exactly when the system will
                     automatically remind this customer to renew and what
                     that reminder will say — proof the system is
                     proactive about customer follow-up, not just a promise. --}}
                <button type="button" class="stTabBtn" data-tab="renewalCard" id="tabBtnRenewal"
                        style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:8px 8px 0 0; padding:7px 16px; font-size:10.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">
                    {{ __('sales_transactions.tab_renewal') }} <span id="renVisitedTick" style="display:none; color:#38A169;">&#10003;</span>
                </button>
                {{-- FIXED 19 Jul 2026 — folded into the tab row itself
                     (margin-left:auto pushes it to the far right) instead
                     of its own line above the tabs. --}}
                <a href="javascript:void(0)" id="backToUploadLink" style="margin-left:auto; font-size:9.5px; font-weight:600; color:#1565C0; text-decoration:none; white-space:nowrap;">{{ __('sales_transactions.change_document_link') }}</a>
            </div>

            {{-- CUSTOMER --}}
            {{-- REDESIGNED 19 Jul 2026 — per Chris: tightened padding/gaps
                 and switched Postcode/City/State to flex-wrap sized to
                 content (Postcode is short, City/State can be longer),
                 same approach as the Policy tab. --}}
            <div id="customerCard" class="stTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:6px 10px; margin-bottom:6px;">
                <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:2px;">{{ __('sales_transactions.customer_heading') }}</div>
                <div style="font-size:9px; color:#9ca3af; margin-bottom:4px;">{{ __('sales_transactions.legend_note_customer') }}</div>
                <div style="position:relative; margin-bottom:4px;">
                    <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.search_existing_customer_label') }}</label>
                    <input type="text" id="customerSearch" placeholder="{{ __('sales_transactions.start_typing_search_placeholder') }}" autocomplete="off"
                           style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                    <div id="customerResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #B2EBF2; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:50; max-height:180px; overflow-y:auto;"></div>
                </div>
                <div id="customerSelectedBanner" style="display:none; background:#e8f5e9; border-left:3px solid #38A169; border-radius:6px; padding:5px 8px; font-size:10.5px; color:#1b5e20; margin-bottom:4px;">
                    {{ __('sales_transactions.selected_prefix') }} <strong id="customerSelectedName"></strong> <a href="#" onclick="clearCustomer(event)" style="color:#b71c1c; margin-left:8px; text-decoration:none;">{{ __('sales_transactions.clear_link') }}</a>
                </div>

                <div id="newCustomerFields">
                    <div style="font-size:9.5px; color:#9ca3af; margin-bottom:4px;">{{ __('sales_transactions.or_enter_new_customer_note') }}</div>
                    {{-- FIXED 19 Jul 2026 — CSS Grid instead of flex-wrap so
                         no field can wrap onto its own line and balloon to
                         full width. --}}
                    <div style="display:grid; grid-template-columns:1.3fr 1fr 1fr 1.1fr; gap:6px; margin-bottom:4px;">
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_full_name') }}</label>
                            <input type="text" name="new_customer_name" value="{{ old('new_customer_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_name" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_nric') }}</label>
                            <input type="text" name="new_customer_nric" value="{{ old('new_customer_nric') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_nric" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_phone_hp_label') }}</label>
                            <input type="text" name="new_customer_phone" value="{{ old('new_customer_phone') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_phone" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            {{-- NEW 19 Jul 2026 — customers.email exists in the DB
                                 and the renewal reminder job (SendRenewalReminders.php)
                                 refuses to send anything if it's blank, but this form
                                 never captured it until now. --}}
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_email') }}</label>
                            <input type="email" name="new_customer_email" value="{{ old('new_customer_email') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_email" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_renewal_email_note') }}</span>
                        </div>
                    </div>
                    {{-- REDESIGNED 19 Jul 2026 — per Chris: Address now shares
                         one row with Postcode/City/State instead of sitting on
                         its own full-width row, cutting a whole row of height
                         out of the Customer tab so it fits without scrolling.
                         FIXED 19 Jul 2026 — CSS Grid instead of flex-wrap. --}}
                    <div style="display:grid; grid-template-columns:2fr 0.6fr 1fr 1fr; gap:6px; margin-bottom:4px;">
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_address') }}</label>
                            <input type="text" name="new_customer_address" value="{{ old('new_customer_address') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_address" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_postcode') }}</label>
                            <input type="text" name="new_customer_postcode" value="{{ old('new_customer_postcode') }}" maxlength="10" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_postcode" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_city') }}</label>
                            <input type="text" name="new_customer_city" value="{{ old('new_customer_city') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_city" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.field_state') }}</label>
                            <input type="text" name="new_customer_state" value="{{ old('new_customer_state') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="new_customer_state" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- POLICY DETAILS --}}
            {{-- REDESIGNED 19 Jul 2026 — per Chris: (1) fields laid out in
                 flex-wrap rows sized to actual content instead of a rigid
                 equal-width grid, so short fields (e.g. NCD, Seating
                 Capacity) no longer waste a full column's width — more
                 fields now pack onto each row, shrinking total scroll
                 height. (2) Vendor/Product are matched from the document
                 and LOCKED (read-only, grey background) once a confident
                 match is found — per Chris, letting the agent silently
                 switch the insurer or product away from what the document
                 actually says would be a fraud/dispute risk. They only
                 stay editable if no confident match could be made from
                 the document, with a warning note shown in that case. --}}
            <div id="policyCard" class="stTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:6px 10px; margin-bottom:6px;">
                <div style="font-size:11px; font-weight:700; color:#374151; margin-bottom:2px;">{{ __('sales_transactions.policy_sale_details_heading') }}</div>
                {{-- NEW 19 Jul 2026 — per Chris: fields the document
                     confidently read (e.g. Premium Amount, NRIC) are now
                     LOCKED after Read Document, since commission is
                     calculated off the sales amount and identity fields
                     must match what was submitted — changing them here
                     would be a fraud/dispute risk. --}}
                <div style="font-size:9px; color:#9ca3af; margin-bottom:4px;">{{ __('sales_transactions.legend_note_policy') }}</div>
                {{-- COMPACTED 19 Jul 2026 — per Chris: all 5 top fields now
                     share ONE row (narrower widths) instead of two, cutting
                     a full row of height so this tab fits without scrolling.
                     FIXED 19 Jul 2026 — switched flex-wrap to CSS Grid: with
                     flex-wrap, a field that didn't fit the row would wrap
                     onto its own line and then flex-grow would stretch it to
                     100% width (this is why "Total Amount Payable" briefly
                     rendered as one giant lone field). CSS Grid with a fixed
                     column count never wraps — columns just shrink together
                     — so this can't happen again. --}}
                <div style="display:grid; grid-template-columns:1.3fr 1.3fr 1fr 1fr 1fr; gap:6px;">
                    <div style="min-width:0;">
                        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_vendor_label') }}</label>
                        <select name="vendor_id" id="vendorSelect" required style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <option value="">{{ __('sales_transactions.select_vendor_option') }}</option>
                            @foreach($vendors as $v)
                                <option value="{{ $v->vendor_id }}" data-industry="{{ $v->industry }}" {{ old('vendor_id') == $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
                            @endforeach
                        </select>
                        <span id="vendorLockNote" style="display:none; color:#1565C0; font-size:9px; font-weight:600;">{{ __('sales_transactions.matched_locked_note') }}</span>
                        <span id="vendorNoMatchNote" style="display:none; color:#B45309; font-size:9px; font-weight:600;">{{ __('sales_transactions.no_confident_match_note') }}</span>
                    </div>
                    <div style="position:relative; min-width:0;">
                        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_product_search_label') }}</label>
                        <input type="text" id="productSearch" placeholder="{{ __('sales_transactions.select_vendor_first_placeholder') }}" autocomplete="off" disabled
                               style="width:100%; border:1px solid #B2EBF2; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                        <input type="hidden" name="product_id" id="productIdHidden" value="{{ old('product_id') }}">
                        <div id="productResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #B2EBF2; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:50; max-height:180px; overflow-y:auto;"></div>
                        <span id="productLockNote" style="display:none; color:#1565C0; font-size:9px; font-weight:600;">{{ __('sales_transactions.matched_locked_note') }}</span>
                        {{-- NEW 19 Jul 2026 — per Chris: previously gave NO
                             feedback at all when the vendor matched but the
                             product name on the document didn't — it just
                             sat empty, which looked broken/frozen. Now shows
                             exactly what the document said so the agent can
                             see it wasn't a glitch, just an unmatched name,
                             and either pick the closest product manually or
                             ask for it to be added to that vendor's product
                             list. --}}
                        <span id="productNoMatchNote" style="display:none; color:#B45309; font-size:9px; font-weight:600;">{{ __('sales_transactions.product_no_match_note') }}</span>
                    </div>
                    <div style="min-width:0;">
                        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_doc_ref_no_label') }}</label>
                        <input type="text" name="document_reference_number" value="{{ old('document_reference_number') }}" required placeholder="{{ __('sales_transactions.policy_invoice_no_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                        <span class="missingNote" data-for="document_reference_number" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                    </div>
                    <div style="min-width:0;">
                        {{-- RELABELLED 19 Jul 2026 — Chris flagged this as the
                             most important field on the whole form; label
                             now says plainly what it is instead of the
                             generic "Sales Amount". --}}
                        {{-- FIXED 19 Jul 2026 — per Chris: commission is
                             calculated off this field, so it must be the
                             GROSS premium (before SST/tax/duty), not the
                             final total the customer pays — paying
                             commission on tax/duty would overpay every
                             insurance sale. --}}
                        <label style="font-size:9px; font-weight:700; color:#B45309; display:block; margin-bottom:2px;">&#9733; {{ __('sales_transactions.field_premium_rm_label') }}</label>
                        <input type="number" step="0.01" min="0.01" name="premium_amount" value="{{ old('premium_amount') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; font-weight:700;">
                        <span class="missingNote" data-for="premium_amount" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                    </div>
                    <div style="min-width:0;">
                        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.sum_insured_rm_label') }}</label>
                        <input type="number" step="0.01" min="0" name="sum_insured" value="{{ old('sum_insured') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                        <span class="missingNote" data-for="sum_insured" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                    </div>
                </div>

                {{-- NEW 17 Jul 2026 — insurance-only fields, shown only when
                     the selected vendor's industry is INSURANCE. Hidden
                     entirely (and not required) for any other industry. --}}
                <div id="insuranceFieldsWrap" style="display:none; margin-top:4px; padding-top:4px; border-top:1px dashed #E2E8F0;">
                    {{-- COMPACTED 19 Jul 2026 — per Chris: Coverage Start/End/
                         Type/Vehicle No. AND NCD%/Excess/Total Payable now
                         share ONE row (7 narrower fields) instead of two
                         separate rows, cutting a full row of height. FIXED
                         19 Jul 2026 — CSS Grid instead of flex-wrap so no
                         field can ever wrap onto its own line and balloon to
                         full width (this was the cause of "Total Amount
                         Payable" briefly rendering as one giant lone box). --}}
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr 0.9fr 0.7fr 0.8fr 1.1fr; gap:6px;">
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_coverage_start_label') }}</label>
                            <input type="date" name="coverage_start" id="coverageStartInput" value="{{ old('coverage_start') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="coverage_start" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_coverage_end_label') }}</label>
                            <input type="date" name="coverage_end" id="coverageEndInput" value="{{ old('coverage_end') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="coverage_end" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_coverage_type_label') }}</label>
                            <input type="text" name="coverage_type" value="{{ old('coverage_type') }}" placeholder="{{ __('sales_transactions.eg_comprehensive_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="coverage_type" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_vehicle_no_label') }}</label>
                            <input type="text" name="vehicle_number" value="{{ old('vehicle_number') }}" placeholder="{{ __('sales_transactions.eg_vehicle_no_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; text-transform:uppercase;">
                            <span class="missingNote" data-for="vehicle_number" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        {{-- NEW 19 Jul 2026 — NCD %, Excess, Gross Premium: shown
                             on almost every motor policy schedule, requested by
                             Chris after the earlier demo showed them but this
                             form didn't yet capture them. Stored via the same
                             flexible attributes table as Coverage Type/Add-ons. --}}
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_ncd_pct_label') }}</label>
                            <input type="number" step="0.01" min="0" max="100" name="ncd_percentage" value="{{ old('ncd_percentage') }}" placeholder="e.g. 55" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="ncd_percentage" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_excess_rm_label') }}</label>
                            <input type="number" step="0.01" min="0" name="excess_amount" value="{{ old('excess_amount') }}" placeholder="e.g. 0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="excess_amount" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            {{-- FIXED 19 Jul 2026 — this field (still
                                 named "gross_premium" internally) now
                                 captures the TAX-INCLUSIVE total the
                                 customer actually paid, for record
                                 purposes only. The commission-basis figure
                                 moved to the starred Premium Amount field
                                 above. --}}
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_total_payable_rm_label') }}</label>
                            <input type="number" step="0.01" min="0" name="gross_premium" value="{{ old('gross_premium') }}" placeholder="e.g. 456.00" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10.5px; box-sizing:border-box;">
                            <span class="missingNote" data-for="gross_premium" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                    </div>

                    {{-- REDESIGNED 19 Jul 2026 — per Chris: flex-wrap sized to
                         actual content (Seating Capacity/Year are narrow,
                         Make & Model/Engine No./Chassis No. are wider)
                         instead of an equal-width 3-column grid, so short
                         fields stop wasting space and more fields fit per
                         row. --}}
                    <div style="font-size:9.5px; color:#9ca3af; margin:5px 0 4px;">{{ __('sales_transactions.vehicle_details_note') }}</div>
                    {{-- FIXED 19 Jul 2026 — CSS Grid (8 fixed columns) instead
                         of flex-wrap, so all 8 fields always sit on one row
                         and shrink together instead of any one wrapping and
                         ballooning to full width. --}}
                    <div style="display:grid; grid-template-columns:1.7fr 1fr 0.55fr 0.55fr 1.2fr 1.2fr 1.2fr 1.2fr; gap:5px;">
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_make_type_label') }}</label>
                            <input type="text" name="vehicle_make_model" value="{{ old('vehicle_make_model') }}" placeholder="{{ __('sales_transactions.eg_vehicle_make_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="vehicle_make_model" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_cubic_cap_label') }}</label>
                            <input type="text" name="cubic_capacity" value="{{ old('cubic_capacity') }}" placeholder="1,468 CC" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="cubic_capacity" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_year_label') }}</label>
                            <input type="text" name="year_of_manufacture" value="{{ old('year_of_manufacture') }}" maxlength="4" placeholder="2006" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="year_of_manufacture" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_seating_label') }}</label>
                            <input type="text" name="seating_capacity" value="{{ old('seating_capacity') }}" placeholder="5" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                            <span class="missingNote" data-for="seating_capacity" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_engine_no_label') }}</label>
                            <input type="text" name="engine_number" value="{{ old('engine_number') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box; text-transform:uppercase;">
                            <span class="missingNote" data-for="engine_number" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_chassis_no_label') }}</label>
                            <input type="text" name="chassis_number" value="{{ old('chassis_number') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box; text-transform:uppercase;">
                            <span class="missingNote" data-for="chassis_number" style="display:none; color:#b71c1c; font-size:9px; font-weight:600;">{{ __('sales_transactions.not_found_please_fill_note') }}</span>
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_trailer_chassis_label') }}</label>
                            <input type="text" name="trailer_chassis_number" value="{{ old('trailer_chassis_number') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box; text-transform:uppercase;">
                        </div>
                        <div style="min-width:0;">
                            <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.field_named_drivers_label') }}</label>
                            <input type="text" name="named_drivers" value="{{ old('named_drivers') }}" placeholder="{{ __('sales_transactions.all_drivers_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 4px; font-size:10px; box-sizing:border-box;">
                        </div>
                    </div>

                    {{-- NEW 18 Jul 2026 — add-ons/extensions vary by renewal
                         year and by vendor's own wording, so this is a free
                         text field rather than a fixed checklist. Split into
                         TWO side-by-side boxes (first 3 lines / remaining
                         lines) instead of one tall stacked list, so a
                         typical 5-6 line add-ons set only needs ~3 rows of
                         height instead of 5-6. --}}
                    {{-- REWORKED 19 Jul 2026 — per Chris: two REAL,
                         independently-named form fields (addons_part1/
                         addons_part2, merged back into one "addons" string
                         server-side in store()) instead of one hidden
                         field kept in sync via JS — no hidden proxy
                         element, no synthetic events. FIXED again 19 Jul
                         2026 — per Chris ("I want add-on box 1 box2
                         protected"): confirmed he wants these locked the
                         same way as every other confidently-extracted
                         field (Vendor, Premium, NRIC, etc.) — green/
                         read-only once the document read them, editable
                         only if UNSURE or genuinely missing. See the
                         'addons' special case in the extraction JS below. --}}
                    <div style="margin-top:4px;">
                        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('sales_transactions.addons_extensions_label') }}</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                            <textarea name="addons_part1" id="addonsBox1" rows="3" placeholder="{{ __('sales_transactions.eg_addons1_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; resize:vertical;">{{ old('addons_part1') }}</textarea>
                            <textarea name="addons_part2" id="addonsBox2" rows="3" placeholder="{{ __('sales_transactions.eg_addons2_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; resize:vertical;">{{ old('addons_part2') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RENEWAL REMINDER PREVIEW — 19 Jul 2026. This does not send
                 anything itself. It just previews, using the REAL template
                 from app/Console/Commands/SendRenewalReminders.php (the
                 job that actually runs daily, per routes/console.php), what
                 will happen automatically once this transaction is saved:
                 when the reminder goes out (Coverage End minus 30 days,
                 the command's real default) and exactly what it will say. --}}
            <div id="renewalCard" class="stTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:6px 10px; margin-bottom:6px;">
                {{-- FIXED 19 Jul 2026 — per Chris: title + description now
                     share ONE row instead of stacking on two lines. --}}
                <div style="display:flex; align-items:baseline; gap:6px; margin-bottom:3px; white-space:nowrap; overflow:hidden;">
                    <span style="font-size:10px; font-weight:700; color:#374151; flex-shrink:0;">{{ __('sales_transactions.automatic_renewal_reminder_heading') }}</span>
                    <span style="font-size:8.5px; color:#9ca3af; text-overflow:ellipsis; overflow:hidden;">{{ __('sales_transactions.live_preview_note') }}</span>
                </div>

                <div id="renewalNotApplicable" style="display:none; background:#F7FAFC; border:1px dashed #E2E8F0; border-radius:6px; padding:6px; font-size:9.5px; color:#6b7280;">
                    {{ __('sales_transactions.renewal_not_applicable_note') }}
                </div>

                {{-- FIXED 19 Jul 2026 — per Chris: removed apostrophes from
                     the customer-facing message text ("customer's" →
                     "customer", "you'd" → "you would"), and shrank the
                     font/line-height further (twice, per Chris's repeated
                     "still too big" feedback) so the full message fits on
                     one screen without scrolling. --}}
                <div id="renewalPreviewWrap" style="display:none;">
                    {{-- FIXED 19 Jul 2026 — per Chris: the 3 lines here
                         ("Sent automatically on:" / date / the 30-day note)
                         now share ONE row instead of stacking. --}}
                    <div style="background:#eff6ff; border-left:3px solid #1565C0; border-radius:6px; padding:3px 6px; margin-bottom:3px; display:flex; align-items:baseline; gap:6px; flex-wrap:wrap;">
                        <span style="font-size:8.5px; color:#1e40af;">{{ __('sales_transactions.sent_automatically_on_label') }}</span>
                        <span id="renewalDateText" style="font-size:10.5px; font-weight:700; color:#1565C0;">{{ __('sales_transactions.fill_coverage_end_first_note') }}</span>
                        <span style="font-size:8px; color:#6b7280;">{{ __('sales_transactions.thirty_days_note') }}</span>
                    </div>

                    <div style="font-size:8.5px; font-weight:700; color:#9ca3af; margin-bottom:1px;">{{ __('sales_transactions.message_customer_receive_label') }}</div>
                    <div style="background:#F7FAFC; border:1px solid #E2E8F0; border-radius:6px; padding:5px 7px; font-size:8.5px; color:#374151; line-height:1.15;">
                        <div style="margin-bottom:2px;"><strong>{{ __('sales_transactions.renewal_email_subject') }}</strong></div>
                        <div style="margin-bottom:2px;"><strong>{{ __('sales_transactions.to_label') }}</strong> <span id="renewalToText">{{ __('sales_transactions.customer_email_on_file_placeholder') }}</span> &nbsp; <strong>{{ __('sales_transactions.cc_label') }}</strong> you + {{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}, {{ \App\Services\RoleLabelService::label('GROUP_LEADER') }} &amp; {{ __('gl.role_admin') }}</div>
                        <hr style="border:none; border-top:1px solid #E2E8F0; margin:2px 0;">
                        {{ __('sales_transactions.dear_word') }} <span id="renewalCustomerName">{{ __('sales_transactions.the_customer_word') }}</span>,<br>
                        {{-- FIXED 19 Jul 2026 — per Chris: wording changed
                             from "due for renewal soon" to "due on [end
                             date]", showing the actual Coverage End date. --}}
                        <span id="renewalDueDateLine">{{ __('sales_transactions.due_date_line_default') }}</span><br>
                        {{-- NEW 19 Jul 2026 — per Chris: the reminder must
                             remind the customer WHAT they bought (Sum
                             Insured, Coverage Type, Vehicle No., NCD,
                             Add-ons, etc.), not just that it's expiring.
                             Filled live by JS from the same fields on this
                             form, and matches exactly what gets stored to
                             the DB on Submit. FIXED 19 Jul 2026 — per
                             Chris: rendered as monospace <pre> so the
                             padded labels actually line up in a column,
                             matching the padding done server-side. --}}
                        <pre id="renewalPolicyDetails" style="margin:0 0 2px; font-family:'Courier New', Courier, monospace; font-size:8.5px; line-height:1.15; white-space:pre-wrap; word-break:break-word;">&mdash;</pre>
                        {{ __('sales_transactions.let_us_know_proceed_note') }}<br>
                        <em style="color:#9ca3af;">{{ __('sales_transactions.secure_link_placeholder') }}</em><br>
                        {{ __('sales_transactions.agent_label') }} {{ $agent->full_name }} ({{ $agent->phone }})<br>
                        GeneralLink
                    </div>
                </div>
            </div>

            </div>{{-- /#reviewSection --}}

        </div>

        {{-- FIXED 19 Jul 2026 — per Chris: Step 3/Submit must NOT show on the
             initial upload-only screen. Now gated behind the same
             reviewSection reveal (i.e. only appears once Next has been
             clicked after a successful/attempted read, or on a validation
             re-render where the tabs are already open). --}}
        {{-- FIXED 19 Jul 2026 — per Chris: removed the "Step 4 · Submit"
             instruction line entirely (extra vertical space, not needed).
             FIXED 19 Jul 2026 — per Chris: Prev/Next (previously their own
             row inside the scrollable tab content, where they could get
             cut off/hidden along with everything else if a tab overflowed)
             now live in THIS pinned footer row together with Cancel/Submit
             — Prev on the far left, Next on the far right, Cancel+Submit
             grouped in the middle — so all navigation is always visible
             regardless of tab content height. RELABELLED 19 Jul 2026 — per
             Chris: "Submit Sales Transaction" was misleading — it looked
             like it might only submit the Policy tab, leaving the agent
             unsure whether Customer tab edits (phone/email) were saved at
             all. Renamed to "Save & Submit" and added the note below to
             make explicit that this ONE action saves all three tabs
             together. There is no separate save per tab — everything is
             one form, submitted once. --}}
        <div id="step3Section" style="display:{{ $hasPriorInput ? 'block' : 'none' }}; flex-shrink:0; padding:3px 0 0; background:#fff; border-top:1px solid #f3f4f6;">
            <div style="text-align:center; font-size:9px; color:#9ca3af; margin-bottom:2px;">{{ __('sales_transactions.saves_together_note') }}</div>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding-bottom:3px;">
                <button type="button" id="tabPrevBtn" style="background:#9ca3af; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:600; cursor:not-allowed;">{{ __('network.prev') }}</button>
                <div style="display:flex; gap:8px;">
                    <a href="{{ route($rolePrefix . '.sales-transactions.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:11.5px; font-weight:500;">{{ __('gl.cancel_button') }}</a>
                    <button type="submit" id="submitBtn" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 20px; font-size:11.5px; font-weight:700; cursor:pointer;">{{ __('sales_transactions.save_submit_button') }}</button>
                </div>
                <button type="button" id="tabNextBtn" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:700; cursor:pointer;">{{ __('network.next') }}</button>
            </div>
        </div>
    </form>
</div>

{{-- FIXED 19 Jul 2026 — per Chris: no more separate floating message box
     (it was creating a disconnected message far from the upload area,
     with a big empty gap in between). The confirmation text now lives
     inline via #inlineReadStatus, right below the Read Document button.
     Only the "Next" action itself stays fixed bottom-right, since that
     was requested to always be reachable without scrolling. --}}
<button type="button" id="postReadNextBtn" style="display:none; position:fixed; bottom:20px; right:20px; background:#1565C0; color:#fff; border:none; border-radius:8px; padding:12px 28px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 4px 14px rgba(0,0,0,.25); z-index:1000;">{{ __('network.next') }}</button>

<script>
    // REMOVED 19 Jul 2026 — per Chris ("cannot change at all, it was
    // extracted from documents"): the previous design kept a hidden
    // #addonsTextarea in sync with two visible boxes via a merge/split
    // function pair and a synthetic dispatched 'input' event. That
    // indirection is gone now — addonsBox1/addonsBox2 (name=
    // "addons_part1"/"addons_part2") are real, ordinary, always-editable
    // form fields with no proxy element and no per-keystroke JS at all;
    // they're merged back into one "addons" string server-side in
    // SalesTransactionController@store. This function is kept only because
    // other textareas may still want auto-sizing later.
    function autoGrowTextarea(el) {
        if (!el) return;
        var lines = el.value.split(/\r\n|\r|\n/).length;
        el.rows = Math.max(1, lines);
    }

(function() {
    var custSearch = document.getElementById('customerSearch');
    var custResults = document.getElementById('customerResults');
    var custIdHidden = document.getElementById('customerIdHidden');
    var custBanner = document.getElementById('customerSelectedBanner');
    var custName = document.getElementById('customerSelectedName');
    var newCustFields = document.getElementById('newCustomerFields');
    var custTimer = null;

    custSearch.addEventListener('input', function() {
        clearTimeout(custTimer);
        var term = this.value.trim();
        if (term.length < 2) { custResults.style.display = 'none'; return; }
        custTimer = setTimeout(function() {
            fetch('{{ route($rolePrefix . ".sales-transactions.customer-typeahead") }}?term=' + encodeURIComponent(term))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    if (!rows.length) { custResults.innerHTML = '<div style="padding:8px;font-size:10px;color:#9ca3af;">{{ __('sales_transactions.no_matches_js') }}</div>'; custResults.style.display = 'block'; return; }
                    custResults.innerHTML = rows.map(function(r) {
                        return '<div class="cust-opt" data-id="'+r.customer_id+'" data-name="'+r.full_name+'" style="padding:6px 8px;font-size:10.5px;cursor:pointer;border-bottom:1px solid #f3f4f6;">'+r.full_name+' <span style="color:#9ca3af;">&middot; '+(r.phone||'')+'</span></div>';
                    }).join('');
                    custResults.style.display = 'block';
                    custResults.querySelectorAll('.cust-opt').forEach(function(el) {
                        el.onmouseover = function(){ this.style.background='#EBF5FB'; };
                        el.onmouseout = function(){ this.style.background=''; };
                        el.onclick = function() {
                            custIdHidden.value = this.dataset.id;
                            custName.textContent = this.dataset.name;
                            custBanner.style.display = 'block';
                            newCustFields.style.display = 'none';
                            custSearch.value = this.dataset.name;
                            custResults.style.display = 'none';
                        };
                    });
                });
        }, 250);
    });

    window.clearCustomer = function(e) {
        e.preventDefault();
        custIdHidden.value = '';
        custBanner.style.display = 'none';
        newCustFields.style.display = 'block';
        custSearch.value = '';
    };

    document.addEventListener('click', function(e) {
        if (!custSearch.contains(e.target) && !custResults.contains(e.target)) custResults.style.display = 'none';
    });

    // NEW 17 Jul 2026 — show/hide the insurance-only coverage date fields
    // based on the selected vendor's industry, and drop the "required"
    // attribute when hidden so non-insurance submissions aren't blocked.
    var insuranceWrap = document.getElementById('insuranceFieldsWrap');
    var coverageStart = document.getElementById('coverageStartInput');
    var coverageEnd = document.getElementById('coverageEndInput');

    // FIXED 19 Jul 2026 — vendor_id is never auto-filled by "Read
    // Document" (only a human picks the vendor), so this used to stay
    // hidden even when Coverage Start/End, NCD %, Engine No., etc. HAD
    // been filled by the extraction — Chris correctly pointed out the
    // badge said "18 filled" but only 3 fields were actually visible.
    // Once extraction fills any insurance-only field, this flag latches
    // true so the section (and the Renewal Reminder preview) shows
    // immediately, without waiting for the agent to pick a vendor first.
    var extractionInsuranceHint = false;

    function toggleInsuranceFields() {
        var opt = vendorSelect.options[vendorSelect.selectedIndex];
        var isInsurance = (opt && opt.dataset.industry === 'INSURANCE') || extractionInsuranceHint;
        insuranceWrap.style.display = isInsurance ? 'block' : 'none';
        if (coverageStart) coverageStart.required = isInsurance;
        if (coverageEnd) coverageEnd.required = isInsurance;
        updateRenewalPreview(isInsurance);
    }

    // NEW 19 Jul 2026 — Renewal Reminder tab: previews exactly what
    // renewals:send-reminders (app/Console/Commands/SendRenewalReminders.php,
    // scheduled daily in routes/console.php with its real 30-day default)
    // will do automatically once this transaction is saved. Pure preview —
    // sends nothing itself, just reads the same coverage_end/customer name
    // values already on this form.
    var renewalNotApplicable = document.getElementById('renewalNotApplicable');
    var renewalPreviewWrap = document.getElementById('renewalPreviewWrap');
    var renewalDateText = document.getElementById('renewalDateText');
    var renewalCustomerNameEl = document.getElementById('renewalCustomerName');
    var renewalDueDateLine = document.getElementById('renewalDueDateLine');
    var renewalPolicyDetails = document.getElementById('renewalPolicyDetails');
    // Keep in sync with $padWidth in SalesTransactionController@store.
    var PAD_WIDTH = 24;
    function padLabel(label) {
        var s = label;
        while (s.length < PAD_WIDTH) s += ' ';
        return s;
    }
    var newCustomerNameInput = document.querySelector('[name="new_customer_name"]');
    var vehicleNumberInput = document.querySelector('[name="vehicle_number"]');
    var coverageTypeInput = document.querySelector('[name="coverage_type"]');
    var sumInsuredInput = document.querySelector('[name="sum_insured"]');
    var ncdInput = document.querySelector('[name="ncd_percentage"]');
    var excessInput = document.querySelector('[name="excess_amount"]');
    // FIXED 19 Jul 2026 — 'addons' is no longer one field; it's the two
    // real boxes below (addons_part1/addons_part2), read directly wherever
    // the combined value is needed.
    var addonsBox1El = document.getElementById('addonsBox1');
    var addonsBox2El = document.getElementById('addonsBox2');
    var coverageStartInputEl = document.getElementById('coverageStartInput');
    var MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    function fmtMoney(v) {
        var n = parseFloat(v);
        return isNaN(n) ? '0.00' : n.toFixed(2);
    }
    function fmtDateShort(val) {
        if (!val) return '?';
        var d = new Date(val + 'T00:00:00');
        return d.getDate() + ' ' + MONTH_NAMES[d.getMonth()].slice(0, 3) + ' ' + d.getFullYear();
    }

    function updateRenewalPreview(isInsurance) {
        if (typeof isInsurance === 'undefined') {
            var opt = vendorSelect.options[vendorSelect.selectedIndex];
            isInsurance = opt && opt.dataset.industry === 'INSURANCE';
        }

        if (!isInsurance) {
            renewalNotApplicable.style.display = 'block';
            renewalPreviewWrap.style.display = 'none';
            return;
        }
        renewalNotApplicable.style.display = 'none';
        renewalPreviewWrap.style.display = 'block';

        var endVal = coverageEnd ? coverageEnd.value : '';
        if (!endVal) {
            renewalDateText.textContent = '{{ __('sales_transactions.fill_coverage_end_first_note') }}';
        } else {
            // Same "30 days before coverage_end" the real command uses
            // by default (--days=30, scheduled with no override).
            var d = new Date(endVal + 'T00:00:00');
            d.setDate(d.getDate() - 30);
            renewalDateText.textContent = d.getDate() + ' ' + MONTH_NAMES[d.getMonth()] + ' ' + d.getFullYear();
        }

        // NOTE: intentionally reads the outer "custName" element (the
        // selected-existing-customer banner span) as a fallback — do not
        // rename this to "custName" locally, that would shadow it.
        var resolvedCustomerName = (newCustomerNameInput && newCustomerNameInput.value.trim())
            || (custName && custName.textContent)
            || '{{ __('sales_transactions.the_customer_word') }}';
        renewalCustomerNameEl.textContent = resolvedCustomerName;

        // FIXED 19 Jul 2026 — per Chris: wording now shows the actual due
        // (Coverage End) date instead of "due for renewal soon".
        renewalDueDateLine.textContent = '{{ __('sales_transactions.due_date_line_prefix') }} ' + fmtDateShort(endVal) + '{{ __('sales_transactions.due_date_line_suffix') }}';

        // Mirrors the exact lines SalesTransactionController@store builds
        // into reminder_message (same PAD_WIDTH) — keep both in sync if
        // the format changes. Rendered inside a <pre> so the padded
        // spaces actually line up the colons in a column.
        var lines = [];
        lines.push(padLabel('Vehicle Registration No.') + ': ' + ((vehicleNumberInput && vehicleNumberInput.value.trim()) || '-'));
        lines.push(padLabel('Coverage Type') + ': ' + ((coverageTypeInput && coverageTypeInput.value.trim()) || '-'));
        lines.push(padLabel('Sum Insured') + ': RM ' + fmtMoney(sumInsuredInput ? sumInsuredInput.value : 0));
        var startVal = coverageStartInputEl ? coverageStartInputEl.value : '';
        lines.push(padLabel('Coverage Period') + ': ' + fmtDateShort(startVal) + ' to ' + fmtDateShort(endVal));
        if (ncdInput && ncdInput.value) lines.push(padLabel('NCD') + ': ' + ncdInput.value + '%');
        if (excessInput && excessInput.value) lines.push(padLabel('Excess') + ': RM ' + fmtMoney(excessInput.value));

        // FIXED 19 Jul 2026 — per Chris's mockup: each add-on gets its own
        // aligned row under the same colon column, split into a name
        // column and a right-following RM amount column. Mirrors the PHP
        // parsing in SalesTransactionController@store exactly.
        var addonsVal = ((addonsBox1El ? addonsBox1El.value : '') + '\n' + (addonsBox2El ? addonsBox2El.value : '')).trim();
        if (addonsVal) {
            var addonRows = [];
            var addonNameWidth = 0;
            addonsVal.split(/\r\n|\r|\n/).forEach(function(addonLine) {
                addonLine = addonLine.trim();
                if (!addonLine) return;
                var m = addonLine.match(/^(.*\S)\s+([\d,]+(?:\.\d{1,2})?)$/);
                var name, amount;
                if (m) {
                    name = m[1];
                    amount = 'RM ' + fmtMoney(m[2].replace(/,/g, ''));
                } else {
                    name = addonLine;
                    amount = '';
                }
                addonRows.push([name, amount]);
                addonNameWidth = Math.max(addonNameWidth, name.length);
            });
            lines.push('Add-ons:');
            addonRows.forEach(function(row) {
                var name = row[0], amount = row[1];
                var paddedName = name;
                while (paddedName.length < addonNameWidth + 2) paddedName += ' ';
                lines.push(padLabel('') + ': ' + paddedName + amount);
            });
        }
        renewalPolicyDetails.textContent = lines.join('\n');
    }

    // Live updates as the agent types/picks these, so the preview never
    // goes stale — same pattern as the coverage-fields toggle above.
    if (coverageEnd) coverageEnd.addEventListener('input', function() { updateRenewalPreview(); });
    if (newCustomerNameInput) newCustomerNameInput.addEventListener('input', function() { updateRenewalPreview(); });
    [vehicleNumberInput, coverageTypeInput, sumInsuredInput, ncdInput, excessInput, addonsBox1El, addonsBox2El, coverageStartInputEl].forEach(function(el) {
        if (el) el.addEventListener('input', function() { updateRenewalPreview(); });
    });
    document.addEventListener('click', function(e) {
        // Picking an existing customer from the type-ahead list also
        // changes the name the preview should show.
        if (e.target.closest && e.target.closest('.cust-opt')) updateRenewalPreview();
    });

    // Product type-ahead, scoped to chosen vendor
    var vendorSelect = document.getElementById('vendorSelect');
    var prodSearch = document.getElementById('productSearch');
    var prodResults = document.getElementById('productResults');
    var prodIdHidden = document.getElementById('productIdHidden');
    var prodTimer = null;

    vendorSelect.addEventListener('change', function() {
        prodSearch.disabled = !this.value;
        prodSearch.placeholder = this.value ? '{{ __('sales_transactions.type_search_products_placeholder') }}' : '{{ __('sales_transactions.select_vendor_first_placeholder') }}';
        prodSearch.value = '';
        prodIdHidden.value = '';
        toggleInsuranceFields();
    });

    // FIXED 19 Jul 2026 — genuine bug: after a validation error (e.g.
    // "duplicate document reference number") redirects back to this same
    // page with old() input, Blade correctly re-selects the Vendor
    // dropdown — but nothing ever fires a 'change' event on it (setting
    // the "selected" attribute in HTML doesn't trigger JS listeners), so
    // Product stayed stuck on its initial disabled "Select vendor
    // first..." state even though a vendor was clearly already chosen.
    // Runs the same enabling logic on page load if Vendor already has a
    // value.
    if (vendorSelect.value) {
        prodSearch.disabled = false;
        prodSearch.placeholder = '{{ __('sales_transactions.type_search_products_placeholder') }}';
    }

    // NEW 19 Jul 2026 — per Chris: the Vendor and Product the document
    // actually says must not be silently changeable by the agent — doing
    // so would create a dispute (document says one insurer/product, the
    // saved transaction says another). When extractDocument() returns a
    // confident vendor_match/product_match, these lock the fields visually
    // (pointer-events blocked, tabIndex removed) while still submitting
    // the correct value normally. If no confident match was found, the
    // fields stay fully editable — locking to a WRONG guess would be
    // worse than not locking at all.
    var vendorLockNote = document.getElementById('vendorLockNote');
    var vendorNoMatchNote = document.getElementById('vendorNoMatchNote');
    var productLockNote = document.getElementById('productLockNote');
    var productNoMatchNote = document.getElementById('productNoMatchNote');

    // FIXED 19 Jul 2026 — per Chris's exact mockup: locked/confirmed
    // fields must stay GREEN (matching "read from the document"), not
    // grey. Grey was never asked for — it was my own choice and doesn't
    // match what was shown and approved.
    function lockField(el) {
        el.style.pointerEvents = 'none';
        el.tabIndex = -1;
        el.style.background = '#e8f5e9';
        el.style.color = '#1b5e20';
        el.style.borderColor = '#38A169';
    }

    // NEW 19 Jul 2026 — per Chris: every field the document confidently
    // filled in (Premium Amount, NRIC, Sum Insured, etc. — not just
    // Vendor/Product) gets locked the same way, so the agent can't quietly
    // change a value the document already supplied. Tracked here so a
    // fresh document read can unlock+clear them all before refilling.
    var lockedFieldEls = [];

    function applyVendorProductMatch(result) {
        vendorLockNote.style.display = 'none';
        vendorNoMatchNote.style.display = 'none';
        productLockNote.style.display = 'none';
        productNoMatchNote.style.display = 'none';

        var vm = result.vendor_match;
        if (!vm) return;

        if (vm.matched) {
            vendorSelect.value = vm.vendor_id;
            // Same follow-on effects as a real change event (enables
            // product search, shows/hides insurance fields).
            prodSearch.disabled = false;
            prodSearch.placeholder = 'Type to search products...';
            toggleInsuranceFields();
            lockField(vendorSelect);
            vendorLockNote.style.display = 'block';

            var pm = result.product_match;
            if (pm && pm.matched) {
                prodIdHidden.value = pm.product_id;
                prodSearch.value = pm.product_name;
                lockField(prodSearch);
                productLockNote.style.display = 'block';
            } else if (pm && pm.raw_text) {
                // FIXED 19 Jul 2026 — per Chris: previously silent when the
                // vendor matched but the product name on the document
                // didn't match anything in that vendor's product list —
                // looked like a broken/frozen field. Now explains exactly
                // why (the field is NOT disabled — it's just unmatched)
                // and shows what the document actually said, so it's
                // obvious this product needs to be added to Product
                // Maintenance for that vendor, or picked manually here.
                productNoMatchNote.textContent = @json(__('sales_transactions.product_no_match_detail_js')).replace(':text', pm.raw_text);
                productNoMatchNote.style.display = 'block';
            }
        } else if (vm.raw_text) {
            vendorNoMatchNote.textContent = @json(__('sales_transactions.vendor_no_match_detail_js')).replace(':text', vm.raw_text);
            vendorNoMatchNote.style.display = 'block';
        }
    }

    prodSearch.addEventListener('input', function() {
        clearTimeout(prodTimer);
        var term = this.value.trim();
        var vendorId = vendorSelect.value;
        if (!vendorId) return;
        prodTimer = setTimeout(function() {
            fetch('{{ route($rolePrefix . ".sales-transactions.product-typeahead") }}?vendor_id=' + encodeURIComponent(vendorId) + '&term=' + encodeURIComponent(term))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    if (!rows.length) { prodResults.innerHTML = '<div style="padding:8px;font-size:10px;color:#9ca3af;">{{ __('sales_transactions.no_matches_js') }}</div>'; prodResults.style.display = 'block'; return; }
                    prodResults.innerHTML = rows.map(function(r) {
                        return '<div class="prod-opt" data-id="'+r.product_id+'" data-name="'+r.product_name+'" style="padding:6px 8px;font-size:10.5px;cursor:pointer;border-bottom:1px solid #f3f4f6;">'+r.product_name+' <span style="color:#9ca3af;">&middot; '+r.product_type+'</span></div>';
                    }).join('');
                    prodResults.style.display = 'block';
                    prodResults.querySelectorAll('.prod-opt').forEach(function(el) {
                        el.onmouseover = function(){ this.style.background='#EBF5FB'; };
                        el.onmouseout = function(){ this.style.background=''; };
                        el.onclick = function() {
                            prodIdHidden.value = this.dataset.id;
                            prodSearch.value = this.dataset.name;
                            prodResults.style.display = 'none';
                        };
                    });
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (!prodSearch.contains(e.target) && !prodResults.contains(e.target)) prodResults.style.display = 'none';
    });

    // Run once on load too — handles the case where a validation error
    // sent the agent back here with a vendor already selected (old()).
    toggleInsuranceFields();

    // NEW 19 Jul 2026 — Customer / Sales Transaction folder tabs. Only
    // one panel is visible at a time; both still submit together since
    // hidden fields are just display:none, not removed from the form.
    var tabBtns = document.querySelectorAll('.stTabBtn');
    var tabPanels = document.querySelectorAll('.stTabPanel');
    var tabOrder = ['policyCard', 'customerCard', 'renewalCard'];
    var tabPrevBtn = document.getElementById('tabPrevBtn');
    var tabNextBtn = document.getElementById('tabNextBtn');

    function setNavBtnState(btn, enabled, label) {
        btn.disabled = !enabled;
        btn.style.cursor = enabled ? 'pointer' : 'not-allowed';
        btn.style.background = enabled ? '#1565C0' : '#9ca3af';
        btn.textContent = label;
    }

    // NEW 19 Jul 2026 — tracks which tabs the agent has actually opened,
    // so Submit can warn if Customer or Policy was never checked (Chris's
    // concern: the Submit button sits right there and could be clicked
    // by accident before the other tabs are ever reviewed).
    var visitedTabs = { customerCard: false, policyCard: false, renewalCard: false };
    var visitedTickEl = {
        customerCard: document.getElementById('custVisitedTick'),
        policyCard: document.getElementById('polVisitedTick'),
        renewalCard: document.getElementById('renVisitedTick'),
    };

    var currentTabId = 'policyCard';
    function activateTab(tabId) {
        currentTabId = tabId;
        visitedTabs[tabId] = true;
        if (visitedTickEl[tabId]) visitedTickEl[tabId].style.display = 'inline';
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
        // NEW 19 Jul 2026 — standard Prev/Next, matching the blue-fill
        // convention used elsewhere in the app, so stepping between the
        // two tabs doesn't rely only on clicking the tab headers.
        var idx = tabOrder.indexOf(tabId);
        setNavBtnState(tabPrevBtn, idx > 0, @json(__('network.prev')));
        setNavBtnState(tabNextBtn, idx < tabOrder.length - 1, @json(__('network.next')));
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });
    tabPrevBtn.addEventListener('click', function() {
        var idx = tabOrder.indexOf(currentTabId);
        if (idx > 0) activateTab(tabOrder[idx - 1]);
    });
    tabNextBtn.addEventListener('click', function() {
        var idx = tabOrder.indexOf(currentTabId);
        if (idx < tabOrder.length - 1) activateTab(tabOrder[idx + 1]);
    });
    // Set correct initial Prev/Next state for the default-active Customer tab.
    activateTab('policyCard');

    // Which tab each extracted field name belongs to, so we can badge
    // each tab with how many fields on it were auto-filled.
    var customerFieldNames = ['new_customer_name', 'new_customer_nric', 'new_customer_phone', 'new_customer_email', 'new_customer_address', 'new_customer_postcode', 'new_customer_city', 'new_customer_state'];

    // NEW 19 Jul 2026 — after "Read Document" runs, flag every visible
    // Customer/Policy field that's STILL blank with a red note + red
    // border, so the agent knows exactly what to fill in manually
    // (e.g. customer email/phone often aren't printed on the document
    // at all) instead of only seeing what WAS found.
    // FIXED 19 Jul 2026 — per Chris's mockup: a genuinely missing field
    // gets a full pink highlighted box (border + tinted background), not
    // just a thin red border, to match the "Not found — please fill in"
    // boxes shown in the approved preview. Only touches empty fields —
    // never resets a filled field's border/background, since a
    // confidently-locked field needs to KEEP its green styling here.
    function flagMissingFields() {
        document.querySelectorAll('.missingNote').forEach(function(note) {
            var el = form.querySelector('[name="' + note.dataset.for + '"]');
            if (!el) return;
            var empty = !el.value || !el.value.trim();
            note.style.display = empty ? 'block' : 'none';
            if (empty) {
                el.style.borderColor = '#e53935';
                el.style.background = '#fdecea';
            } else if (el.style.pointerEvents !== 'none') {
                // Only clear pink/red styling for fields that aren't
                // locked — a locked field's green styling is set
                // elsewhere and must not be touched here.
                el.style.borderColor = '';
                el.style.background = '';
            }
        });
    }

    // Clears an individual field's red flag the moment the agent starts
    // typing into it, instead of waiting for another full re-check.
    document.querySelectorAll('.missingNote').forEach(function(note) {
        var el = document.querySelector('[name="' + note.dataset.for + '"]');
        if (!el) return;
        el.addEventListener('input', function() {
            var empty = !el.value || !el.value.trim();
            note.style.display = empty ? 'block' : 'none';
            el.style.borderColor = empty ? '#e53935' : '';
            el.style.background = empty ? '#fdecea' : '';
        });
    });

    // FIXED 19 Jul 2026 — used by extractionInsuranceHint above: if any of
    // these were filled by "Read Document", the insurance-only section
    // (and Renewal Reminder preview) should show right away instead of
    // staying hidden until the agent manually picks the vendor.
    var insuranceOnlyFieldNames = ['coverage_start', 'coverage_end', 'coverage_type', 'vehicle_number', 'vehicle_make_model', 'cubic_capacity', 'year_of_manufacture', 'seating_capacity', 'engine_number', 'chassis_number', 'trailer_chassis_number', 'named_drivers', 'addons', 'ncd_percentage', 'excess_amount', 'gross_premium'];

    // NEW 18 Jul 2026 — "Read Document": sends the selected file to the
    // Claude API (via extractDocument() on the controller) and fills in
    // every matching field with what it finds. Nothing is saved by this
    // step — the agent still reviews and corrects before hitting the
    // real Submit button below.
    var docInput = document.getElementById('documentInput');
    var extractBtn = document.getElementById('extractBtn');
    var extractStatus = document.getElementById('extractStatus');
    var custFilledBadge = document.getElementById('custFilledBadge');
    var polFilledBadge = document.getElementById('polFilledBadge');
    var inlineReadStatus = document.getElementById('inlineReadStatus');
    var reviewSection = document.getElementById('reviewSection');
    var step3Section = document.getElementById('step3Section');
    var postReadNextBtn = document.getElementById('postReadNextBtn');
    var uploadCard = document.getElementById('uploadCard');
    var backToListRow = document.getElementById('backToListRow');
    var consentCard = document.getElementById('consentCard');
    var backToUploadLink = document.getElementById('backToUploadLink');
    var consentCheckbox = document.getElementById('consentCheckbox');
    var pendingTabToShow = 'policyCard';

    // NEW 19 Jul 2026 — per Chris: the upload step must not be usable
    // until the consent declaration is ticked (the document goes to the
    // Claude API for AI-assisted processing, so consent must come first).
    // Re-evaluated on every checkbox change so unchecking it after the
    // fact re-locks the upload step too.
    function updateConsentGate() {
        var consented = consentCheckbox.checked;
        uploadCard.style.opacity = consented ? '1' : '.5';
        uploadCard.style.pointerEvents = consented ? 'auto' : 'none';
        var hasFile = docInput.files && docInput.files.length > 0;
        extractBtn.disabled = !(consented && hasFile);
        extractBtn.style.opacity = (consented && hasFile) ? '1' : '.5';
    }
    consentCheckbox.addEventListener('change', updateConsentGate);
    updateConsentGate();

    // FIXED 19 Jul 2026 — per Chris: clicking Next should feel like moving
    // to a new screen (matching the tab preview shown in chat), not just
    // revealing more content below the upload box on the same scroll. The
    // upload card now hides here, the tabs section takes over the full
    // view, and the page jumps to the top so it reads as a clean step
    // change instead of "scroll down to see more."
    postReadNextBtn.addEventListener('click', function() {
        postReadNextBtn.style.display = 'none';
        uploadCard.style.display = 'none';
        // FIXED 19 Jul 2026 — per Chris: the declaration box must also
        // disappear here — the review screen shows ONLY the 3 tabs at the
        // top, nothing stacked above them.
        consentCard.style.display = 'none';
        if (backToListRow) backToListRow.style.display = 'none';
        reviewSection.style.display = 'block';
        step3Section.style.display = 'block';
        activateTab(pendingTabToShow);
        window.scrollTo(0, 0);
    });

    // Lets the agent step back to the upload box (e.g. to re-read a
    // different document) without losing the tabs — clicking Read
    // Document again will simply refill and they can hit Next again.
    backToUploadLink.addEventListener('click', function() {
        reviewSection.style.display = 'none';
        step3Section.style.display = 'none';
        consentCard.style.display = 'block';
        uploadCard.style.display = 'block';
        if (backToListRow) backToListRow.style.display = 'flex';
        window.scrollTo(0, 0);
    });
    var form = extractBtn.closest('form');

    function unlockField(el) {
        el.style.pointerEvents = '';
        el.tabIndex = 0;
        el.style.background = '';
        el.style.color = '';
        el.style.borderColor = '';
    }

    docInput.addEventListener('change', function() {
        updateConsentGate();
        extractStatus.textContent = '';
        inlineReadStatus.style.display = 'none';
        postReadNextBtn.style.display = 'none';
        reviewSection.style.display = 'none';
        step3Section.style.display = 'none';

        // Reading a different/new document — clear any previous
        // vendor/product lock so it can be re-matched fresh.
        unlockField(vendorSelect);
        unlockField(prodSearch);
        vendorLockNote.style.display = 'none';
        vendorNoMatchNote.style.display = 'none';
        productLockNote.style.display = 'none';
        productNoMatchNote.style.display = 'none';

        // NEW 19 Jul 2026 — also unlock + clear every other field that was
        // locked from the PREVIOUS document, so re-reading a new/corrected
        // document isn't stuck with stale locked values it can't overwrite
        // by hand and the new read can't unlock (locked fields never
        // reach the fill loop's own value-setting for a field the new
        // document doesn't happen to mention).
        lockedFieldEls.forEach(function(el) {
            unlockField(el);
            el.title = '';
            el.value = '';
        });
        lockedFieldEls = [];

        // FIXED 19 Jul 2026 — addonsBox1/addonsBox2 ARE included in
        // lockedFieldEls above when the previous document confidently
        // filled them (per Chris: "I want add-on box 1 box2 protected"),
        // so the loop already unlocks + clears them in that case. But if
        // they were only UNSURE (amber, never added to lockedFieldEls),
        // that styling needs clearing too — covered here unconditionally.
        [addonsBox1El, addonsBox2El].forEach(function(el) {
            if (!el) return;
            unlockField(el);
            el.title = '';
            el.value = '';
        });
    });

    // DD-MM-YYYY (what Claude is asked to return) -> YYYY-MM-DD (what
    // an <input type="date"> needs to accept a value).
    // FIXED 19 Jul 2026 — this used to anchor the regex to the whole
    // string (^...$), so a value like "10-04-2026 05:30pm" (Claude
    // sometimes appends the time straight off the document) failed to
    // match at all and the date was silently skipped — this is why
    // Coverage Start/End looked "missing" even though they were found.
    // Now it just looks for the DD-MM-YYYY pattern anywhere in the
    // string and ignores anything after it.
    function toDateInputValue(raw) {
        var m = /(\d{2})-(\d{2})-(\d{4})/.exec(raw);
        if (!m) return null;
        return m[3] + '-' + m[2] + '-' + m[1];
    }

    extractBtn.addEventListener('click', function() {
        var file = docInput.files[0];
        if (!file) return;

        extractBtn.disabled = true;
        extractStatus.style.color = '#6b7280';
        extractStatus.textContent = @json(__('sales_transactions.reading_document_js'));

        var fd = new FormData();
        fd.append('document', file);
        fd.append('_token', document.querySelector('input[name="_token"]').value);
        // NEW 19 Jul 2026 — server-side backstop for the consent gate
        // (see extractDocument() validation) in case the button's
        // disabled state is ever bypassed.
        fd.append('consent_declaration', consentCheckbox.checked ? '1' : '');

        fetch('{{ route($rolePrefix . ".sales-transactions.extract-document") }}', {
            method: 'POST',
            body: fd,
        })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                extractBtn.disabled = false;

                // NEW 5 Aug 2026 — tells the agent which key actually
                // paid for this read, since it can differ from what's
                // set in their Profile (falls back to Company Credit if
                // their BYOK key was disconnected or their Integration
                // Hub was locked this session — see extractDocument()).
                var sourceNote = '';
                if (result.extraction_source === 'OPENAI') { sourceNote = @json(__('sales_transactions.source_note_openai_js')); }
                else if (result.extraction_source === 'GEMINI') { sourceNote = @json(__('sales_transactions.source_note_gemini_js')); }
                else if (result.byok_fallback_reason === 'NOT_CONNECTED') { sourceNote = @json(__('sales_transactions.source_note_not_connected_js')); }
                else if (result.byok_fallback_reason === 'HUB_LOCKED') { sourceNote = @json(__('sales_transactions.source_note_hub_locked_js')); }
                else if (result.byok_fallback_reason === 'KEY_UNREADABLE') { sourceNote = @json(__('sales_transactions.source_note_key_unreadable_js')); }

                if (result.status !== 'OK') {
                    extractStatus.style.color = '#b71c1c';
                    extractStatus.textContent = result.message || @json(__('sales_transactions.could_not_read_document_js'));
                    return;
                }

                var unsureFields = [];
                var filledCount = 0;
                var custFilled = 0;
                var polFilled = 0;

                Object.keys(result.values).forEach(function(key) {
                    var raw = result.values[key];
                    if (raw === null || raw === undefined || raw === '') return;

                    var value = String(raw);
                    var isUnsure = value.indexOf('UNSURE:') === 0;
                    if (isUnsure) {
                        value = value.replace(/^UNSURE:\s*/, '');
                        unsureFields.push(key);
                    }

                    // SPECIAL CASE 19 Jul 2026 — per Chris: "I want add-on
                    // box 1 box2 protected" — confirmed he wants add-ons
                    // locked the same way as every other confidently-
                    // extracted field (Vendor, Premium, NRIC, etc.), not
                    // left editable. 'addons' has no single named element
                    // (it's the two real addonsBox1/addonsBox2 fields), so
                    // it's handled here instead of the generic
                    // single-element logic below, but follows the exact
                    // same lock/unsure rule as everything else.
                    if (key === 'addons') {
                        var addonLines = value.split(/\r\n|\r|\n/).filter(function(l) { return l.trim() !== ''; });
                        if (addonsBox1El) addonsBox1El.value = addonLines.slice(0, 3).join('\n');
                        if (addonsBox2El) addonsBox2El.value = addonLines.slice(3).join('\n');
                        filledCount++;
                        polFilled++;
                        extractionInsuranceHint = true;
                        if (isUnsure) {
                            [addonsBox1El, addonsBox2El].forEach(function(el) {
                                if (!el) return;
                                el.style.background = '#fff8e1';
                                el.style.borderColor = '#F5A623';
                            });
                        } else {
                            [addonsBox1El, addonsBox2El].forEach(function(el) {
                                if (!el) return;
                                lockField(el);
                                el.title = @json(__('sales_transactions.locked_field_title_js'));
                                lockedFieldEls.push(el);
                            });
                        }
                        return;
                    }

                    var el = form.querySelector('[name="' + key + '"]');
                    if (!el) return;

                    if (el.type === 'date') {
                        var converted = toDateInputValue(value);
                        if (!converted) return;
                        value = converted;
                    }

                    if (key === 'premium_amount' || key === 'sum_insured' || key === 'ncd_percentage' || key === 'excess_amount' || key === 'gross_premium') {
                        value = value.replace(/[^0-9.]/g, '');
                        if (value === '') return;
                    }

                    el.value = value;
                    if (el.tagName === 'TEXTAREA') { autoGrowTextarea(el); }
                    filledCount++;
                    if (customerFieldNames.indexOf(key) !== -1) { custFilled++; } else { polFilled++; }
                    if (insuranceOnlyFieldNames.indexOf(key) !== -1) { extractionInsuranceHint = true; }

                    // FIXED 19 Jul 2026 — per Chris: a confidently-read
                    // field (e.g. Premium Amount, NRIC) must NOT be
                    // silently changeable afterwards — commission is
                    // calculated off the sales amount, so quietly bumping
                    // it after the document was read would be a fraud
                    // risk, same for identity fields like NRIC. Only
                    // fields the document did NOT confidently provide stay
                    // editable: genuinely missing ones (red note, never
                    // reach this code at all) and ones flagged UNSURE
                    // (yellow — hard to read, agent needs to be ABLE to
                    // correct these, so they are deliberately NOT locked).
                    if (isUnsure) {
                        el.style.background = '#fff8e1';
                        el.style.borderColor = '#F5A623';
                    } else {
                        lockField(el);
                        el.title = @json(__('sales_transactions.locked_field_title_js'));
                        lockedFieldEls.push(el);
                    }
                });

                // If a vendor is already selected, this makes any newly
                // filled insurance fields actually visible right away.
                toggleInsuranceFields();

                // NEW 19 Jul 2026 — auto-select + lock Vendor/Product if
                // extractDocument() found a confident match (see comment
                // above applyVendorProductMatch).
                applyVendorProductMatch(result);

                if (filledCount === 0) {
                    extractStatus.style.color = '#b71c1c';
                    extractStatus.textContent = @json(__('sales_transactions.no_matching_fields_js'));
                    custFilledBadge.style.display = 'none';
                    polFilledBadge.style.display = 'none';

                    // NEW 19 Jul 2026 — even when nothing was auto-filled,
                    // still let the agent proceed to fill things in by hand
                    // rather than getting stuck with no way forward. Stays
                    // right here below the button — no screen change.
                    pendingTabToShow = 'policyCard';
                    inlineReadStatus.textContent = @json(__('sales_transactions.could_not_read_fields_js'));
                    inlineReadStatus.style.background = '#B45309';
                    inlineReadStatus.style.color = '#fff';
                    inlineReadStatus.style.display = 'block';
                    postReadNextBtn.style.display = 'block';
                } else {
                    // NEW 19 Jul 2026 — badge each tab with how many of its
                    // own fields got auto-filled, and land straight on
                    // whichever tab has the most once the agent clicks Next.
                    if (custFilled > 0) { custFilledBadge.textContent = custFilled + ' ' + @json(__('sales_transactions.filled_word_js')); custFilledBadge.style.display = 'inline-block'; } else { custFilledBadge.style.display = 'none'; }
                    if (polFilled > 0) { polFilledBadge.textContent = polFilled + ' ' + @json(__('sales_transactions.filled_word_js')); polFilledBadge.style.display = 'inline-block'; } else { polFilledBadge.style.display = 'none'; }
                    pendingTabToShow = polFilled > custFilled ? 'policyCard' : 'customerCard';

                    // NEW 19 Jul 2026 — red-flag every field that's still
                    // blank so the agent can see, at a glance, exactly what
                    // needs to be typed in manually before submitting.
                    flagMissingFields();

                    // FIXED 19 Jul 2026 — per Chris: stay on this same
                    // screen, no collapsing the upload box away. The
                    // confirmation shows right here, below Read Document.
                    if (unsureFields.length > 0) {
                        inlineReadStatus.textContent = @json(__('sales_transactions.document_read_unsure_js', ['count' => '__COUNT__', 'file' => '__FILE__'])).replace('__COUNT__', filledCount).replace('__FILE__', file.name).replace('[SOURCE]', sourceNote);
                    } else {
                        inlineReadStatus.textContent = @json(__('sales_transactions.document_read_success_js', ['count' => '__COUNT__', 'file' => '__FILE__'])).replace('__COUNT__', filledCount).replace('__FILE__', file.name).replace('[SOURCE]', sourceNote);
                    }
                    inlineReadStatus.style.background = '#c8e6c9';
                    inlineReadStatus.style.color = '#1b5e20';
                    inlineReadStatus.style.display = 'block';
                    postReadNextBtn.style.display = 'block';
                }
            })
            .catch(function() {
                extractBtn.disabled = false;
                extractStatus.style.color = '#b71c1c';
                extractStatus.textContent = @json(__('sales_transactions.extraction_error_js'));
            });
    });

    // NEW 19 Jul 2026 — Chris's concern: the Submit button sits right
    // there and someone could click it without ever having opened the
    // Sales Transaction/Policy tab (or Customer) to check the fields on
    // it. This doesn't block submission — the required-field validation
    // on the server already does that — it just makes sure the agent
    // gets a clear, explicit "are you sure" if they haven't looked.
    form.addEventListener('submit', function(e) {
        var unvisited = [];
        if (!visitedTabs.customerCard) unvisited.push(@json(__('sales_transactions.tab_customer_js')));
        if (!visitedTabs.policyCard) unvisited.push(@json(__('sales_transactions.tab_policy_js')));
        if (unvisited.length > 0) {
            var proceed = confirm(
                @json(__('sales_transactions.unvisited_tabs_confirm_js', ['tabs' => '__TABS__'])).replace('__TABS__', unvisited.join(' ' + @json(__('sales_transactions.and_word')) + ' '))
            );
            if (!proceed) {
                e.preventDefault();
                activateTab(unvisited[0] === @json(__('sales_transactions.tab_customer_js')) ? 'customerCard' : 'policyCard');
            }
        }
    });
})();
</script>
@endsection
