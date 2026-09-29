{{-- NEW 19 Sep 2026 -- "AI Accountant" floating widget, per Chris:
     "forget carolyn use another ai agents call AI Accountant use a
     corporate account avatar man." Separate bubble/panel from Carolyn
     entirely (own endpoint: /ai-accountant/chat, own brain:
     AiAccountantService) -- deliberately small and single-purpose
     (Chart of Accounts / GL Code only, Phase 1 of the AI Master Data
     Assistant), no voice/memory/ticketing.
     UPDATED 23 Sep 2026 -- per Chris: "move accountant ai beside
     carolyn". Moved from top-LEFT to top-RIGHT, sitting immediately to
     the left of Carolyn's bubble (right:66px vs Carolyn's right:16px,
     same top:56px) instead of the opposite corner of the screen -- the
     two AI bubbles now sit side by side. Only shown inside the
     Financial Master File sidebar section now (see the @if wrapping
     this include in layouts/glade.blade.php) -- it used to render on
     every glade-portal screen for Admin.
     UPDATED 19 Sep 2026 -- avatar swapped from a hand-drawn SVG to
     Chris's own uploaded cartoon image, cropped like a passport photo
     (public/images/chris-accountant-avatar.png -- head+shoulders only,
     the checklist/book he was originally holding are cropped out).
     Also added: father/daughter persona lore (this agent is "Chris")
     and a handoff to Carolyn for anything that isn't Chart of Accounts
     / Accounting -- see AiAccountantService::tools() (handoff_to_carolyn)
     and the handoff handling inside aiAccountantSend() below. --}}
@php
    $__accountantEndpoint = route('ai-accountant.chat');
    $__accountantGreeting = __('ai_accountant.greeting');
    $__accountantStorageKey = 'aiAccountantChatState:agent:' . (auth('agent')->id() ?? 'unknown');
    $__accountantCoaLabels = [
        'type' => __('cbe_accounting.field_account_type'),
        'category' => __('cbe_accounting.field_account_category'),
        'group' => __('cbe_accounting.field_account_group'),
        'code' => __('cbe_accounting.field_account_code'),
        'name' => __('cbe_accounting.field_account_name'),
        'nameZh' => __('cbe_accounting.field_account_name_zh'),
        'description' => __('cbe_accounting.field_description'),
        'matchTitle' => __('coa_chat.match_title'),
        'newTitle' => __('coa_chat.new_title'),
        'confirmSave' => __('coa_chat.confirm_save_button'),
        'viewAccount' => __('coa_chat.view_account_button'),
        'saving' => __('coa_chat.saving_label'),
    ];
    $__accountantCoaTypeLabels = [
        'ASSET' => __('cbe_accounting.type_asset'),
        'LIABILITY' => __('cbe_accounting.type_liability'),
        'EQUITY' => __('cbe_accounting.type_equity'),
        'INCOME' => __('cbe_accounting.type_income'),
        'EXPENSE' => __('cbe_accounting.type_expense'),
    ];
    $__accountantEditUrlTemplate = route('cbe.accounting.chart-of-accounts.edit', ['account' => 'PLACEHOLDER']);
    $__accountantStoreUrl = route('cbe.accounting.chart-of-accounts.store');
@endphp

<div id="aiAccountantBubble" onclick="aiAccountantToggle()" title="{{ __('ai_accountant.bubble_tooltip') }}" style="position:fixed; top:56px; right:66px; width:42px; height:42px; border-radius:50%; background:#37474F; box-shadow:0 4px 14px rgba(0,0,0,.35); display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:9999; border:2px solid #fff; overflow:hidden;">
    <img src="{{ asset('images/chris-accountant-avatar.png') }}" alt="{{ __('ai_accountant.panel_title') }}" style="width:100%; height:100%; object-fit:cover; object-position:center 20%;">
</div>

