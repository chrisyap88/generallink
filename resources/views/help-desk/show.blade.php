@extends('layouts.dashboard')

@section('page-title', __('help-desk.title') . ' — ' . $thread->subject)

@section('content')

{{-- REDESIGNED 21 Jul 2026 — Thread view for one Help Desk message:
     every message (opening + replies from either side), oldest first,
     each with its own attachment if any, plus a reply box at the
     bottom. Either primary party (initiator or recipient) can flag,
     reply, close, or reopen — this is peer-to-peer messaging, not an
     agent-to-Admin ticket, so there's no single "owner" who alone
     controls status. Cc'd viewers get a read-only view.

     ADDED 22 Jul 2026 — per Chris: TL/Introducer logins can translate
     any message into their preferred language, and get a "Fix
     Wording" suggestion before sending a reply. Both are paid Claude
     API calls — the agent always sees a "this will cost RM X, proceed?"
     confirm first; translating something already translated once is
     free (cached server-side). GL/Admin never see either control.

     TRANSLATED 22 Jul 2026 — screen chrome now goes through
     __('help-desk.*') the same way index.blade.php does. Message
     BODIES here are data, never touched by this — only the on-demand
     Translate button (a separate, paid, opt-in action) ever changes
     what's shown for a message's text. --}}

@php
    $hdRoleLabels = [
        'ADMIN' => __('help-desk.role_admin'),
        'GROUP_LEADER' => __('help-desk.role_gl'),
        'TEAM_LEADER' => __('help-desk.role_tl'),
        'INTRODUCER' => __('help-desk.role_introducer'),
    ];
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
    $hdPriorityColors = ['HIGH' => '#e53935', 'MEDIUM' => '#F6AD55', 'LOW' => '#9ca3af'];
    $isCarolynTicket = $thread->created_by_type === 'CAROLYN_AI';
    $hdCreatorLine = $isCarolynTicket
        ? ('🤖 Created by Carolyn AI Agent' . ($initiator->full_name ? " (on behalf of {$initiator->full_name})" : ($thread->guest_name ? " (Guest: {$thread->guest_name})" : ' (Guest, not logged in)')))
        : (__('help-desk.from_label') . ': ' . ($initiator->full_name ?? '—') . ' — ' . __('help-desk.to_label') . ': ' . ($recipient->full_name ?? '—'));
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    {{-- RESTORED 12 Aug 2026 per Chris: "you left the user hanging there
         and no way to go back" — the 8 Aug removal (below) went too far:
         the bottom Prev/Next only pages through THIS thread's own
         messages, so a single-message thread (e.g. any Carolyn-logged
         ticket) had both Prev and Next permanently greyed out, with
         nothing else on screen to leave the thread. Brought back as its
         own clearly different control — labelled "Help Desk", never
         "Prev" — so it can never be confused with the message pager. --}}
    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <a href="{{ route('help-desk.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600; display:flex; align-items:center; gap:4px;">&larr; {{ __('help-desk.title') }}</a>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="padding:2px 10px; border-radius:20px; font-size:9.5px; font-weight:600;
                background:{{ $thread->status === 'OPEN' ? '#fff8e1' : '#f3f4f6' }};
                color:{{ $thread->status === 'OPEN' ? '#92400e' : '#6b7280' }};">{{ $thread->status === 'OPEN' ? __('help-desk.status_open') : __('help-desk.status_closed') }}</span>
            @if($canReply)
            @php
                $isInitiator = $thread->initiator_agent_id === $viewerAgentId;
                $isRecipientViewer = $thread->recipient_agent_id === $viewerAgentId;
                $flagged = $isInitiator ? $thread->flagged_by_initiator : $thread->flagged_by_recipient;
            @endphp
            {{-- Flagging is a personal "To me"/"From me" marker — not
                 meaningful for an Admin viewing a Carolyn-logged ticket
                 they aren't personally the initiator/recipient of. --}}
            @if($isInitiator || $isRecipientViewer)
            <form method="POST" action="{{ route('help-desk.flag', $thread->thread_id) }}" style="display:inline;">
                @csrf
                <button type="submit" title="Flag" style="background:none; border:none; cursor:pointer; font-size:15px; color:{{ $flagged ? '#F6AD55' : '#d1d5db' }};">&#9733;</button>
            </form>
            @endif
            @if($thread->status === 'OPEN')
            <form method="POST" action="{{ route('help-desk.close', $thread->thread_id) }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:#f3f4f6; color:#6b7280; border:none; border-radius:20px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('help-desk.close') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('help-desk.reopen', $thread->thread_id) }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:#e3f2fd; color:#1565C0; border:none; border-radius:20px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('help-desk.reopen') }}</button>
            </form>
            @endif
            @endif
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="flex-shrink:0; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">
            {{ $thread->subject }}
            @if($thread->priority && isset($hdPriorityColors[$thread->priority]))
            <span style="font-size:8.5px; font-weight:700; color:#fff; background:{{ $hdPriorityColors[$thread->priority] }}; border-radius:10px; padding:2px 8px; margin-left:6px; vertical-align:middle;">{{ ucfirst(strtolower($thread->priority)) }}</span>
            @endif
        </div>
        <div style="font-size:9.5px; color:#9ca3af;">
            {{ $hdCreatorLine }}{{ $thread->thread_code ? ' — Ref: ' . $thread->thread_code : '' }}{{ $ccNames->count() ? ' — ' . __('help-desk.cc_label') . ': ' . $ccNames->implode(', ') : '' }} — {{ $hdCategoryLabels[$thread->category] ?? str_replace('_', ' ', $thread->category) }} — {{ __('help-desk.started') }} {{ \Carbon\Carbon::parse($thread->created_at)->format('d M Y, h:i A') }}
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        {{-- CHANGED 8 Aug 2026 per Chris: strict no-scroll rule — was
             overflow-y:auto (an internally-scrolling panel). Now shows a
             fixed page of messages (see hdMessagesPerPage in the
             controller) with real Prev/Next paging below, same pattern as
             every list screen in the app. --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            @foreach($messages as $m)
            @php
                $isMe = $m->sender_agent_id === $viewerAgentId;
                $isCarolynMessage = $m->sender_agent_id === null;
                $cachedT = $cachedTranslations->get($m->message_id);
                $hdSenderLabel = $isCarolynMessage
                    ? '🤖 Carolyn AI Agent'
                    : ($m->full_name ?? 'Unknown') . ' (' . ($isMe ? __('help-desk.you') : ($hdRoleLabels[$m->role] ?? __('help-desk.role_introducer'))) . ')';
            @endphp
            <div style="margin-bottom:10px; display:flex; {{ $isMe ? 'justify-content:flex-end;' : 'justify-content:flex-start;' }}">
                <div style="max-width:70%;">
                    <div style="background:{{ $isMe ? '#1565C0' : '#f3f4f6' }}; color:{{ $isMe ? '#fff' : '#374151' }}; border-radius:10px; padding:8px 10px;">
                        <div style="font-size:8.5px; opacity:.8; margin-bottom:3px;">{{ $hdSenderLabel }} — {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y, h:i A') }}</div>
                        <div class="hdMsgOriginal" data-message-id="{{ $m->message_id }}" style="font-size:10.5px; white-space:pre-wrap; line-height:1.4;">{{ $m->body }}</div>
                        <div class="hdMsgTranslated" data-message-id="{{ $m->message_id }}" style="font-size:10.5px; white-space:pre-wrap; line-height:1.4; display:{{ $cachedT ? 'block' : 'none' }};">{{ $cachedT->translated_body ?? '' }}</div>
                        @if($m->attachment_file_path)
                        <div style="margin-top:5px;">
                            <a href="{{ route('help-desk.attachment', $m->message_id) }}" target="_blank" style="font-size:9px; font-weight:600; color:{{ $isMe ? '#e3f2fd' : '#1565C0' }}; text-decoration:underline;">&#128206; {{ $m->attachment_file_name }}</a>
                        </div>
                        @endif
                    </div>
                    @if($canUseLanguageFeatures && !$isMe)
                    <div style="margin-top:3px; {{ $isMe ? 'text-align:right;' : 'text-align:left;' }}">
                        <button type="button" class="hdTranslateBtn" data-message-id="{{ $m->message_id }}" data-fee="{{ $translationFee }}" style="background:none; border:none; cursor:pointer; font-size:9px; color:#1565C0; font-weight:600; padding:0;">
                            &#127760; <span class="hdTranslateBtnLabel">{{ $cachedT ? __('help-desk.view_translated') : __('help-desk.translate') }}</span>
                        </button>
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- NEW 8 Aug 2026 per Chris: strict Prev/Next rule — pages
             through this thread's own messages (oldest page = furthest
             back in the conversation). --}}
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:6px 0; border-top:1px solid #f3f4f6;">
            @if($hdPage <= 1)
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">&larr; {{ __('help-desk.prev') }}</span>
            @else
                <a href="{{ route('help-desk.show', ['threadId' => $thread->thread_id, 'page' => $hdPage - 1]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">&larr; {{ __('help-desk.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('help-desk.page_of', ['current' => $hdPage, 'last' => $hdLastPage, 'total' => $hdTotalMessages]) }}</span>
            @if($hdPage >= $hdLastPage)
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('help-desk.next') }} &rarr;</span>
            @else
                <a href="{{ route('help-desk.show', ['threadId' => $thread->thread_id, 'page' => $hdPage + 1]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('help-desk.next') }} &rarr;</a>
            @endif
        </div>

        @if(!$canReply)
        <div style="border-top:1px solid #f3f4f6; padding-top:8px; margin-top:8px; text-align:center; font-size:9.5px; color:#9ca3af;">{{ __('help-desk.cc_viewer_note') }}</div>
        @elseif($thread->status !== 'CLOSED')
        <form method="POST" action="{{ route('help-desk.reply', $thread->thread_id) }}" enctype="multipart/form-data" style="border-top:1px solid #f3f4f6; padding-top:8px; margin-top:8px;">
            @csrf
            <textarea name="body" id="hdReplyBody" rows="2" maxlength="3000" required placeholder="{{ __('help-desk.reply_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:4px; box-sizing:border-box; resize:none;"></textarea>
            @if($canUseLanguageFeatures)
            <div id="hdRephraseSuggestion" style="display:none; background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; padding:6px 8px; font-size:10.5px; color:#0c4a6e; margin-bottom:6px;">
                <div style="font-weight:600; margin-bottom:3px;">{{ __('help-desk.suggested_wording') }}</div>
                <div id="hdRephraseSuggestionText" style="white-space:pre-wrap; margin-bottom:5px;"></div>
                <button type="button" id="hdRephraseAccept" style="background:#1565C0; color:#fff; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer; margin-right:6px;">{{ __('help-desk.use_this') }}</button>
                <button type="button" id="hdRephraseDiscard" style="background:#f3f4f6; color:#6b7280; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; cursor:pointer;">{{ __('help-desk.discard') }}</button>
            </div>
            @endif
            {{-- CHANGED 12 Aug 2026 per Chris: "i suggest you dont create
                 write myself or ask carolyn to help...you just build in
                 automatically spelling check and rephrase." No mode
                 toggle — checks automatically when the agent leaves the
                 reply box, only prompts if she found something worth
                 changing. Free for every role except TL/Introducer, who
                 keep their existing paid Fix Wording above instead. --}}
            @unless($canUseLanguageFeatures)
            <div id="hdReplyCarolynBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:6px 8px; font-size:10.5px; margin-bottom:6px; max-height:110px; overflow-y:auto; box-sizing:border-box;">
                <div id="hdReplyCarolynStatus" style="color:#7c3aed; font-weight:600;">✨ Carolyn is checking your spelling and wording...</div>
                <div id="hdReplyCarolynResult" style="display:none;">
                    <div id="hdReplyCarolynPreview" style="white-space:pre-wrap; color:#111827; margin-bottom:6px;"></div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" id="hdReplyCarolynAccept" style="background:#16a34a; color:#fff; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">✅ Use This</button>
                        <button type="button" id="hdReplyCarolynRetype" style="background:#f3f4f6; color:#6b7280; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; cursor:pointer;">✖ No, I'll Retype</button>
                    </div>
                </div>
            </div>
            <div style="font-size:9px; color:#9ca3af; margin-bottom:4px;">✨ Carolyn checks your spelling automatically when you finish typing.</div>
            @endunless
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="font-size:9.5px; max-width:200px;">
                <div style="display:flex; gap:8px; align-items:center;">
                    @if($canUseLanguageFeatures)
                    <button type="button" id="hdFixWordingBtn" data-fee="{{ $rephraseFee }}" style="background:#fff; color:#1565C0; border:1px solid #1565C0; border-radius:6px; padding:5px 12px; font-size:10px; font-weight:600; cursor:pointer;">&#10024; {{ __('help-desk.fix_wording') }}</button>
                    @endif
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('help-desk.send_reply') }}</button>
                </div>
            </div>
        </form>
        @else
        <div style="border-top:1px solid #f3f4f6; padding-top:8px; margin-top:8px; text-align:center; font-size:9.5px; color:#9ca3af;">{{ __('help-desk.thread_closed_note') }}</div>
        @endif
    </div>

</div>

@if($canUseLanguageFeatures)
<script>
var HD_I18N = {
    typeReplyFirst: @json(__('help-desk.js_type_reply_first')),
    somethingWrong: @json(__('help-desk.js_something_wrong')),
    couldNotReachServer: @json(__('help-desk.js_could_not_reach_server')),
    confirmChargeTemplate: @json(__('help-desk.js_confirm_charge')),
    viewOriginal: @json(__('help-desk.view_original')),
    viewTranslated: @json(__('help-desk.view_translated'))
};

(function() {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    function chargedPost(url, payload, onDone) {
        function send(confirmed) {
            fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json'},
                body: JSON.stringify(Object.assign({}, payload, {confirm: confirmed ? 1 : 0}))
            }).then(function(r) { return r.json(); }).then(function(data) {
                if (data.status === 'confirm_required') {
                    var msg = HD_I18N.confirmChargeTemplate.replace(':fee', Number(data.fee).toFixed(2));
                    if (window.confirm(msg)) { send(true); }
                    return;
                }
                if (data.status === 'ok') { onDone(data); return; }
                alert(data.message || HD_I18N.somethingWrong);
            }).catch(function() {
                alert(HD_I18N.couldNotReachServer);
            });
        }
        send(false);
    }

    // ---- Per-message Translate ----
    document.querySelectorAll('.hdTranslateBtn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var messageId = btn.dataset.messageId;
            var originalDiv = document.querySelector('.hdMsgOriginal[data-message-id="' + messageId + '"]');
            var translatedDiv = document.querySelector('.hdMsgTranslated[data-message-id="' + messageId + '"]');
            var label = btn.querySelector('.hdTranslateBtnLabel');

            // Already translated and currently showing the original — just toggle back.
            if (translatedDiv.style.display === 'block') {
                translatedDiv.style.display = 'none';
                originalDiv.style.display = 'block';
                label.textContent = HD_I18N.viewTranslated;
                return;
            }
            if (translatedDiv.textContent.trim() !== '') {
                originalDiv.style.display = 'none';
                translatedDiv.style.display = 'block';
                label.textContent = HD_I18N.viewOriginal;
                return;
            }

            chargedPost('/help-desk/message/' + messageId + '/translate', {}, function(data) {
                translatedDiv.textContent = data.text;
                originalDiv.style.display = 'none';
                translatedDiv.style.display = 'block';
                label.textContent = HD_I18N.viewOriginal;
            });
        });
    });

    // ---- Fix Wording ----
    var fixBtn = document.getElementById('hdFixWordingBtn');
    if (fixBtn) {
        fixBtn.addEventListener('click', function() {
            var textarea = document.getElementById('hdReplyBody');
            var text = textarea.value.trim();
            if (!text) { alert(HD_I18N.typeReplyFirst); return; }

            chargedPost('/help-desk/rephrase', {text: text}, function(data) {
                var box = document.getElementById('hdRephraseSuggestion');
                document.getElementById('hdRephraseSuggestionText').textContent = data.text;
                box.style.display = 'block';

                document.getElementById('hdRephraseAccept').onclick = function() {
                    textarea.value = data.text;
                    box.style.display = 'none';
                };
                document.getElementById('hdRephraseDiscard').onclick = function() {
                    box.style.display = 'none';
                };
            });
        });
    }
})();
</script>
@endif

