@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_vendors.onboarding_workflow_title'))

@section('content')

{{-- NEW 13 Aug 2026 — per Chris: "this communication tools allow vendor
     to upload new file if require or ask for changes... and all this
     amendment must have keep track in row form when admin click this
     program because in actual environment it may incur a series of
     communication." Two tabs on one screen (house Prev/Next + tab
     pattern, no popups, no scroll): the Communication Thread (same
     thread as Pending Vendor Logins Q&A, now supporting file
     attachments both ways) and the Amendment Log (the structured row
     table of every requested change/document and its status). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 76px 8px 16px; box-sizing:border-box; overflow:hidden;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; justify-content:space-between; align-items:flex-start; gap:8px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; word-break:break-word;">{{ $vendor->vendor_name }}</div>
            <div style="font-size:9px; color:#9ca3af; margin-top:1px;">{{ __('admin_vendors.vendor_email_registered_line', ['email' => $vendor->vendor_email, 'date' => \Carbon\Carbon::parse($vendor->created_at)->format('d M Y')]) }}</div>
        </div>
        @php
            $statusLabel = match($vendor->login_status) {
                'PENDING' => __('admin_vendors.status_pending_review'), 'AWAITING_PASSWORD' => __('admin_vendors.status_awaiting_password_setup'),
                'RESTRICTED' => __('admin_vendors.status_restricted_access'), 'ACTIVE' => __('admin_vendors.status_approved_active'), 'REJECTED' => __('masterfile.status_rejected'), default => $vendor->login_status,
            };
            $statusColor = match($vendor->login_status) {
                'PENDING' => '#854d0e', 'AWAITING_PASSWORD' => '#1565C0', 'RESTRICTED' => '#6D28D9', 'ACTIVE' => '#166534', 'REJECTED' => '#b71c1c', default => '#6b7280',
            };
            $statusBg = match($vendor->login_status) {
                'PENDING' => '#fef9c3', 'AWAITING_PASSWORD' => '#e0f2fe', 'RESTRICTED' => '#f5f3ff', 'ACTIVE' => '#f0fdf4', 'REJECTED' => '#fef2f2', default => '#f3f4f6',
            };
        @endphp
        <div style="flex-shrink:0; display:flex; align-items:center; gap:6px;">
            {{-- NEW 13 Aug 2026 per Chris — same Resend Verification Email
                 action already on Pending Vendor Logins' detail screen,
                 also here since this is where Admin is naturally already
                 messaging the vendor. Only relevant while AWAITING_PASSWORD. --}}
            @if($vendor->login_status === 'AWAITING_PASSWORD')
            <form method="POST" action="{{ route('admin.vendors.pending-logins.resend-verification', $vendor->vendor_id) }}" onsubmit="return confirm({{ json_encode(__('admin_vendors.confirm_resend_verification', ['email' => $vendor->vendor_email])) }});">
                @csrf
                <button type="submit" style="background:#fff; color:#0D5A8E; border:1px solid #0D5A8E; border-radius:6px; padding:4px 10px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.resend_verification_button') }}</button>
            </form>
            @endif
            <span style="font-size:9.5px; font-weight:700; padding:3px 10px; border-radius:10px; white-space:nowrap; color:{{ $statusColor }}; background:{{ $statusBg }};">{{ $statusLabel }}</span>
        </div>
    </div>

    <div style="display:flex; align-items:center; flex-wrap:nowrap; gap:3px; flex-shrink:0; margin-bottom:6px;">
        <button type="button" id="owTabBtn-thread" onclick="owShowTab('thread')" style="background:#1565C0; color:#fff; border:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.tab_communication_thread') }}</button>
        <button type="button" id="owTabBtn-amend" onclick="owShowTab('amend')" style="background:#F7FAFC; color:#6b7280; border:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.amendment_log_tab') }}{{ $amendments->where('status', '!=', 'RESOLVED')->count() ? __('admin_vendors.amendment_log_open_suffix', ['count' => $amendments->where('status', '!=', 'RESOLVED')->count()]) : '' }}</button>
    </div>

    <div style="flex:1; min-height:0; border:1px solid #E2E8F0; border-radius:0 6px 6px 6px; padding:10px; overflow:hidden; display:flex; flex-direction:column;">

        {{-- TAB: Communication Thread --}}
        <div id="owTabPane-thread" style="flex:1; min-height:0; display:flex; flex-direction:column;">
            {{-- CHANGED 13 Aug 2026 per Chris: "no scroll" — was
                 overflow-y:auto (an inner scrollbar Chris flagged as
                 looking wrong/chat-app-like); now overflow:hidden, same
                 fix as Help Desk's own thread — paginated 3
                 messages/page in the controller instead, with a real
                 Prev/Next bar below. --}}
            <div style="flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column; gap:6px; padding-right:2px;">
                @forelse($thread as $m)
                <div style="display:flex; {{ $m->sender_type === 'ADMIN' ? 'justify-content:flex-end;' : '' }}">
                    <div style="max-width:78%;">
                        @if($m->message_type === 'AMENDMENT_REQUEST')
                        <div style="font-size:8.5px; font-weight:700; color:#b45309; margin-bottom:2px; {{ $m->sender_type === 'ADMIN' ? 'text-align:right;' : '' }}">{{ __('admin_vendors.amendment_request_tag') }}</div>
                        @endif
                        <div style="font-size:10.5px; line-height:1.4; border-radius:10px; padding:6px 10px; word-break:break-word; {{ $m->sender_type === 'ADMIN' ? 'background:#1565C0; color:#fff;' : 'background:#f3f4f6; color:#1a2b3c;' }}">{{ $m->message }}</div>
                        @if($m->attachment_path)
                        <div style="margin-top:3px; {{ $m->sender_type === 'ADMIN' ? 'text-align:right;' : '' }}">
                            <a href="{{ route('admin.vendors.onboarding-workflow.attachment', [$vendor->vendor_id, $m->message_id]) }}" target="_blank" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:5px; padding:2px 8px; font-size:8.5px; font-weight:600; display:inline-block;">&#128206; {{ $m->attachment_file_name }}</a>
                        </div>
                        @endif
                        <div style="font-size:8.5px; color:#9ca3af; margin-top:2px; {{ $m->sender_type === 'ADMIN' ? 'text-align:right;' : '' }}">{{ $m->sender_type === 'ADMIN' ? ($adminNames[$m->sender_admin_id] ?? __('admin_vendors.admin_fallback_name')) : $vendor->vendor_name }} &middot; {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:ia') }}</div>
                    </div>
                </div>
                @empty
                <div style="margin:auto; color:#9ca3af; font-size:10.5px;">{{ __('admin_vendors.no_messages_send_below') }}</div>
                @endforelse
            </div>

            {{-- NEW 13 Aug 2026 — Prev/Next for the thread itself, same
                 bottom bar convention as every list screen in the app.
                 Hidden entirely when there's only one page, so it never
                 clutters a short conversation. --}}
            @if($owLastPage > 1)
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px; margin-top:2px; border-top:1px solid #f3f4f6;">
                @if($owPage <= 1)
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ route('admin.vendors.onboarding-workflow.show', ['vendorId' => $vendor->vendor_id, 'page' => $owPage - 1]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:9px; color:#6b7280;">{{ __('admin_vendors.thread_page_of', ['current' => $owPage, 'last' => $owLastPage, 'total' => $owTotalMessages]) }}</span>
                @if($owPage >= $owLastPage)
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</span>
                @else
                    <a href="{{ route('admin.vendors.onboarding-workflow.show', ['vendorId' => $vendor->vendor_id, 'page' => $owPage + 1]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
                @endif
            </div>
            @endif

            <div style="flex-shrink:0; margin-top:8px; padding-top:8px; border-top:1px solid #f3f4f6; display:flex; flex-direction:column; gap:6px;">
                <form method="POST" action="{{ route('admin.vendors.onboarding-workflow.message', $vendor->vendor_id) }}" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:6px;">
                    @csrf
                    {{-- CHANGED 13 Aug 2026 per Chris: "resize the message
                         box bigger" — was height:44px. --}}
                    <textarea name="message" id="owMsgBody" required maxlength="2000" placeholder="{{ __('admin_vendors.message_placeholder_admin') }}" style="width:100%; height:80px; resize:none; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;"></textarea>
                    {{-- NEW 13 Aug 2026 per Chris: "ask carolyn to help" on
                         this message box, formatted the same way as Help
                         Desk's reply box — automatic spelling/wording
                         check that fires after typing pauses, never a
                         mode toggle Admin has to remember to switch. --}}
                    <div id="owMsgCarolynBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:6px 8px; font-size:10.5px; max-height:110px; overflow-y:auto; box-sizing:border-box;">
                        <div id="owMsgCarolynStatus" style="color:#7c3aed; font-weight:600;">{{ __('admin_vendors.carolyn_checking') }}</div>
                        <div id="owMsgCarolynResult" style="display:none;">
                            <div id="owMsgCarolynPreview" style="white-space:pre-wrap; color:#111827; margin-bottom:6px;"></div>
                            <div style="display:flex; gap:6px;">
                                <button type="button" id="owMsgCarolynAccept" style="background:#16a34a; color:#fff; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('admin_vendors.use_this_button') }}</button>
                                <button type="button" id="owMsgCarolynRetype" style="background:#f3f4f6; color:#6b7280; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; cursor:pointer;">{{ __('admin_vendors.retype_button') }}</button>
                            </div>
                        </div>
                    </div>
                    <div style="font-size:9px; color:#9ca3af;">{{ __('admin_vendors.carolyn_hint') }}</div>
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:6px;">
                        <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" style="font-size:9px; max-width:60%;">
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 16px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.send_button') }}</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.vendors.onboarding-workflow.amendment', $vendor->vendor_id) }}" style="display:flex; gap:6px; align-items:center; flex-wrap:nowrap; background:#fef9c3; border-radius:6px; padding:6px 8px;">
                    @csrf
                    <input type="text" name="item_label" required maxlength="150" placeholder="{{ __('admin_vendors.amendment_item_placeholder') }}" style="flex:1; min-width:0; border:1px solid #eab308; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    <input type="text" name="request_note" required maxlength="2000" placeholder="{{ __('admin_vendors.amendment_note_placeholder') }}" style="flex:1; min-width:0; border:1px solid #eab308; border-radius:5px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;">
                    {{-- NEW 14 Aug 2026 — per Chris: "Amendment auto-updating
                         final registration copy Please build." Optional —
                         only pick this when the fix is a plain field
                         correction (e.g. phone number), so resolving it
                         later can apply the corrected value in one click
                         instead of Admin retyping it in Master File >
                         Vendors. Leave as "— not a specific field —" for
                         document-type amendments (SSM re-upload etc.). --}}
                    <select name="target_field" style="flex-shrink:0; width:150px; border:1px solid #eab308; border-radius:5px; padding:4px 6px; font-size:9px; background:#fff;">
                        <option value="">{{ __('admin_vendors.not_specific_field_option') }}</option>
                        @foreach(\App\Http\Controllers\Admin\VendorOnboardingWorkflowController::AMENDABLE_FIELDS as $fKey => $fLabel)
                        <option value="{{ $fKey }}">{{ $fLabel }}</option>
                        @endforeach
                    </select>
                    <button type="submit" style="background:#b45309; color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9px; font-weight:600; cursor:pointer; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.request_amendment_button') }}</button>
                </form>
            </div>
        </div>

        {{-- TAB: Amendment Log --}}
        <div id="owTabPane-amend" style="display:none; flex:1; min-height:0; flex-direction:column;">
            <div style="flex:1; min-height:0; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
                    <colgroup><col style="width:16%;"><col style="width:24%;"><col style="width:12%;"><col style="width:10%;"><col style="width:38%;"></colgroup>
                    <thead>
                        <tr style="background:#f0f9ff;">
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_item') }}</th>
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('admin_vendors.col_request') }}</th>
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_status') }}</th>
                            <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_date') }}</th>
                            <th style="text-align:center; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($amendments as $a)
                        @php
                            $aColor = match($a->status) { 'RESOLVED' => '#166534', 'SUBMITTED' => '#1565C0', default => '#854d0e' };
                            $aBg = match($a->status) { 'RESOLVED' => '#f0fdf4', 'SUBMITTED' => '#e0f2fe', default => '#fef9c3' };
                        @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:5px 6px; vertical-align:top; font-weight:600; color:#263238; word-break:break-word;">
                                {{ $a->item_label }}
                                @if($a->target_field)
                                <div style="font-size:8px; color:#6D28D9; font-weight:600; margin-top:2px;">&#128279; {{ \App\Http\Controllers\Admin\VendorOnboardingWorkflowController::AMENDABLE_FIELDS[$a->target_field] ?? $a->target_field }}</div>
                                @endif
                            </td>
                            <td style="padding:5px 6px; vertical-align:top; color:#4b5563; word-break:break-word;">
                                {{ $a->request_note }}
                                @if($a->applied_value)
                                <div style="font-size:8.5px; color:#166534; margin-top:3px;">{{ __('admin_vendors.applied_value_note', ['value' => $a->applied_value]) }}</div>
                                @endif
                            </td>
                            <td style="padding:5px 6px; vertical-align:top;"><span style="font-size:8.5px; font-weight:700; padding:2px 7px; border-radius:10px; white-space:nowrap; color:{{ $aColor }}; background:{{ $aBg }};">{{ $a->status }}</span></td>
                            <td style="padding:5px 6px; vertical-align:top; color:#9ca3af; white-space:nowrap;">{{ \Carbon\Carbon::parse($a->created_at)->format('d M Y') }}</td>
                            <td style="padding:5px 6px; vertical-align:top;">
                                @if($a->status !== 'RESOLVED')
                                    @if($a->target_field)
                                    {{-- NEW 14 Aug 2026 — per Chris: "Amendment
                                         auto-updating final registration
                                         copy Please build." Type the
                                         corrected value once here and
                                         Apply pushes it straight onto the
                                         vendor's record AND resolves this
                                         row in one click. --}}
                                    <form method="POST" action="{{ route('admin.vendors.onboarding-workflow.amendment-apply', [$vendor->vendor_id, $a->amendment_id]) }}" style="display:flex; gap:4px; align-items:center; flex-wrap:nowrap; margin-bottom:4px;">
                                        @csrf
                                        <input type="text" name="new_value" required maxlength="255" placeholder="{{ __('admin_vendors.corrected_value_placeholder') }}" style="flex:1; min-width:0; border:1px solid #6D28D9; border-radius:4px; padding:3px 6px; font-size:8.5px; box-sizing:border-box;">
                                        <button type="submit" style="background:#6D28D9; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap; flex-shrink:0;">{{ __('admin_vendors.apply_resolve_button') }}</button>
                                    </form>
                                    @endif
                                <form method="POST" action="{{ route('admin.vendors.onboarding-workflow.amendment-resolve', [$vendor->vendor_id, $a->amendment_id]) }}">
                                    @csrf
                                    <button type="submit" style="background:#f3f4f6; color:#374151; border:1px solid #d1d5db; border-radius:5px; padding:3px 9px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('admin_vendors.mark_resolved_button') }}{{ $a->target_field ? __('admin_vendors.no_value_suffix') : '' }}</button>
                                </form>
                                @else
                                <span style="color:#9ca3af; font-size:8.5px;">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="padding:20px; text-align:center; color:#9ca3af;">{{ __('admin_vendors.no_amendments_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <div style="flex-shrink:0; padding-top:8px; margin-top:6px; border-top:1px solid #f3f4f6;">
        <a href="{{ route('admin.vendors.onboarding-workflow') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700; white-space:nowrap; display:inline-block;">{{ __('network.prev') }}</a>
    </div>
</div>

<script>
function owShowTab(tab) {
    ['thread', 'amend'].forEach(function(t) {
        var pane = document.getElementById('owTabPane-' + t);
        var btn = document.getElementById('owTabBtn-' + t);
        if (pane) { pane.style.display = (t === tab) ? 'flex' : 'none'; }
        if (btn) {
            btn.style.background = (t === tab) ? '#1565C0' : '#F7FAFC';
            btn.style.color = (t === tab) ? '#fff' : '#6b7280';
        }
    });
}

// NEW 13 Aug 2026 per Chris: "ask carolyn to hep" on this message box,
// same automatic spelling/wording check Help Desk's reply box uses
// (see help-desk/show.blade.php) — checks 1.5s after typing pauses and
// again on blur, only prompts if Carolyn actually suggests a change.
(function () {
    var OW_I18N = { carolynChecking: @json(__('admin_vendors.carolyn_checking')) };
    var bodyEl = document.getElementById('owMsgBody');
    var box = document.getElementById('owMsgCarolynBox');
    if (!bodyEl || !box) { return; }
    var status = document.getElementById('owMsgCarolynStatus');
    var result = document.getElementById('owMsgCarolynResult');
    var preview = document.getElementById('owMsgCarolynPreview');
    var lastChecked = '';

    function runCheck() {
        var bodyVal = bodyEl.value.trim();
        if (bodyVal.length < 4 || bodyVal === lastChecked) { return; }
        lastChecked = bodyVal;

        box.style.display = 'block';
        status.style.display = 'block';
        status.textContent = OW_I18N.carolynChecking;
        result.style.display = 'none';

        fetch('{{ route('ai-write-assist') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
            body: JSON.stringify({ body: bodyVal, content_type: 'vendor_onboarding_message' })
        }).then(function (r) { return r.json(); }).then(function (data) {
            status.style.display = 'none';
            if (data.status !== 'OK' || data.body === bodyVal) {
                box.style.display = 'none';
                return;
            }
            preview.textContent = data.body;
            result.style.display = 'block';
        }).catch(function () {
            box.style.display = 'none';
        });
    }

    var debounceTimer = null;
    bodyEl.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runCheck, 1500);
    });
    bodyEl.addEventListener('blur', function () { clearTimeout(debounceTimer); runCheck(); });

    document.getElementById('owMsgCarolynAccept').addEventListener('click', function () {
        bodyEl.value = preview.textContent;
        box.style.display = 'none';
    });
    document.getElementById('owMsgCarolynRetype').addEventListener('click', function () {
        box.style.display = 'none';
        bodyEl.focus();
    });
})();
</script>

@endsection