<div id="aiAccountantPanel" style="display:none; position:fixed; top:104px; right:66px; width:340px; max-width:calc(100vw - 32px); height:460px; max-height:calc(100vh - 130px); background:#fff; border-radius:14px; box-shadow:0 8px 28px rgba(0,0,0,.28); z-index:9999; flex-direction:column; overflow:hidden; font-family:inherit; box-sizing:border-box;">
    <div id="aiAccountantPanelHeader" style="background:linear-gradient(120deg,#FFD54F,#FFA726); color:#263238; padding:10px 14px; display:flex; align-items:center; gap:8px; flex-shrink:0; cursor:move;">
        <div style="width:32px; height:32px; border-radius:50%; overflow:hidden; flex-shrink:0; border:1.5px solid #fff; background:#263238;">
            <img src="{{ asset('images/chris-accountant-avatar.png') }}" alt="{{ __('ai_accountant.panel_title') }}" style="width:100%; height:100%; object-fit:cover; object-position:center 20%;">
        </div>
        <div style="flex:1; min-width:0;">
            <div style="font-size:12px; font-weight:700; color:#263238;">{{ __('ai_accountant.panel_title') }}</div>
            <div style="font-size:9px; color:#5D4037;">{{ __('ai_accountant.panel_subtitle') }}</div>
        </div>
        <span onclick="aiAccountantClear()" title="{{ __('ai_accountant.clear_tooltip') }}" style="cursor:pointer; font-size:13px; line-height:1; flex-shrink:0;">🗑️</span>
        <span onclick="aiAccountantToggle()" title="{{ __('ai_accountant.close_tooltip') }}" style="cursor:pointer; font-size:16px; line-height:1; flex-shrink:0; color:#263238;">✕</span>
    </div>
    <div id="aiAccountantMessages" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px; font-size:11px; background:#F7FAFC; min-height:0;"></div>
    <div style="flex-shrink:0; padding:8px; border-top:1px solid #E2E8F0; display:flex; gap:6px;">
        <input id="aiAccountantInput" type="text" placeholder="{{ __('ai_accountant.input_placeholder') }}" onkeydown="if(event.key==='Enter')aiAccountantSend();" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:20px; padding:7px 12px; font-size:11px; outline:none;">
        <button onclick="aiAccountantSend()" style="background:#37474F; color:#fff; border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:13px; flex-shrink:0;">➤</button>
    </div>
</div>

<script>
(function () {
    window.__aiAccountantEndpoint = @json($__accountantEndpoint);
    window.__aiAccountantGreeting = @json($__accountantGreeting);
    window.__aiAccountantStorageKey = @json($__accountantStorageKey);
    window.__aiAccountantCoaLabels = @json($__accountantCoaLabels);
    window.__aiAccountantCoaTypeLabels = @json($__accountantCoaTypeLabels);
    window.__aiAccountantEditUrlTemplate = @json($__accountantEditUrlTemplate);
    window.__aiAccountantStoreUrl = @json($__accountantStoreUrl);

    var restored = null;
    try { restored = JSON.parse(sessionStorage.getItem(window.__aiAccountantStorageKey) || 'null'); } catch (e) {}
    window.__aiAccountantHistory = (restored && restored.history) || [];
    window.__aiAccountantOpened = !!(restored && restored.opened);
    window.__aiAccountantDisplayLog = (restored && restored.displayLog) || [];
    window.__aiAccountantPanelWasOpen = !!(restored && restored.panelOpen);
})();

function aiAccountantPersistState() {
    try {
        sessionStorage.setItem(window.__aiAccountantStorageKey, JSON.stringify({
            history: window.__aiAccountantHistory,
            opened: window.__aiAccountantOpened,
            displayLog: window.__aiAccountantDisplayLog,
            panelOpen: document.getElementById('aiAccountantPanel').style.display === 'flex'
        }));
    } catch (e) {}
}

function aiAccountantClear() {
    window.__aiAccountantHistory = [];
    window.__aiAccountantDisplayLog = [];
    window.__aiAccountantOpened = true;
    try { sessionStorage.removeItem(window.__aiAccountantStorageKey); } catch (e) {}
    document.getElementById('aiAccountantMessages').innerHTML = '';
    aiAccountantAppend('assistant', window.__aiAccountantGreeting);
    aiAccountantPersistState();
}

function aiAccountantAppend(who, text, skipLog) {
    var box = document.getElementById('aiAccountantMessages');
    var bubble = document.createElement('div');
    var isUser = who === 'user';
    bubble.style.cssText = 'max-width:85%; padding:7px 10px; border-radius:10px; line-height:1.4; white-space:pre-wrap; ' +
        (isUser
            ? 'align-self:flex-end; background:#37474F; color:#fff; border-bottom-right-radius:2px;'
            : 'align-self:flex-start; background:#fff; color:#374151; border:1px solid #E2E8F0; border-bottom-left-radius:2px;');
    bubble.textContent = text;
    box.appendChild(bubble);
    box.scrollTop = box.scrollHeight;
    if (!skipLog) { window.__aiAccountantDisplayLog.push({ who: who, text: text }); }
}

