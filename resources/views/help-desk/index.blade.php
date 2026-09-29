@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('help-desk.title'))

@section('content')

{{-- REDESIGNED 21 Jul 2026 — Help Desk (was agent-to-Admin-only
     "Enquiries"). Per Chris: every role — Admin included — can message
     their own upline and downline (never sideways to a peer), with an
     optional Cc to anyone reachable by both sender and recipient, and
     an optional attachment (e.g. a bank-in slip). Admin picks a "To"
     via a typeahead search box (their eligible list is literally
     everyone); every other role sees a plain dropdown of their own
     upline+downline, which is naturally small. Gmail-style inbox: flag
     column, paperclip for attachments, bold+red-dot for unread.

     TRANSLATED 22 Jul 2026 — per Chris: TL/Introducer-only screen
     translation. Every static label below goes through __('help-desk.*')
     (see lang/en|zh|ms/help-desk.php) — the active locale is set by
     App\Http\Middleware\SetAgentLocale based on the viewer's own
     preferred_language. GL/Admin always resolve to English. Data
     (names, subjects, message bodies) is NEVER touched by this —
     only the fixed screen chrome. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">
    {{-- REMOVED 8 Aug 2026 per Chris: strict rule is ONLY the bottom
         Prev/Next pair may navigate — this top "← Prev" was actually a
         back-to-dashboard link mislabeled as Prev, which is exactly what
         the rule forbids. Use the sidebar to leave this screen instead. --}}

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="hdTabBtn" data-tab="hdInbox" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:var(--gl-blue); cursor:pointer; position:relative; top:1px;">{{ __('help-desk.tab_inbox') }} ({{ $myThreads->total() }})</button>
        <button type="button" class="hdTabBtn" data-tab="hdNew" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('help-desk.tab_new_message') }}</button>
    </div>
</div>

