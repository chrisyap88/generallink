{{-- NEW 3 Aug 2026 — AI Guided Navigation overlay. Separate from the
     Carolyn chat widget (ai-assistant-widget.blade.php), but the two talk
     to each other: Carolyn's chat can hand off into a guided walkthrough
     via window.aiGuidanceStart(goal). See docs/ai-guided-navigation-design.md.

     HARD RULE, no exceptions (Chris's decision, 2 Aug 2026): this overlay
     NEVER clicks a button, submits a form, or changes a field's value for
     the user. It only highlights an element and shows an instruction —
     the human performs every click and every keystroke themselves. --}}
@php
    $__aiNavRoute = optional(request()->route())->getName();
@endphp
<style>
    #aiNavRing {
        position: fixed; z-index: 10000; pointer-events: none;
        border: 3px solid #1B9AE4; border-radius: 8px;
        box-shadow: 0 0 0 4px rgba(27,154,228,.25), 0 0 18px rgba(27,154,228,.55);
        transition: top .25s ease, left .25s ease, width .25s ease, height .25s ease;
        display: none;
    }
    @keyframes aiNavPulse { 0%, 100% { opacity: 1; } 50% { opacity: .55; } }
    #aiNavRing.show { display: block; animation: aiNavPulse 1.4s ease-in-out infinite; }

    #aiNavTooltip {
        position: fixed; z-index: 10001; max-width: 300px;
        background: linear-gradient(120deg,#1565C0,#0D5A8E); color: #fff;
        border-radius: 10px; padding: 10px 14px; font-size: 12px; line-height: 1.45;
        box-shadow: 0 6px 20px rgba(0,0,0,.28); display: none;
    }
    #aiNavTooltip.show { display: block; }
    #aiNavTooltip .aiNavCloseBtn {
        float: right; cursor: pointer; margin-left: 8px; opacity: .8; font-size: 13px;
    }
    #aiNavTooltip .aiNavCloseBtn:hover { opacity: 1; }
</style>

<div id="aiNavRing"></div>
<div id="aiNavTooltip"></div>

<script>
(function() {
    window.__aiNavCurrentRoute = @json($__aiNavRoute);
    window.__aiNavStartUrl = @json(route('ai-guidance.start'));
    window.__aiNavNextUrl = @json(route('ai-guidance.next'));
    window.__aiNavPollTimers = [];
    window.__aiNavEndWalkthroughTooltip = @json(__('partials.end_walkthrough_tooltip'));
})();

function aiNavLog(event, data) {
    var ts = new Date().toISOString().split('T')[1].replace('Z', '');
    console.log('[AiNav ' + ts + '] ' + event, data !== undefined ? data : '');
}

function aiNavHeaders() {
    var headers = {'Content-Type': 'application/json'};
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) { headers['X-CSRF-TOKEN'] = csrfMeta.content; }
    return headers;
}

function aiNavGetSession() {
    try { return JSON.parse(sessionStorage.getItem('aiNavSession') || 'null'); } catch (e) { return null; }
}
function aiNavSetSession(s) { sessionStorage.setItem('aiNavSession', JSON.stringify(s)); }
function aiNavClearSession() { sessionStorage.removeItem('aiNavSession'); }

function aiNavClearWatchers() {
    (window.__aiNavPollTimers || []).forEach(function(t) { clearInterval(t); });
    window.__aiNavPollTimers = [];
}

function aiNavHideUi() {
    document.getElementById('aiNavRing').classList.remove('show');
    document.getElementById('aiNavTooltip').classList.remove('show');
}

function aiNavSpeak(text) {
    // Reuses Carolyn's existing voice pipeline if the chat widget is
    // present on this page (it always is, per the standing rule) —
    // falls back to silently just showing the text if not.
    if (window.aiAssistantSpeak) { window.aiAssistantSpeak(text); }
}