// Same card pattern as originally built for Carolyn -- MATCH shows a
// "View Account" link (nothing to confirm), NEW shows the full
// Type/Category/Group/Code/Name/Name(zh)/Description proposal with a
// real Confirm & Save button. Never persisted into displayLog -- a
// stale Confirm button shouldn't silently reappear after a reload.
function aiAccountantRenderCoaCard(result) {
    var L = window.__aiAccountantCoaLabels;
    if (!L) { return; }
    var isNew = result.type === 'NEW';
    var d = isNew ? result.proposal : result.account;
    var typeLabel = (window.__aiAccountantCoaTypeLabels || {})[d.account_type] || d.account_type;
    var categoryName = d.category_name || d.account_category_name || null;
    var groupName = d.group_name || d.account_group_name || null;

    var box = document.getElementById('aiAccountantMessages');
    var card = document.createElement('div');
    card.style.cssText = 'align-self:stretch; background:#ECEFF1; border:1px solid #CFD8DC; border-radius:10px; padding:8px 10px; font-size:10px;';

    var rows = [
        [L.type, typeLabel],
        [L.category, categoryName || '—'],
        [L.group, groupName || '—'],
        [L.code, d.account_code],
        [L.name, d.account_name],
        [L.nameZh, d.account_name_zh || '—'],
        [L.description, d.description || '—']
    ];

    var html = '<div style="font-weight:700; color:#263238; margin-bottom:6px;">' + (isNew ? L.newTitle : L.matchTitle) + '</div>';
    html += '<div style="display:grid; grid-template-columns:82px 1fr; row-gap:3px;">';
    rows.forEach(function (r) {
        html += '<div style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">' + r[0] + '</div><div style="color:#111827;">' + r[1] + '</div>';
    });
    html += '</div>';
    card.innerHTML = html;

    var actions = document.createElement('div');
    actions.style.cssText = 'display:flex; gap:6px; margin-top:8px;';
    if (isNew) {
        var confirmBtn = document.createElement('button');
        confirmBtn.textContent = L.confirmSave;
        confirmBtn.style.cssText = 'background:#37474F; color:#fff; border:none; border-radius:16px; padding:5px 14px; font-size:9.5px; font-weight:600; cursor:pointer;';
        confirmBtn.onclick = function () { aiAccountantSaveCoaProposal(d, confirmBtn); };
        actions.appendChild(confirmBtn);
    } else if (window.__aiAccountantEditUrlTemplate) {
        var viewLink = document.createElement('a');
        viewLink.textContent = L.viewAccount;
        viewLink.href = window.__aiAccountantEditUrlTemplate.replace('PLACEHOLDER', d.account_id);
        viewLink.style.cssText = 'background:#37474F; color:#fff; text-decoration:none; border-radius:16px; padding:5px 14px; font-size:9.5px; font-weight:600;';
        actions.appendChild(viewLink);
    }
    card.appendChild(actions);
    box.appendChild(card);
    box.scrollTop = box.scrollHeight;
}

// Posts straight to the SAME chart-of-accounts.store route the manual
// Add Account form uses — full server-side validation/uniqueness/scope
// checks run exactly as they would for a manually typed form.
function aiAccountantSaveCoaProposal(p, btn) {
    var L = window.__aiAccountantCoaLabels;
    btn.disabled = true;
    btn.textContent = L ? L.saving : '...';

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = window.__aiAccountantStoreUrl;
    form.style.display = 'none';

    function addField(name, value) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value || '';
        form.appendChild(input);
    }

    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    addField('_token', csrfMeta ? csrfMeta.content : '');
    addField('account_type', p.account_type);
    addField('account_group_id', p.account_group_id);
    addField('account_category_id', p.account_category_id);
    addField('account_code', p.account_code);
    addField('account_name', p.account_name);
    addField('account_name_zh', p.account_name_zh);
    addField('description', p.description);
    addField('is_posting_account', '1');

    document.body.appendChild(form);
    form.submit();
}

