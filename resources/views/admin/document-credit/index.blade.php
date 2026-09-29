@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('document_credit.title'))

@section('content')

{{-- NEW 21 Jul 2026 — Admin side of the Document Credit Wallet.
     3 folder-style tabs (same pattern as Notification Setup) so each
     fits on one screen without scrolling: Pending Top-Ups (review +
     approve/reject bank-in slips), Agent Balances (overview), Settings
     (the flat RM amount deducted per document read). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="dcTabBtn" data-tab="dcPending" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">{{ __('document_credit.pending_topups_tab_label') }} ({{ $pendingRequests->total() }})</button>
        <button type="button" class="dcTabBtn" data-tab="dcBalances" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('document_credit.agent_balances_tab_label') }}</button>
        <button type="button" class="dcTabBtn" data-tab="dcSettings" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('document_credit.settings_tab_label') }}</button>
    </div>
</div>

<div style="flex:1 1 auto; min-height:0; padding-bottom:10px;">

    {{-- TAB 1 — PENDING TOP-UPS. NOTE: per the documented footgun (never
         put display:grid/flex directly on an element whose own
         .style.display is toggled by tab JS — it silently overwrites
         the layout), the flex column lives on the INNER wrapper below,
         not on #dcPending itself, which the JS toggles as plain
         block/none. --}}
    <div id="dcPending" class="dcTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; height:100%; box-sizing:border-box;">
        <div style="height:100%; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_agent') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_requested') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_slip') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingRequests as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px;">{{ $r->full_name }} <span style="color:#9ca3af;">({{ $r->agent_code }} — {{ $r->role }})</span></td>
                        <td style="padding:5px 8px;">{{ \Carbon\Carbon::parse($r->requested_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:5px 8px; font-weight:600;">RM {{ number_format($r->amount_requested, 2) }}</td>
                        <td style="padding:5px 8px;"><a href="{{ route('admin.document-credit.slip', $r->request_id) }}" target="_blank" style="color:#1565C0; font-weight:600; text-decoration:none;">{{ __('document_credit.view_slip_link') }}</a></td>
                        <td style="padding:5px 8px;">
                            <form method="POST" action="{{ route('admin.document-credit.approve', $r->request_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('masterfile.approve_button') }}</button>
                            </form>
                            <button type="button" onclick="dcReject('{{ $r->request_id }}')" style="background:#e53935; color:#fff; border:none; border-radius:5px; padding:4px 10px; font-size:9px; font-weight:600; cursor:pointer; margin-left:4px;">{{ __('masterfile.reject_button') }}</button>
                            <form id="dcRejectForm-{{ $r->request_id }}" method="POST" action="{{ route('admin.document-credit.reject', $r->request_id) }}" style="display:none;">
                                @csrf
                                <input type="hidden" name="admin_note" id="dcRejectNote-{{ $r->request_id }}">
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('document_credit.no_pending_topups_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($pendingRequests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $pendingRequests->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('document_credit.page_x_of_y_pending_paren', ['current' => $pendingRequests->currentPage(), 'last' => $pendingRequests->lastPage(), 'total' => $pendingRequests->total()]) }}</span>
            @if($pendingRequests->hasMorePages())
                <a href="{{ $pendingRequests->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>

        {{-- CHANGED 8 Aug 2026 per Chris: strict no-scroll rule — was
             max-height:120px; overflow-y:auto. Capped to the latest 3 in
             the controller instead (secondary reference list, not the
             primary action queue), so it never needs to scroll. --}}
        @if($reviewedRequests->count())
        <div style="flex-shrink:0; margin-top:6px; padding-top:6px; border-top:1px solid #f3f4f6;">
            <div style="font-size:9px; font-weight:700; color:#6b7280; margin-bottom:3px;">{{ __('document_credit.recently_reviewed_heading') }}</div>
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                @foreach($reviewedRequests as $r)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:3px 8px;">{{ $r->full_name }} ({{ $r->agent_code }})</td>
                    <td style="padding:3px 8px;">RM {{ number_format($r->amount_requested, 2) }}</td>
                    <td style="padding:3px 8px;"><span style="padding:1px 6px; border-radius:20px; font-weight:600; background:{{ $r->status === 'APPROVED' ? '#e8f5e9' : '#fde8e8' }}; color:{{ $r->status === 'APPROVED' ? '#1b5e20' : '#b71c1c' }};">{{ $r->status === 'APPROVED' ? __('points.status_approved') : __('points.status_rejected') }}</span></td>
                    <td style="padding:3px 8px; color:#9ca3af;">{{ \Carbon\Carbon::parse($r->reviewed_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif
        </div>
    </div>

    {{-- TAB 2 — AGENT BALANCES: search + typeahead, paginated, each row
         drills down to that agent's full top-up + usage history. Same
         wrapper-div-holds-flex pattern as the Pending Top-Ups tab
         above, so the outer #dcBalances stays a plain block/none
         toggle target. --}}
    <div id="dcBalances" class="dcTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; height:100%; box-sizing:border-box;">
        <div style="height:100%; display:flex; flex-direction:column;">

        {{-- REBUILT AGAIN 21 Jul 2026 — per Chris: all 3 levels (GL, TL,
             Introducer) must be visible from the start, not appear one
             at a time — otherwise it looks like GL is the only filter
             on offer. TL/Introducer now always render as real <select>
             elements; they're just disabled with a placeholder option
             until their parent level is chosen. Choosing any level
             clears the levels below it and resubmits. The name/code
             search box narrows further within whatever level is
             currently selected. --}}
        <form method="GET" id="dcFilterForm" action="{{ route('admin.document-credit.index') }}" style="position:relative; margin-bottom:8px; display:flex; gap:6px; flex-wrap:wrap; align-items:center;" autocomplete="off">
            <select name="gl_id" onchange="this.form.tl_id.value=''; this.form.introducer_id.value=''; this.form.submit();" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff; min-width:160px;">
                <option value="">{{ __('document_credit.choose_role_option', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</option>
                @foreach($glOptions as $gl)
                <option value="{{ $gl->agent_id }}" {{ $glId === $gl->agent_id ? 'selected' : '' }}>{{ $gl->full_name }} ({{ $gl->agent_code }})</option>
                @endforeach
            </select>

            @if($glId)
            <select name="tl_id" onchange="this.form.introducer_id.value=''; this.form.submit();" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff; min-width:160px;">
                <option value="">{{ __('document_credit.all_role_plural_option', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</option>
                @foreach($tlOptions as $tl)
                <option value="{{ $tl->agent_id }}" {{ $tlId === $tl->agent_id ? 'selected' : '' }}>{{ $tl->full_name }} ({{ $tl->agent_code }})</option>
                @endforeach
            </select>
            @else
            <select disabled title="{{ __('document_credit.choose_role_first_title', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}" style="border:1px solid #e5e7eb; border-radius:6px; padding:6px 8px; font-size:10px; background:#f3f4f6; color:#9ca3af; min-width:160px;">
                <option>{{ __('document_credit.choose_role_first_option', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</option>
            </select>
            <input type="hidden" name="tl_id" value="">
            @endif

            @if($tlId)
            <select name="introducer_id" onchange="this.form.submit();" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff; min-width:160px;">
                <option value="">{{ __('document_credit.all_role_plural_option', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</option>
                @foreach($introOptions as $intro)
                <option value="{{ $intro->agent_id }}" {{ $introId === $intro->agent_id ? 'selected' : '' }}>{{ $intro->full_name }} ({{ $intro->agent_code }})</option>
                @endforeach
            </select>
            @else
            <select disabled title="{{ __('document_credit.choose_role_first_title', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}" style="border:1px solid #e5e7eb; border-radius:6px; padding:6px 8px; font-size:10px; background:#f3f4f6; color:#9ca3af; min-width:160px;">
                <option>{{ __('document_credit.choose_role_first_option', ['role' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</option>
            </select>
            <input type="hidden" name="introducer_id" value="">
            @endif

            <div style="position:relative; flex:1; min-width:180px; max-width:280px;">
                <input type="text" id="dcAgentSearchBox" name="agent_search" value="{{ $agentSearch }}" placeholder="{{ __('document_credit.or_search_name_code_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                <div id="dcTypeaheadList" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:0 0 6px 6px; z-index:20; max-height:180px; overflow-y:auto;"></div>
            </div>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('gl.search_button') }}</button>
            @if($agentSearch || $glId || $tlId || $introId)
            <a href="{{ route('admin.document-credit.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; display:flex; align-items:center;">{{ __('dashboard.clear_word') }}</a>
            @endif
        </form>

        @if(!$filterApplied)
        {{-- FIXED 21 Jul 2026 — per Chris: never load/display every
             agent by default. Nothing is queried until a level is
             chosen above or a search is typed — with hundreds (or one
             day, thousands) of agents, "show all" on first load is
             exactly what he told us not to build. --}}
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="text-align:center; color:#9ca3af; font-size:10.5px; max-width:320px; line-height:1.5;">
                {{ __('document_credit.filter_prompt_note', ['gl' => \App\Services\RoleLabelService::label('GROUP_LEADER'), 'tl' => \App\Services\RoleLabelService::label('TEAM_LEADER'), 'intro' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}
            </div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_agent') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_code') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_role') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($agentBalances as $a)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('admin.document-credit.agent-detail', $a->agent_id) }}'">
                        <td style="padding:5px 8px; color:#1565C0; font-weight:600;">{{ $a->full_name }} &rarr;</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->agent_code }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->role }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:600; {{ $a->document_credit_balance <= 0 ? 'color:#b71c1c;' : 'color:#1b5e20;' }}">RM {{ number_format($a->document_credit_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('document_credit.no_agents_found_note') }}{{ $agentSearch ? __('document_credit.matching_search_suffix', ['term' => $agentSearch]) : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($agentBalances->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $agentBalances->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('document_credit.page_x_of_y_agents_paren', ['current' => $agentBalances->currentPage(), 'last' => $agentBalances->lastPage(), 'total' => $agentBalances->total()]) }}</span>
            @if($agentBalances->hasMorePages())
                <a href="{{ $agentBalances->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif

        </div>
    </div>

    {{-- TAB 3 — SETTINGS --}}
    <div id="dcSettings" class="dcTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:12px; height:100%; box-sizing:border-box;">
        <form method="POST" action="{{ route('admin.document-credit.settings') }}">
            @csrf
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('document_credit.deduction_amount_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; max-width:480px; line-height:1.4;">
                {{ __('document_credit.deduction_amount_desc') }}
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <label style="font-size:9px; font-weight:600; color:#374151;">RM</label>
                <input type="number" step="0.01" min="0.01" name="deduction_amount" value="{{ $deductionAmount }}" required style="width:120px; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
            </div>

            {{-- NEW 21 Jul 2026 — per Chris: flat processing fee taken
                 out of every approved bank-slip top-up before it's
                 credited to the agent's balance. --}}
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-top:8px; border-top:1px solid #f3f4f6;">{{ __('document_credit.processing_fee_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; max-width:480px; line-height:1.4;">
                {{ __('document_credit.processing_fee_desc', ['fee' => number_format($topupFee, 2), 'net' => number_format(max(0, 150 - $topupFee), 2)]) }}
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:9px; font-weight:600; color:#374151;">RM</label>
                <input type="number" step="0.01" min="0" name="topup_fee" value="{{ $topupFee }}" required style="width:120px; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
            </div>

            {{-- NEW 22 Jul 2026 — per Chris: Help Desk message
                 translation and "Fix Wording" rephrase are TL/Introducer
                 -only features that both call the Claude API, charged
                 from this same wallet with an up-front confirm popup. --}}
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-top:8px; border-top:1px solid #f3f4f6;">{{ __('document_credit.translation_fee_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; max-width:480px; line-height:1.4;">
                {{ __('document_credit.translation_fee_desc', ['tl' => \App\Services\RoleLabelService::label('TEAM_LEADER'), 'intro' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}
            </div>
            <div style="display:flex; align-items:center; gap:8px; margin-bottom:16px;">
                <label style="font-size:9px; font-weight:600; color:#374151;">RM</label>
                <input type="number" step="0.01" min="0" name="translation_fee" value="{{ $translationFee }}" required style="width:120px; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
            </div>

            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px; padding-top:8px; border-top:1px solid #f3f4f6;">{{ __('document_credit.rephrase_fee_heading') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:10px; max-width:480px; line-height:1.4;">
                {{ __('document_credit.rephrase_fee_desc', ['tl' => \App\Services\RoleLabelService::label('TEAM_LEADER'), 'intro' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:9px; font-weight:600; color:#374151;">RM</label>
                <input type="number" step="0.01" min="0" name="rephrase_fee" value="{{ $rephraseFee }}" required style="width:120px; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; box-sizing:border-box;">
            </div>

            <button type="submit" style="margin-top:16px; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('growth.save_button') }}</button>
        </form>
    </div>

</div>
</div>

<script>
var DC_I18N = {
    rejectReasonPrompt: @json(__('document_credit.reject_reason_prompt_js'))
};

// FIXED 21 Jul 2026 — GL/TL/Introducer dropdowns are now rendered
// server-side directly (see controller), each already scoped to the
// level above and auto-submitting via inline onchange= — no AJAX
// population needed here any more.

// Typeahead for the Agent Balances search box — FIXED 21 Jul 2026: now
// scoped to whatever GL/TL/Introducer is currently selected, instead
// of searching blindly across every agent.
(function() {
    var box = document.getElementById('dcAgentSearchBox');
    var list = document.getElementById('dcTypeaheadList');
    if (!box) return;
    var timer = null;

    box.addEventListener('input', function() {
        clearTimeout(timer);
        var q = box.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(function() {
            var form = box.form;
            var glVal = form.gl_id ? form.gl_id.value : '';
            var tlVal = form.tl_id ? form.tl_id.value : '';
            var introVal = form.introducer_id ? form.introducer_id.value : '';
            var url = '{{ route('admin.document-credit.agent-typeahead') }}?q=' + encodeURIComponent(q)
                + '&gl_id=' + encodeURIComponent(glVal) + '&tl_id=' + encodeURIComponent(tlVal) + '&introducer_id=' + encodeURIComponent(introVal);
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data.length) { list.style.display = 'none'; return; }
                    data.forEach(function(item) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:6px 8px; font-size:10px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.name + ' (' + item.code + ')';
                        row.addEventListener('mousedown', function() {
                            box.value = item.name;
                            list.style.display = 'none';
                            box.form.submit();
                        });
                        list.appendChild(row);
                    });
                    list.style.display = 'block';
                });
        }, 200);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== box) list.style.display = 'none';
    });
})();

function dcReject(requestId) {
    var reason = prompt(DC_I18N.rejectReasonPrompt);
    if (reason === null || reason.trim() === '') return;
    document.getElementById('dcRejectNote-' + requestId).value = reason;
    document.getElementById('dcRejectForm-' + requestId).submit();
}

(function() {
    var tabBtns = document.querySelectorAll('.dcTabBtn');
    var tabPanels = document.querySelectorAll('.dcTabPanel');

    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });

    activateTab('dcPending');
})();
</script>
@endsection