<div style="flex:1 1 auto; min-height:0; padding-bottom:10px;">

    <div id="hdInbox" class="hdTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; height:100%; box-sizing:border-box;">
        <div style="height:100%; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="width:24px; padding:4px 4px;"></th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_with') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_subject') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_last_activity') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('help-desk.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $hdCategoryLabels = [
                            'GENERAL' => __('help-desk.category_general'),
                            'CLAIM_UPDATE' => __('help-desk.category_claim_update'),
                            'TOPUP_PAYMENT' => __('help-desk.category_topup_payment'),
                            'DATA_CORRECTION' => __('help-desk.category_data_correction'),
                            // MERGED 12 Aug 2026 per Chris: Carolyn AI Tickets folded into
                            // Help Desk — these are the categories she logs under.
                            'COMPLAINT' => 'Complaint',
                            'QUESTION' => 'Question',
                            'BUG_REPORT' => 'Bug Report',
                            'OTHER' => 'Other',
                        ];
                        $hdRoleLabels = [
                            'ADMIN' => __('help-desk.role_admin'),
                            'GROUP_LEADER' => __('help-desk.role_gl'),
                            'TEAM_LEADER' => __('help-desk.role_tl'),
                            'INTRODUCER' => __('help-desk.role_introducer'),
                        ];
                        $hdPriorityColors = ['HIGH' => '#e53935', 'MEDIUM' => '#F6AD55', 'LOW' => '#9ca3af'];
                    @endphp
                    @forelse($myThreads as $t)
                    @php
                        $isCarolynTicket = $t->created_by_type === 'CAROLYN_AI';
                        $isInitiator = $t->initiator_agent_id === $agent->agent_id;
                        $viewedAt = $isInitiator ? $t->last_viewed_by_initiator_at : $t->last_viewed_by_recipient_at;
                        $unread = $isCarolynTicket
                            ? ($t->status === 'OPEN')
                            : ($isInitiator
                                ? (!$viewedAt || \Carbon\Carbon::parse($t->last_message_at)->gt(\Carbon\Carbon::parse($viewedAt)))
                                : (($t->recipient_agent_id === $agent->agent_id) && (!$viewedAt || \Carbon\Carbon::parse($t->last_message_at)->gt(\Carbon\Carbon::parse($viewedAt)))));
                        $flagged = $isInitiator ? $t->flagged_by_initiator : $t->flagged_by_recipient;
                        // MERGED 12 Aug 2026 per Chris: a Carolyn-logged ticket has no
                        // single human recipient — show who/what actually created it
                        // instead of a "To:"/"From:" correspondent.
                        $withLabel = $isCarolynTicket ? 'Created by' : ($isInitiator ? __('help-desk.to_label') : __('help-desk.from_label'));
                        $withName = $isCarolynTicket
                            ? ('🤖 Carolyn AI Agent' . ($t->initiator_name ? " ({$t->initiator_name})" : ($t->guest_name ? " (Guest: {$t->guest_name})" : ' (Guest)')))
                            : ($isInitiator ? $t->recipient_name : $t->initiator_name);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer; {{ $unread ? 'background:#f0f9ff;' : '' }}" onclick="window.location='{{ route('help-desk.show', $t->thread_id) }}'">
                        <td style="padding:5px 4px; text-align:center;">
                            <form method="POST" action="{{ route('help-desk.flag', $t->thread_id) }}" onclick="event.stopPropagation();" style="display:inline;">
                                @csrf
                                <button type="submit" title="Flag" style="background:none; border:none; cursor:pointer; font-size:12px; color:{{ $flagged ? '#F6AD55' : '#d1d5db' }};">&#9733;</button>
                            </form>
                        </td>
                        <td style="padding:5px 8px; color:#374151;"><span style="color:#9ca3af;">{{ $withLabel }}:</span> {{ $withName ?? '—' }}</td>
                        <td style="padding:5px 8px; color:var(--gl-blue); font-weight:{{ $unread ? '700' : '600' }};">
                            @if($unread)<span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#F44336; margin-right:5px;"></span>@endif
                            @if($t->priority && isset($hdPriorityColors[$t->priority]))<span title="{{ ucfirst(strtolower($t->priority)) }} priority" style="display:inline-block; width:6px; height:6px; border-radius:50%; background:{{ $hdPriorityColors[$t->priority] }}; margin-right:5px;"></span>@endif
                            {{ $t->subject }}
                            @if($t->has_attachment)<span style="color:#9ca3af; margin-left:4px;" title="{{ __('help-desk.has_attachment') }}">&#128206;</span>@endif
                            &rarr;
                        </td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $hdCategoryLabels[$t->category] ?? str_replace('_', ' ', $t->category) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \Carbon\Carbon::parse($t->last_message_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:5px 8px; text-align:right;">
                            <span style="padding:2px 8px; border-radius:20px; font-size:8.5px; font-weight:600;
                                background:{{ $t->status === 'OPEN' ? '#fff8e1' : '#f3f4f6' }};
                                color:{{ $t->status === 'OPEN' ? '#92400e' : '#6b7280' }};">{{ $t->status === 'OPEN' ? __('help-desk.status_open') : __('help-desk.status_closed') }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('help-desk.no_messages') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($myThreads->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">&larr; {{ __('help-desk.prev') }}</span>
            @else
                <a href="{{ $myThreads->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">&larr; {{ __('help-desk.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('help-desk.page_of', ['current' => $myThreads->currentPage(), 'last' => $myThreads->lastPage(), 'total' => $myThreads->total()]) }}</span>
            @if($myThreads->hasMorePages())
                <a href="{{ $myThreads->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('help-desk.next') }} &rarr;</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('help-desk.next') }} &rarr;</span>
            @endif
        </div>
        </div>
    </div>

    <div id="hdNew" class="hdTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:10px 14px; height:100%; box-sizing:border-box; overflow:hidden;">
        {{-- ADJUSTED 22 Jul 2026 — Chris: "no scroll up down left right, must
             in one screen." Was a single stacked column that overflowed the
             panel on shorter viewports. Rebuilt as two side-by-side columns
             (fields on the left, message on the right) so the whole form's
             vertical footprint is roughly halved and fits without scrolling. --}}
        <div style="font-size:11px; font-weight:700; color:var(--gl-blue); margin-bottom:6px;">{{ __('help-desk.new_message_heading') }}</div>
        <form method="POST" action="{{ route('help-desk.store') }}" enctype="multipart/form-data" id="hdComposeForm" style="display:flex; gap:16px; height:calc(100% - 22px);">

            @csrf

            {{-- COMPACTED 12 Aug 2026 — per Chris (bottom-of-panel truncation
                 screenshot): adding the Cc search field grew this column
                 taller than before, clipping the Send button off the
                 bottom. Every field's spacing below is trimmed slightly
                 (margins, label gaps, chip-row height) to reclaim that
                 room — six field-groups (To/Cc/Category/Subject/
                 Attachment/Send) must still all fit with zero scroll. --}}
            <div style="flex:0 0 250px; min-width:0; display:flex; flex-direction:column; overflow:hidden;">

                <label style="font-size:8.5px; font-weight:600; color:#374151; display:block; margin-bottom:1px;">{{ __('help-desk.to_label') }} <span style="color:#e53935;">*</span></label>

                @if($agent->role === 'ADMIN')
                {{-- CHANGED 12 Aug 2026 per Chris: "why to didnt display
                     Chris Yap, why show selected? should display in To
                     Chris Yap" — after picking someone, their name now
                     fills the To box itself (readonly, looks like a
                     normal answered field) instead of leaving it empty
                     with a separate "Selected: ..." confirmation line
                     underneath. A ✕ clears it to search again. --}}
                <div style="position:relative; margin-bottom:4px;">
                    <input type="text" id="hdRecipientSearch" autocomplete="off" placeholder="{{ __('help-desk.search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:4px 7px; font-size:10px; box-sizing:border-box;">
                    <button type="button" id="hdRecipientClear" title="Change recipient" style="display:none; position:absolute; right:6px; top:50%; transform:translateY(-50%); background:none; border:none; color:#9ca3af; font-size:13px; font-weight:700; cursor:pointer; line-height:1; padding:2px;">&times;</button>
                    <input type="hidden" name="recipient_id" id="hdRecipientId">
                    <div id="hdRecipientResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #E2E8F0; border-radius:0 0 6px 6px; max-height:140px; overflow-y:auto; z-index:20; box-shadow:0 4px 10px rgba(0,0,0,0.08);"></div>
                </div>
                @else
                <select name="recipient_id" id="hdRecipientId" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:4px 7px; font-size:10px; margin-bottom:4px; box-sizing:border-box; background:#fff;">
                    <option value="">{{ __('help-desk.select_recipient') }}</option>
                    @foreach($eligibleRecipients as $r)
                    <option value="{{ $r->agent_id }}" data-role="{{ $r->role }}">{{ $r->full_name }} ({{ $hdRoleLabels[$r->role] ?? __('help-desk.role_introducer') }})</option>
                    @endforeach
                </select>
                @endif

                {{-- REDESIGNED 12 Aug 2026 (second pass) per Chris: "i should
                     able to cc all in the group with type ahead" — the Cc
                     pool can now include the whole GL group plus every
                     Admin, which is too many to show as a flat checkbox
                     list. Search-to-add typeahead (same interaction as the
                     To field) with removable chips instead. No separate
                     "Cc" label — the placeholder already says it, and
                     dropping the label row is part of the space reclaimed
                     above; the chip row is capped to one compact line
                     (scrolls internally, same as the search results
                     dropdowns already do, if more than fit are added). --}}
                <div id="hdCcBlock" style="display:none; margin-bottom:4px;">
                    <div style="position:relative;">
                        <input type="text" id="hdCcSearch" autocomplete="off" placeholder="{{ __('help-desk.cc_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 7px; font-size:9.5px; box-sizing:border-box;">
                        <div id="hdCcResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #E2E8F0; border-radius:0 0 6px 6px; max-height:110px; overflow-y:auto; z-index:19; box-shadow:0 4px 10px rgba(0,0,0,0.08);"></div>
                    </div>
                    <div id="hdCcChips" style="display:flex; flex-wrap:wrap; gap:3px; margin-top:3px; max-height:20px; overflow-y:auto;"></div>
                </div>

                <label style="font-size:8.5px; font-weight:600; color:#374151; display:block; margin-bottom:1px;">{{ __('help-desk.col_category') }} <span style="color:#e53935;">*</span></label>
                <select name="category" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:4px 7px; font-size:10px; margin-bottom:4px; box-sizing:border-box; background:#fff;">
                    <option value="GENERAL">{{ __('help-desk.category_general') }}</option>
                    <option value="CLAIM_UPDATE">{{ __('help-desk.category_claim_update') }}</option>
                    <option value="TOPUP_PAYMENT">{{ __('help-desk.category_topup_payment') }}</option>
                    <option value="DATA_CORRECTION">{{ __('help-desk.category_data_correction') }}</option>
                </select>

                <label style="font-size:8.5px; font-weight:600; color:#374151; display:block; margin-bottom:1px;">{{ __('help-desk.subject_label') }} <span style="color:#e53935;">*</span></label>
                <input type="text" name="subject" id="hdComposeSubject" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:4px 7px; font-size:10px; margin-bottom:4px; box-sizing:border-box;">

                <label style="font-size:8.5px; font-weight:600; color:#374151; display:block; margin-bottom:1px;">&#128206; {{ __('help-desk.attachment_label') }}</label>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:9px; margin-bottom:4px; box-sizing:border-box; background:#fff;">

                <div style="margin-top:auto;">
                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:5px 20px; font-size:10.5px; font-weight:600; cursor:pointer; width:100%;">{{ __('help-desk.send') }}</button>
                </div>
            </div>

            <div style="flex:1 1 auto; min-width:0; display:flex; flex-direction:column; overflow:hidden;">
                <label style="font-size:8.5px; font-weight:600; color:#374151; display:block; margin-bottom:2px;">{{ __('help-desk.message_label') }} <span style="color:#e53935;">*</span></label>
                <textarea name="body" id="hdComposeBody" maxlength="3000" required style="width:100%; flex:1 1 auto; min-height:0; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; margin-bottom:5px; box-sizing:border-box; resize:none;"></textarea>

                @if($canUseLanguageFeatures)
                <div id="hdComposeRephraseSuggestion" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:5px 7px; font-size:9.5px; color:#0c4a6e; margin-bottom:5px; max-height:70px; overflow-y:auto; flex-shrink:0;">
                    <div style="font-weight:600; margin-bottom:2px;">{{ __('help-desk.suggested_wording') }}</div>
                    <div id="hdComposeRephraseSuggestionText" style="white-space:pre-wrap; margin-bottom:4px;"></div>
                    <button type="button" id="hdComposeRephraseAccept" style="background:var(--gl-blue); color:#fff; border:none; border-radius:4px; padding:3px 9px; font-size:9px; font-weight:600; cursor:pointer; margin-right:5px;">{{ __('help-desk.use_this') }}</button>
                    <button type="button" id="hdComposeRephraseDiscard" style="background:#f3f4f6; color:#6b7280; border:none; border-radius:4px; padding:3px 9px; font-size:9px; cursor:pointer;">{{ __('help-desk.discard') }}</button>
                </div>
                <div style="flex-shrink:0;">
                    <button type="button" id="hdComposeFixWordingBtn" data-fee="{{ $rephraseFee }}" style="background:#fff; color:var(--gl-blue); border:1px solid var(--gl-blue); border-radius:6px; padding:4px 11px; font-size:9.5px; font-weight:600; cursor:pointer;">&#10024; {{ __('help-desk.fix_wording') }}</button>
                </div>
                @else
                {{-- CHANGED 12 Aug 2026 per Chris: "i suggest you dont
                     create write myself or ask carolyn to help, you just
                     build in automatically spelling check and rephrase
                     better sentence once the subject message enter and
                     the message content, the user just see the prompt
                     action and click accept or no i re type again." No
                     mode toggle anymore — checks Subject + Message
                     automatically when the agent leaves the Message box,
                     and only pops up if she actually found something
                     worth changing. Free for every role except TL/
                     Introducer, who keep their existing paid Fix Wording
                     above instead (never both on the same field). --}}
                <div id="hdComposeCarolynBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px; margin-bottom:5px; max-height:130px; overflow-y:auto; flex-shrink:0; box-sizing:border-box;">
                    <div id="hdComposeCarolynStatus" style="color:#7c3aed; font-weight:600;">✨ Carolyn is checking your spelling and wording...</div>
                    <div id="hdComposeCarolynResult" style="display:none;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">Suggested Subject:</div>
                        <div id="hdComposeCarolynTitlePreview" style="font-weight:600; color:#111827; margin-bottom:6px;"></div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">Suggested Message:</div>
                        <div id="hdComposeCarolynBodyPreview" style="white-space:pre-wrap; color:#111827; margin-bottom:8px;"></div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" id="hdComposeCarolynAccept" style="background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">✅ Use This</button>
                            <button type="button" id="hdComposeCarolynRetype" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">✖ No, I'll Retype</button>
                        </div>
                    </div>
                </div>
                <div style="font-size:8.5px; color:#9ca3af; flex-shrink:0;">✨ Carolyn checks your spelling automatically when you finish typing.</div>
                @endif
            </div>

        </form>
    </div>

    {{-- REMOVED 12 Aug 2026 per Chris: "remove the article/faq folder" —
         this whole tab (list + Admin "+ Add Article" publish flow) made
         things more confusing, not less. Removed entirely rather than
         left half-built. --}}

</div>
</div>

<script>
// NEW 22 Jul 2026 — server-rendered strings for the bits of JS that
// need translated text (alerts, confirm popups). Keeps the dictionary
// single-sourced in lang/*/help-desk.php rather than duplicating text
// in JS literals.
var HD_I18N = {
    typeMessageFirst: @json(__('help-desk.js_type_message_first')),
    somethingWrong: @json(__('help-desk.js_something_wrong')),
    couldNotReachServer: @json(__('help-desk.js_could_not_reach_server')),
    confirmChargeTemplate: @json(__('help-desk.js_confirm_charge')),
    selectedPrefix: @json(__('help-desk.selected_prefix')),
    roleAdmin: @json(__('help-desk.role_admin')),
    roleGl: @json(__('help-desk.role_gl')),
    roleTl: @json(__('help-desk.role_tl')),
    roleIntroducer: @json(__('help-desk.role_introducer'))
};

(function() {
    var tabBtns = document.querySelectorAll('.hdTabBtn');
    var tabPanels = document.querySelectorAll('.hdTabPanel');

    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'block' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? 'var(--gl-blue)' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });

    activateTab('hdInbox');

    // NEW 9 Aug 2026 — allow other screens (e.g. Rebate Offer Search's
    // "Enquire with Admin" button) to deep-link straight into a
    // pre-filled New Message to Admin via query string, instead of
    // making the agent retype context Admin already has.
    (function () {
        var params = new URLSearchParams(window.location.search);
        var subject = params.get('prefill_subject');
        var body = params.get('prefill_body');
        var wantsAdmin = params.get('prefill_admin') === '1';
        if (!subject && !body && !wantsAdmin) { return; }
        activateTab('hdNew');
        if (subject) {
            var subjectInput = document.querySelector('#hdComposeForm input[name="subject"]');
            if (subjectInput) { subjectInput.value = subject; }
        }
        if (body) {
            var bodyBox = document.getElementById('hdComposeBody');
            if (bodyBox) { bodyBox.value = body; }
        }
        if (wantsAdmin) {
            var recipientSelect = document.getElementById('hdRecipientId');
            if (recipientSelect && recipientSelect.tagName === 'SELECT') {
                var adminOpt = recipientSelect.querySelector('option[data-role="ADMIN"]');
                if (adminOpt) { recipientSelect.value = adminOpt.value; }
            }
        }
    })();

    function hdRoleLabel(role) {
        if (role === 'GROUP_LEADER') return HD_I18N.roleGl;
        if (role === 'TEAM_LEADER') return HD_I18N.roleTl;
        if (role === 'ADMIN') return HD_I18N.roleAdmin;
        return HD_I18N.roleIntroducer;
    }

    // CHANGED 12 Aug 2026 per Chris: "you should default cc, if not in
    // the team just dont show the not found in your group" — the Cc row
    // now always shows once a recipient is picked (not hidden just
    // because nobody happens to be eligible this time), so it's obvious
    // Cc exists as an option. When nobody's eligible, the row shows
    // empty — no error text, no "not found" message.
    //
    // CHANGED AGAIN 12 Aug 2026 per Chris: "i should able to cc all in
    // the group with type ahead" — Cc pool now covers the whole GL group
    // plus every Admin (see DataScopeService::helpDeskCcOptionsFor), so
    // it's rebuilt here as search-to-add with removable chips instead of
    // a flat checkbox list. The full eligible list for this recipient is
    // fetched once and filtered client-side as the agent types.
    var hdCcAllOptions = [];
    var hdCcSelected = {};

    function loadCcOptions(recipientId) {
        var ccBlock = document.getElementById('hdCcBlock');
        var ccSearch = document.getElementById('hdCcSearch');
        var ccResults = document.getElementById('hdCcResults');
        hdCcAllOptions = [];
        hdCcSelected = {};
        renderCcChips();
        if (ccSearch) { ccSearch.value = ''; }
        if (ccResults) { ccResults.innerHTML = ''; ccResults.style.display = 'none'; }
        if (!recipientId) { ccBlock.style.display = 'none'; return; }
        ccBlock.style.display = 'block';

        fetch('{{ route('help-desk.cc-options') }}?recipient_id=' + encodeURIComponent(recipientId))
            .then(function(r) { return r.json(); })
            .then(function(list) { hdCcAllOptions = list; });
    }

    function renderCcChips() {
        var chips = document.getElementById('hdCcChips');
        chips.innerHTML = '';
        Object.keys(hdCcSelected).forEach(function(id) {
            var cc = hdCcSelected[id];
            var chip = document.createElement('span');
            chip.style.cssText = 'display:inline-flex; align-items:center; gap:4px; background:#eef2ff; color:#3730a3; border-radius:12px; padding:2px 4px 2px 8px; font-size:9px;';
            var text = document.createElement('span');
            text.textContent = cc.name + ' (' + hdRoleLabel(cc.role) + ')';
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'cc[]';
            hidden.value = id;
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.title = 'Remove';
            remove.style.cssText = 'background:none; border:none; color:#4338ca; cursor:pointer; font-weight:700; font-size:11px; padding:0 2px; line-height:1;';
            remove.addEventListener('click', function() { delete hdCcSelected[id]; renderCcChips(); });
            chip.appendChild(hidden);
            chip.appendChild(text);
            chip.appendChild(remove);
            chips.appendChild(chip);
        });
    }

    var ccSearchInput = document.getElementById('hdCcSearch');
    var ccResultsBox = document.getElementById('hdCcResults');
    ccSearchInput.addEventListener('input', function() {
        var q = ccSearchInput.value.trim().toLowerCase();
        ccResultsBox.innerHTML = '';
        if (!q) { ccResultsBox.style.display = 'none'; return; }
        var matches = hdCcAllOptions.filter(function(o) {
            return !hdCcSelected[o.id] && o.name.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        if (!matches.length) { ccResultsBox.style.display = 'none'; return; }
        matches.forEach(function(o) {
            var row = document.createElement('div');
            row.style.cssText = 'padding:5px 8px; font-size:9.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
            row.textContent = o.name + ' (' + hdRoleLabel(o.role) + ')';
            row.addEventListener('click', function() {
                hdCcSelected[o.id] = o;
                renderCcChips();
                ccSearchInput.value = '';
                ccResultsBox.innerHTML = '';
                ccResultsBox.style.display = 'none';
            });
            ccResultsBox.appendChild(row);
        });
        ccResultsBox.style.display = 'block';
    });
    ccSearchInput.addEventListener('blur', function() {
        setTimeout(function() { ccResultsBox.style.display = 'none'; }, 150);
    });

    @if($agent->role === 'ADMIN')
    // ---- Admin typeahead "To" search ----
    var searchInput = document.getElementById('hdRecipientSearch');
    var resultsBox = document.getElementById('hdRecipientResults');
    var hiddenId = document.getElementById('hdRecipientId');
    var clearBtn = document.getElementById('hdRecipientClear');
    var searchTimer = null;

    // CHANGED 12 Aug 2026 per Chris: "should display in To Chris Yap" —
    // the picked name now fills the To box itself (readonly, blue text,
    // like a normal answered field) instead of a separate "Selected:
    // ..." line underneath.
    function selectRecipient(id, label) {
        hiddenId.value = id;
        searchInput.value = label;
        searchInput.readOnly = true;
        searchInput.style.color = 'var(--gl-blue)';
        searchInput.style.fontWeight = '600';
        searchInput.style.background = '#f0f9ff';
        clearBtn.style.display = 'block';
        resultsBox.style.display = 'none';
        loadCcOptions(id);
    }

    function clearRecipient() {
        hiddenId.value = '';
        searchInput.value = '';
        searchInput.readOnly = false;
        searchInput.style.color = '';
        searchInput.style.fontWeight = '';
        searchInput.style.background = '';
        clearBtn.style.display = 'none';
        loadCcOptions('');
        searchInput.focus();
    }
    clearBtn.addEventListener('click', clearRecipient);

    searchInput.addEventListener('input', function() {
        var q = searchInput.value.trim();
        clearTimeout(searchTimer);
        if (q.length < 1) { resultsBox.style.display = 'none'; return; }
        searchTimer = setTimeout(function() {
            fetch('{{ route('help-desk.recipient-typeahead') }}?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(list) {
                    resultsBox.innerHTML = '';
                    if (!list.length) { resultsBox.style.display = 'none'; return; }
                    list.forEach(function(item) {
                        var roleLabel = hdRoleLabel(item.role);
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:6px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.name + ' (' + item.code + ' — ' + roleLabel + ')';
                        row.addEventListener('click', function() {
                            selectRecipient(item.id, item.name + ' (' + roleLabel + ')');
                        });
                        resultsBox.appendChild(row);
                    });
                    resultsBox.style.display = 'block';
                });
        }, 250);
    });
    @else
    // ---- Plain dropdown "To" — Cc reloads whenever it changes ----
    document.getElementById('hdRecipientId').addEventListener('change', function() {
        loadCcOptions(this.value);
    });
    @endif

    @if($canUseLanguageFeatures)
    // ---- Fix Wording on the compose form (same pattern as the reply box) ----
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var composeFixBtn = document.getElementById('hdComposeFixWordingBtn');
    if (composeFixBtn) {
        composeFixBtn.addEventListener('click', function() {
            var textarea = document.getElementById('hdComposeBody');
            var text = textarea.value.trim();
            if (!text) { alert(HD_I18N.typeMessageFirst); return; }

            function send(confirmed) {
                fetch('{{ route('help-desk.rephrase') }}', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                    body: JSON.stringify({text: text, confirm: confirmed ? 1 : 0})
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.status === 'confirm_required') {
                        var msg = HD_I18N.confirmChargeTemplate.replace(':fee', Number(data.fee).toFixed(2));
                        if (window.confirm(msg)) { send(true); }
                        return;
                    }
                    if (data.status === 'ok') {
                        var box = document.getElementById('hdComposeRephraseSuggestion');
                        document.getElementById('hdComposeRephraseSuggestionText').textContent = data.text;
                        box.style.display = 'block';
                        document.getElementById('hdComposeRephraseAccept').onclick = function() {
                            textarea.value = data.text;
                            box.style.display = 'none';
                        };
                        document.getElementById('hdComposeRephraseDiscard').onclick = function() {
                            box.style.display = 'none';
                        };
                        return;
                    }
                    alert(data.message || HD_I18N.somethingWrong);
                }).catch(function() {
                    alert(HD_I18N.couldNotReachServer);
                });
            }
            send(false);
        });
    }
    @else
    // ---- NEW 12 Aug 2026 per Chris: automatic spelling/rephrase check,
    // no "Write it myself / Carolyn help to write" toggle. Fires once
    // when the agent leaves the Message box (if Subject+Message have
    // real content), only shows a prompt if Carolyn actually suggests a
    // change, and never re-checks content that hasn't changed since the
    // last check. ----
    (function() {
        var subjectEl = document.getElementById('hdComposeSubject');
        var bodyEl = document.getElementById('hdComposeBody');
        var box = document.getElementById('hdComposeCarolynBox');
        if (!subjectEl || !bodyEl || !box) { return; }
        var status = document.getElementById('hdComposeCarolynStatus');
        var result = document.getElementById('hdComposeCarolynResult');
        var titlePreview = document.getElementById('hdComposeCarolynTitlePreview');
        var bodyPreview = document.getElementById('hdComposeCarolynBodyPreview');
        var lastChecked = '';

        function runCheck() {
            var subjectVal = subjectEl.value.trim();
            var bodyVal = bodyEl.value.trim();
            if (bodyVal.length < 4) { return; }
            var snapshot = subjectVal + '||' + bodyVal;
            if (snapshot === lastChecked) { return; }
            lastChecked = snapshot;

            box.style.display = 'block';
            status.style.display = 'block';
            status.textContent = '✨ Carolyn is checking your spelling and wording...';
            result.style.display = 'none';

            fetch('{{ route('ai-write-assist') }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
                body: JSON.stringify({ title: subjectVal, body: bodyVal, content_type: 'help_desk_message' })
            }).then(function(r) { return r.json(); }).then(function(data) {
                status.style.display = 'none';
                if (data.status !== 'OK' || (data.title === subjectVal && data.body === bodyVal)) {
                    box.style.display = 'none';
                    return;
                }
                titlePreview.textContent = data.title || subjectVal;
                bodyPreview.textContent = data.body;
                result.style.display = 'block';
            }).catch(function() {
                box.style.display = 'none';
            });
        }

        // CHANGED 12 Aug 2026 per Chris: "does not working automatically...
        // still allow to type rubbish" — blur-only meant nothing happened
        // until the agent clicked away from the box, which didn't feel
        // automatic. Now also checks 1.5 seconds after the agent PAUSES
        // typing, in either Subject or Message, without needing to leave
        // the field at all — blur is kept too as an immediate catch-all
        // for when they tab/click away right after typing.
        var debounceTimer = null;
        function scheduleCheck() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(runCheck, 1500);
        }
        subjectEl.addEventListener('input', scheduleCheck);
        bodyEl.addEventListener('input', scheduleCheck);
        subjectEl.addEventListener('blur', function() { clearTimeout(debounceTimer); runCheck(); });
        bodyEl.addEventListener('blur', function() { clearTimeout(debounceTimer); runCheck(); });

        document.getElementById('hdComposeCarolynAccept').addEventListener('click', function() {
            subjectEl.value = titlePreview.textContent;
            bodyEl.value = bodyPreview.textContent;
            box.style.display = 'none';
        });
        document.getElementById('hdComposeCarolynRetype').addEventListener('click', function() {
            box.style.display = 'none';
            bodyEl.focus();
        });
    })();
    @endif
})();
</script>
@endsection