function aiAccountantToggle() {
    if (window.__aiAccountantJustDragged) { window.__aiAccountantJustDragged = false; return; }
    var panel = document.getElementById('aiAccountantPanel');
    var showing = panel.style.display === 'flex';
    panel.style.display = showing ? 'none' : 'flex';
    if (!showing) {
        if (window.__aiAccountantRestorePanelPosition) { window.__aiAccountantRestorePanelPosition(); }
    }
    if (!showing && !window.__aiAccountantOpened) {
        window.__aiAccountantOpened = true;
        aiAccountantAppend('assistant', window.__aiAccountantGreeting);
    }
    if (!showing) {
        document.getElementById('aiAccountantInput').focus();
    }
    aiAccountantPersistState();
}

// Entry point for the "AI Master Data Assistant" hub tile: opens the
// panel and pre-fills (never auto-sends) a starter phrase, so the
// person still explicitly reviews/edits and presses Send themselves.
window.aiAccountantOpenWithHint = function (hintText) {
    var panel = document.getElementById('aiAccountantPanel');
    if (!panel) { return; }
    if (panel.style.display !== 'flex') {
        aiAccountantToggle();
    }
    var input = document.getElementById('aiAccountantInput');
    if (input) {
        input.value = hintText;
        input.focus();
    }
};

function aiAccountantRestoreUi() {
    if (window.__aiAccountantDisplayLog.length) {
        window.__aiAccountantDisplayLog.forEach(function (m) { aiAccountantAppend(m.who, m.text, true); });
    }
    if (window.__aiAccountantPanelWasOpen) {
        document.getElementById('aiAccountantPanel').style.display = 'flex';
    }
}
aiAccountantRestoreUi();

// Father/daughter persona hand-off: when AiAccountantService decides a
// request is genuinely outside Chart of Accounts / Accounting, it sends
// back a ready-made greeting for Carolyn. Both widgets are loaded on the
// same admin/GLADE pages, so Carolyn's own global functions
// (aiAssistantToggle / aiAssistantAppend) are called directly here --
// opened WITHOUT triggering her normal first-time greeting, since she is
// about to say her own hand-off greeting instead.
function aiAccountantHandOffToCarolyn(greeting) {
    var carolynPanel = document.getElementById('aiAssistantPanel');
    if (!carolynPanel) { return; }
    if (carolynPanel.style.display !== 'flex') {
        window.__aiAssistantOpened = true;
        carolynPanel.style.display = 'flex';
        if (typeof window.__aiAssistantRestorePanelPosition === 'function') { window.__aiAssistantRestorePanelPosition(); }
    }
    if (typeof window.aiAssistantAppend === 'function') {
        window.aiAssistantAppend('assistant', greeting);
    }
    if (typeof window.aiAssistantPersistState === 'function') { window.aiAssistantPersistState(); }
}

function aiAccountantSend() {
    var input = document.getElementById('aiAccountantInput');
    var message = input.value.trim();
    if (!message) return;
    input.value = '';
    aiAccountantAppend('user', message);

    var thinking = document.createElement('div');
    thinking.id = 'aiAccountantThinking';
    thinking.style.cssText = 'align-self:flex-start; background:#fff; color:#9ca3af; border:1px solid #E2E8F0; padding:7px 10px; border-radius:10px; font-size:11px;';
    thinking.textContent = '...';
    document.getElementById('aiAccountantMessages').appendChild(thinking);
    document.getElementById('aiAccountantMessages').scrollTop = 999999;

    var headers = { 'Content-Type': 'application/json' };
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) { headers['X-CSRF-TOKEN'] = csrfMeta.content; }

    fetch(window.__aiAccountantEndpoint, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({ message: message, history: window.__aiAccountantHistory })
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var t = document.getElementById('aiAccountantThinking');
            if (t) t.remove();

            if (data.status === 'OK') {
                aiAccountantAppend('assistant', data.reply);
                window.__aiAccountantHistory = data.history || window.__aiAccountantHistory;
                if (data.coa_result) {
                    aiAccountantRenderCoaCard(data.coa_result);
                }
                if (data.handoff_to_carolyn && data.handoff_to_carolyn.greeting) {
                    aiAccountantHandOffToCarolyn(data.handoff_to_carolyn.greeting);
                }
            } else {
                aiAccountantAppend('assistant', data.message || 'Something went wrong — please try again.');
            }
            aiAccountantPersistState();
        })
        .catch(function () {
            var t = document.getElementById('aiAccountantThinking');
            if (t) t.remove();
            aiAccountantAppend('assistant', 'Could not reach the AI Accountant — please check your connection and try again.');
            aiAccountantPersistState();
        });
}