// -------------------------------------------------------
// Positions the highlight ring + tooltip over a given element. Never
// invents a selector — only ever queries the app's own explicit
// data-ai-nav="..." opt-in attribute (see design doc §4.3).
// -------------------------------------------------------
function aiNavPositionOn(el, message) {
    var ring = document.getElementById('aiNavRing');
    var tip = document.getElementById('aiNavTooltip');

    function reposition() {
        var r = el.getBoundingClientRect();
        ring.style.top = (r.top - 6) + 'px';
        ring.style.left = (r.left - 6) + 'px';
        ring.style.width = (r.width + 12) + 'px';
        ring.style.height = (r.height + 12) + 'px';

        var tipTop = r.bottom + 10;
        var tipLeft = Math.min(Math.max(r.left, 8), window.innerWidth - 316);
        if (tipTop + 90 > window.innerHeight) { tipTop = Math.max(8, r.top - 100); }
        tip.style.top = tipTop + 'px';
        tip.style.left = tipLeft + 'px';
    }

    reposition();
    ring.classList.add('show');
    tip.innerHTML = '<span class="aiNavCloseBtn" onclick="aiNavEndWalkthrough()" title="' + window.__aiNavEndWalkthroughTooltip + '">✕</span>' + message;
    tip.classList.add('show');

    var reposHandler = function() { reposition(); };
    window.addEventListener('scroll', reposHandler, true);
    window.addEventListener('resize', reposHandler);
    window.__aiNavReposHandler = reposHandler; // cleaned up on next render
}

function aiNavCleanupPositioning() {
    if (window.__aiNavReposHandler) {
        window.removeEventListener('scroll', window.__aiNavReposHandler, true);
        window.removeEventListener('resize', window.__aiNavReposHandler);
        window.__aiNavReposHandler = null;
    }
}

function aiNavShowMessageOnly(message) {
    // Used for steps with no element (e.g. open_page) or an element that
    // couldn't be found on this page — a fixed banner instead of a ring.
    var tip = document.getElementById('aiNavTooltip');
    aiNavCleanupPositioning();
    document.getElementById('aiNavRing').classList.remove('show');
    tip.style.top = '16px';
    tip.style.left = '50%';
    tip.style.transform = 'translateX(-50%)';
    tip.innerHTML = '<span class="aiNavCloseBtn" onclick="aiNavEndWalkthrough()" title="' + window.__aiNavEndWalkthroughTooltip + '">✕</span>' + message;
    tip.classList.add('show');
}

function aiNavFieldHasValue(el) {
    if (!el) return false;
    if (el.tagName === 'SELECT') return !!el.value;
    return !!(el.value && el.value.trim().length);
}

// -------------------------------------------------------
// The ONLY place that decides a step is "done" and it's time to move on.
// A small fixed set of named conditions the executor already understands
// — never arbitrary code from the manifest or the AI.
// -------------------------------------------------------
function aiNavSetupWatcher(action) {
    aiNavClearWatchers();
    var wait = action.wait;
    if (!wait) {
        // No condition — auto-advance shortly after showing the message
        // (used for the opening open_page step).
        setTimeout(function() { aiNavAdvance('step_completed'); }, 600);
        return;
    }

    if (wait.indexOf('page_arrived:') === 0) {
        var expectedRoute = wait.split(':')[1];
        var s = aiNavGetSession();
        if (s) { s.expectedRoute = expectedRoute; aiNavSetSession(s); }
        return; // resolved on the NEXT page load, see aiNavCheckOnLoad()
    }

    var el = document.querySelector('[data-ai-nav="' + action.element + '"]');

    if (wait === 'field_filled') {
        if (!el) return;
        var check = function() { if (aiNavFieldHasValue(el)) { aiNavAdvance('step_completed'); } };
        el.addEventListener('input', check);
        el.addEventListener('change', check);
        var poll = setInterval(check, 400); // catches programmatic value sets (e.g. postcode auto-fill) that don't fire native events
        window.__aiNavPollTimers.push(poll);
        return;
    }

    if (wait === 'sponsor_selected') {
        var agentIdEl = document.querySelector('[data-ai-nav="upline-agent-id-hidden"]');
        var uplineTypeEl = document.querySelector('[data-ai-nav="upline-type-dropdown"]');
        var check2 = function() {
            var resolved = (agentIdEl && agentIdEl.value) || (uplineTypeEl && uplineTypeEl.value === 'ADMIN_ASSIGN');
            if (resolved) { aiNavAdvance('step_completed'); }
        };
        var poll2 = setInterval(check2, 400);
        window.__aiNavPollTimers.push(poll2);
        return;
    }
}