@unless($canUseLanguageFeatures)
<script>
// NEW 12 Aug 2026 per Chris: automatic spelling/rephrase check on the
// reply box for every role except TL/Introducer (who use the paid Fix
// Wording above instead). Fires once on blur, only prompts if Carolyn
// actually suggests a change, never re-checks unchanged text.
(function() {
    var bodyEl = document.getElementById('hdReplyBody');
    var box = document.getElementById('hdReplyCarolynBox');
    if (!bodyEl || !box) { return; }
    var status = document.getElementById('hdReplyCarolynStatus');
    var result = document.getElementById('hdReplyCarolynResult');
    var preview = document.getElementById('hdReplyCarolynPreview');
    var lastChecked = '';

    function runCheck() {
        var bodyVal = bodyEl.value.trim();
        if (bodyVal.length < 4 || bodyVal === lastChecked) { return; }
        lastChecked = bodyVal;

        box.style.display = 'block';
        status.style.display = 'block';
        status.textContent = '✨ Carolyn is checking your spelling and wording...';
        result.style.display = 'none';

        fetch('{{ route('ai-write-assist') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json'},
            body: JSON.stringify({ body: bodyVal, content_type: 'help_desk_message' })
        }).then(function(r) { return r.json(); }).then(function(data) {
            status.style.display = 'none';
            if (data.status !== 'OK' || data.body === bodyVal) {
                box.style.display = 'none';
                return;
            }
            preview.textContent = data.body;
            result.style.display = 'block';
        }).catch(function() {
            box.style.display = 'none';
        });
    }

    // CHANGED 12 Aug 2026 per Chris: blur-only didn't feel automatic —
    // now also checks 1.5 seconds after the agent pauses typing.
    var debounceTimer = null;
    bodyEl.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runCheck, 1500);
    });
    bodyEl.addEventListener('blur', function() { clearTimeout(debounceTimer); runCheck(); });

    document.getElementById('hdReplyCarolynAccept').addEventListener('click', function() {
        bodyEl.value = preview.textContent;
        box.style.display = 'none';
    });
    document.getElementById('hdReplyCarolynRetype').addEventListener('click', function() {
        box.style.display = 'none';
        bodyEl.focus();
    });
})();
</script>
@endunless
@endsection