// NEW 22 Sep 2026 -- per Chris: the floating AI bubbles must not stay
// fixed in one spot (they can end up sitting on top of a page title).
// Mirrors the drag-and-remember pattern already used for Carolyn's
// panel (8 Aug 2026), applied here to: this panel's header, AND both
// bubbles (collapsed icon, before they're opened) for both AI widgets.
(function () {
    // targetEl = the element that actually moves; handleEl = the element
    // the user presses down on to start the drag (same element for a
    // bubble icon; the header bar only, for a panel).
    function makeDraggable(targetEl, handleEl, posKey, opts) {
        opts = opts || {};
        if (!targetEl || !handleEl) { return; }
        var dragging = false, moved = false, startX, startY, startLeft, startTop;

        function clamp(left, top) {
            var maxLeft = Math.max(4, window.innerWidth - targetEl.offsetWidth - 4);
            var maxTop = Math.max(4, window.innerHeight - targetEl.offsetHeight - 4);
            return { left: Math.min(Math.max(4, left), maxLeft), top: Math.min(Math.max(4, top), maxTop) };
        }

        function applyPosition(left, top) {
            var c = clamp(left, top);
            targetEl.style.left = c.left + 'px';
            targetEl.style.top = c.top + 'px';
            targetEl.style.right = 'auto';
            targetEl.style.bottom = 'auto';
        }

        targetEl['__restorePosition_' + posKey] = function () {
            var saved = null;
            try { saved = JSON.parse(localStorage.getItem(posKey) || 'null'); } catch (e) {}
            if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
                applyPosition(saved.left, saved.top);
            }
        };
        targetEl['__restorePosition_' + posKey]();

        function onPointerDown(e) {
            if (opts.ignoreSelector && e.target.closest(opts.ignoreSelector)) { return; }
            dragging = true;
            moved = false;
            var point = e.touches ? e.touches[0] : e;
            var rect = targetEl.getBoundingClientRect();
            startX = point.clientX;
            startY = point.clientY;
            startLeft = rect.left;
            startTop = rect.top;
            document.addEventListener('mousemove', onPointerMove);
            document.addEventListener('mouseup', onPointerUp);
            document.addEventListener('touchmove', onPointerMove, { passive: false });
            document.addEventListener('touchend', onPointerUp);
        }

        function onPointerMove(e) {
            if (!dragging) { return; }
            var point = e.touches ? e.touches[0] : e;
            var dx = point.clientX - startX;
            var dy = point.clientY - startY;
            if (Math.abs(dx) > 3 || Math.abs(dy) > 3) { moved = true; }
            if (moved) {
                if (e.cancelable) { e.preventDefault(); }
                applyPosition(startLeft + dx, startTop + dy);
            }
        }

        function onPointerUp() {
            if (dragging && moved) {
                try {
                    var rect = targetEl.getBoundingClientRect();
                    localStorage.setItem(posKey, JSON.stringify({ left: rect.left, top: rect.top }));
                } catch (e) {}
                if (opts.onDragEnd) { opts.onDragEnd(); }
            }
            dragging = false;
            document.removeEventListener('mousemove', onPointerMove);
            document.removeEventListener('mouseup', onPointerUp);
            document.removeEventListener('touchmove', onPointerMove);
            document.removeEventListener('touchend', onPointerUp);
        }

        handleEl.addEventListener('mousedown', onPointerDown);
        handleEl.addEventListener('touchstart', onPointerDown, { passive: true });
    }

    var accountantBubble = document.getElementById('aiAccountantBubble');
    makeDraggable(accountantBubble, accountantBubble, 'aiAccountantBubblePos', {
        onDragEnd: function () { window.__aiAccountantJustDragged = true; }
    });

    var accountantPanel = document.getElementById('aiAccountantPanel');
    var accountantHeader = document.getElementById('aiAccountantPanelHeader');
    if (accountantPanel && accountantHeader) {
        makeDraggable(accountantPanel, accountantHeader, 'aiAccountantPanelPos', {
            ignoreSelector: 'span[onclick]'
        });
    }
    window.__aiAccountantRestorePanelPosition = function () {
        if (accountantPanel && accountantPanel.__restorePosition_aiAccountantPanelPos) {
            accountantPanel.__restorePosition_aiAccountantPanelPos();
        }
    };
})();
</script>