function aiNavRenderAction(action) {
    aiNavCleanupPositioning();

    if (action.action === 'end_workflow') {
        aiNavShowMessageOnly(action.message);
        aiNavSpeak(action.message);
        aiNavClearSession();
        aiNavClearWatchers();
        setTimeout(aiNavHideUi, 6000);
        return;
    }

    if (action.action === 'open_page') {
        if (action.url && window.__aiNavCurrentRoute !== action.page) {
            aiNavShowMessageOnly(action.message);
            aiNavSpeak(action.message);
            setTimeout(function() { window.location.href = action.url; }, 900);
            return;
        }
        // Already on the right page — just show the message and move on.
        aiNavShowMessageOnly(action.message);
        aiNavSpeak(action.message);
        aiNavSetupWatcher(action);
        return;
    }

    // highlight_element / focus_input
    var el = document.querySelector('[data-ai-nav="' + action.element + '"]');
    if (el && el.offsetParent === null) {
        // Element exists in the DOM but is currently hidden (e.g. the
        // sponsor-search field when "Admin Assign" was chosen instead) —
        // don't draw a zero-size ring around it, just show the message.
        el = null;
    }
    if (el) {
        if (action.action === 'focus_input') { try { el.focus(); } catch (e) {} }
        aiNavPositionOn(el, action.message);
    } else {
        aiNavLog('element not found on this page, showing message only', action.element);
        aiNavShowMessageOnly(action.message);
    }
    aiNavSpeak(action.message);
    aiNavSetupWatcher(action);
}

function aiNavHandleResponse(data) {
    if (data.status === 'OK' && data.action) {
        aiNavRenderAction(data.action);
        return;
    }
    // NO_WORKFLOW / ERROR / ENDED — nothing more to do; show the message
    // briefly if there is one, then get out of the way.
    if (data.message) {
        aiNavShowMessageOnly(data.message);
        setTimeout(aiNavHideUi, 5000);
    } else {
        aiNavHideUi();
    }
    aiNavClearSession();
    aiNavClearWatchers();
}

function aiNavAdvance(event) {
    var s = aiNavGetSession();
    if (!s) return;
    aiNavLog('guide/next', { session_id: s.id, event: event });
    fetch(window.__aiNavNextUrl, {
        method: 'POST', headers: aiNavHeaders(),
        body: JSON.stringify({ session_id: s.id, event: event })
    })
    .then(function(r) { return r.json(); })
    .then(aiNavHandleResponse)
    .catch(function(e) { aiNavLog('guide/next FAILED', e.message); });
}

// Called by Carolyn's chat widget when she decides to start a walkthrough.
window.aiGuidanceStart = function(goal) {
    aiNavLog('guide/start', { goal: goal, currentRoute: window.__aiNavCurrentRoute });
    fetch(window.__aiNavStartUrl, {
        method: 'POST', headers: aiNavHeaders(),
        body: JSON.stringify({ goal: goal, current_route: window.__aiNavCurrentRoute })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.status === 'OK' && data.session_id) {
            aiNavSetSession({ id: data.session_id, expectedRoute: null });
        }
        aiNavHandleResponse(data);
    })
    .catch(function(e) { aiNavLog('guide/start FAILED', e.message); });
};

// User-initiated bail-out — never auto-triggered.
function aiNavEndWalkthrough() {
    var s = aiNavGetSession();
    aiNavClearWatchers();
    aiNavHideUi();
    aiNavClearSession();
    if (s) {
        fetch(window.__aiNavNextUrl, {
            method: 'POST', headers: aiNavHeaders(),
            body: JSON.stringify({ session_id: s.id, event: 'user_navigated_away' })
        }).catch(function() {});
    }
}

// On every page load: resume an in-progress walkthrough that survived a
// real page navigation (e.g. arriving on auth.register after open_page,
// or the form re-displaying with validation errors).
document.addEventListener('DOMContentLoaded', function() {
    var s = aiNavGetSession();
    if (!s) return;
    if (!s.expectedRoute || s.expectedRoute === window.__aiNavCurrentRoute) {
        aiNavAdvance('step_completed');
    } else {
        aiNavAdvance('user_stuck'); // landed somewhere unexpected (e.g. form validation error) — re-show the current step rather than skip ahead
    }
});
</script>
