{{-- NEW 3 Aug 2026 — GeneralLink AI Assistant floating widget. Shared
     partial used on BOTH the login page (guestMode=true) and every
     authenticated screen via layouts/dashboard.blade.php
     (guestMode=false). Plain HTML/CSS/JS, no build step, matching how
     every other script is already included in this app. --}}
@php
    $__aiEndpoint = $guestMode ? route('ai-assistant.chat-guest') : route('ai-assistant.chat');
    $__aiSlogan = __('partials.ai_slogan');
    $__aiIntro = __('partials.ai_intro');
    $__aiGreeting = $guestMode
        ? __('partials.ai_guest_greeting', ['intro' => $__aiIntro, 'slogan' => $__aiSlogan])
        : __('partials.ai_auth_greeting', ['intro' => $__aiIntro, 'slogan' => $__aiSlogan]);
    // NEW 3 Aug 2026 — conversation now PERSISTS across page navigations
    // (Chris: "why the chat begins with new chat when I navigate to the
    // next screen? it should continue until I click end or exit"). Keyed
    // by mode + agent so switching between logged-in agents in the same
    // browser tab (e.g. while testing different roles) never leaks one
    // agent's conversation into another's. Cleared automatically when the
    // browser tab closes (sessionStorage), or explicitly via the new "End
    // Chat" control in the panel.
    // NEW 9 Aug 2026 — per Chris: does Carolyn know how to help during
    // NEW VENDOR REGISTRATION specifically, not just the generic login
    // page? Previously every guest page was hardcoded to page_context =
    // "Login Page" server-side regardless of which guest screen actually
    // embedded the widget, so Carolyn could never tell a vendor filling
    // in the registration form apart from someone on the plain sign-in
    // page. Each guest blade file now passes its own $guestPageLabel
    // (falls back to "Login Page" if the including page doesn't set one)
    // — see AiAssistantService::buildSystemPrompt() for what Carolyn does
    // with it.
    $__aiGuestPageLabel = $guestMode ? ($guestPageLabel ?? __('partials.ai_default_guest_page_label')) : null;
    $__aiAgent = $guestMode ? null : auth('agent')->user();
    $__aiStorageKey = $guestMode ? 'aiAssistantChatState:guest' : 'aiAssistantChatState:agent:' . ($__aiAgent->agent_id ?? 'unknown');
    // NEW 6 Aug 2026 — per Chris: the browser's own backup voice (used only
    // when ElevenLabs/OpenAI/Google can't be reached) was ALWAYS picking an
    // English-named voice, no matter what language Carolyn was actually
    // replying in — same root problem as the server-side TTS fix, just on
    // the client fallback path. See aiAssistantPickVoiceFrom() below.
    $__aiLanguageService = app(\App\Services\LanguageService::class);
    $__aiLanguageCode = $__aiAgent ? $__aiLanguageService->iso639($__aiLanguageService->effectiveLanguage($__aiAgent)) : 'en';
@endphp
<style>
    @keyframes aiCarolynFloat { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    #aiAssistantBubble { animation: aiCarolynFloat 2.6s ease-in-out infinite; }
    #aiAssistantBubble:hover { transform: scale(1.08); }
</style>

{{-- MOVED 28 Aug 2026 — per Chris: "put carolyn in the space that not
     cover the display records" — the bottom-right corner is exactly
     where every dense KPI/report screen (CBE KPI Box 6/7, and others)
     puts its own last row of real numbers, so the bubble was sitting on
     top of live data on several screens. Moved to just below the top
     bar instead — every authenticated screen keeps that strip clear for
     the topbar icons, so nothing real is ever under it there; on the
     guest login pages (no topbar) it just floats in the same open
     corner it always had, unchanged. --}}
<div id="aiAssistantBubble" onclick="aiAssistantToggle()" title="{{ __('partials.ai_bubble_tooltip') }}" style="position:fixed; top:56px; right:16px; width:42px; height:42px; border-radius:50%; background:linear-gradient(160deg,#E3F2FD 0%,#90CAF9 100%); box-shadow:0 4px 14px rgba(21,101,192,.35); display:flex; align-items:center; justify-content:center; cursor:pointer; z-index:9999; transition:transform .15s ease; border:2px solid #fff;">
    {{-- Avatar image clipped to its own circle (moved off the outer div so the badge below, positioned outside the circle, isn't cut off by overflow:hidden). --}}
    <div style="width:100%; height:100%; border-radius:50%; overflow:hidden;">
        <img src="{{ asset('images/carolyn-avatar.png') }}" alt="Carolyn" style="width:100%; height:100%; object-fit:cover; object-position:center 15%;">
    </div>
    {{-- NEW 5 Aug 2026 — outstanding-items badge. Hidden until aiAssistantRefreshAlerts() finds a nonzero count (agents only, never shown on the guest login page). SHRUNK 8 Aug 2026 to match the smaller bubble. --}}
    @if(!$guestMode)
    <span id="aiAssistantBadge" style="display:none; position:absolute; top:-2px; right:-2px; min-width:14px; height:14px; padding:0 3px; border-radius:7px; background:#DC2626; color:#fff; font-size:8px; font-weight:700; line-height:14px; text-align:center; box-shadow:0 0 0 2px #fff;"></span>
    @endif
</div>

{{-- MOVED 28 Aug 2026 — panel default position follows the bubble's move
     (see above): opens just below it, top-right, instead of bottom-right.
     Only affects agents who haven't dragged the panel yet — a saved drag
     position (localStorage) still wins via __aiAssistantRestorePanelPosition. --}}
<div id="aiAssistantPanel" style="display:none; position:fixed; top:104px; right:16px; width:340px; max-width:calc(100vw - 32px); height:460px; max-height:calc(100vh - 130px); background:#fff; border-radius:14px; box-shadow:0 8px 28px rgba(0,0,0,.28); z-index:9999; flex-direction:column; overflow:hidden; font-family:inherit; box-sizing:border-box;">
    {{-- NEW 8 Aug 2026 — draggable, per Chris: the panel "cover the
         screen field most of the time." Drag anywhere by this header bar
         (cursor:move); position is remembered across page loads. --}}
    <div id="aiAssistantPanelHeader" title="{{ __('partials.ai_drag_tooltip') }}" style="cursor:move; background:linear-gradient(120deg,#1565C0,#0D5A8E); color:#fff; padding:10px 14px; display:flex; align-items:center; gap:8px; flex-shrink:0; user-select:none;">
        <div style="width:32px; height:32px; border-radius:50%; overflow:hidden; flex-shrink:0; border:1.5px solid #fff; pointer-events:none;">
            <img src="{{ asset('images/carolyn-avatar.png') }}" alt="Carolyn" style="width:100%; height:100%; object-fit:cover; object-position:center 15%;">
        </div>
        <div style="flex:1; min-width:0; pointer-events:none;">
            <div style="font-size:12px; font-weight:700;">⠿ {{ __('partials.ai_panel_title') }}</div>
            <div style="font-size:9px; color:#BBDEFB;">{{ __('partials.ai_panel_subtitle') }}</div>
        </div>
        <span id="aiAssistantVoiceToggle" onclick="aiAssistantToggleVoice()" title="{{ __('partials.ai_voice_off_tooltip') }}" style="cursor:pointer; font-size:15px; line-height:1; flex-shrink:0;">🔊</span>
        <span onclick="aiAssistantEndChat()" title="{{ __('partials.ai_end_chat_tooltip') }}" style="cursor:pointer; font-size:13px; line-height:1; flex-shrink:0;">🗑️</span>
        <span onclick="aiAssistantToggle()" title="{{ __('partials.ai_minimize_tooltip') }}" style="cursor:pointer; font-size:16px; line-height:1; flex-shrink:0;">✕</span>
    </div>
    <div id="aiAssistantMessages" style="flex:1; overflow-y:auto; padding:10px; display:flex; flex-direction:column; gap:8px; font-size:11px; background:#F7FAFC; min-height:0;"></div>
    <div style="flex-shrink:0; padding:8px; border-top:1px solid #E2E8F0; display:flex; gap:6px;">
        <input id="aiAssistantInput" type="text" placeholder="{{ __('partials.ai_input_placeholder') }}" onkeydown="if(event.key==='Enter')aiAssistantSend();" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:20px; padding:7px 12px; font-size:11px; outline:none;">
        <button id="aiAssistantMicBtn" onclick="aiAssistantToggleMic()" title="{{ __('partials.ai_mic_tooltip') }}" style="background:#f3f4f6; color:#4A5568; border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:14px; flex-shrink:0;">🎤</button>
        <button onclick="aiAssistantSend()" style="background:#1565C0; color:#fff; border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; font-size:13px; flex-shrink:0;">➤</button>
    </div>
</div>

<script>
window.__aiAssistantI18n = {
    voiceOffTooltip: @json(__('partials.ai_voice_off_tooltip')),
    voiceOnTooltip: @json(__('partials.ai_voice_on_tooltip')),
    micListeningTooltip: @json(__('partials.ai_mic_listening_tooltip')),
    listeningPlaceholder: @json(__('partials.ai_listening_placeholder')),
    speakingTooltip: @json(__('partials.ai_speaking_tooltip')),
    speakingPlaceholder: @json(__('partials.ai_speaking_placeholder')),
    micIdleTooltip: @json(__('partials.ai_mic_idle_tooltip_js')),
    inputPlaceholder: @json(__('partials.ai_input_placeholder')),
    micWatchdogAlert: @json(__('partials.ai_mic_watchdog_alert')),
    micBlockedAlert: @json(__('partials.ai_mic_blocked_alert')),
    micUnclearAlertTemplate: @json(__('partials.ai_mic_unclear_alert_template')),
    micStartFailedAlertTemplate: @json(__('partials.ai_mic_start_failed_alert_template')),
    micUnsupportedAlert: @json(__('partials.ai_mic_unsupported_alert')),
    genericErrorMessage: @json(__('partials.ai_generic_error_message')),
    connectionErrorMessage: @json(__('partials.ai_connection_error_message')),
    defaultGuestPageLabel: @json(__('partials.ai_default_guest_page_label')),
};
(function() {
    window.__aiAssistantEndpoint = @json($__aiEndpoint);
    window.__aiAssistantSpeakEndpoint = @json(route('ai-assistant.speak'));
    window.__aiAssistantGreeting = @json($__aiGreeting);
    window.__aiAssistantStorageKey = @json($__aiStorageKey);
    window.__aiAssistantVoiceOn = true; // speaker mode ON by default, per Chris's request
    window.__aiAssistantAudio = null;   // currently-playing ElevenLabs <audio>, if any

    // NEW 5 Aug 2026 — proactive alerts (badge count + once-a-day popup).
    // Guests (login page) never call this endpoint at all — nothing to
    // alert a not-yet-logged-in visitor about.
    window.__aiAssistantGuestMode = @json($guestMode);
    window.__aiAssistantGuestPageLabel = @json($__aiGuestPageLabel);
    window.__aiAssistantAlertsEndpoint = @json($guestMode ? null : route('ai-assistant.alerts'));
    window.__aiAssistantLanguageCode = @json($__aiLanguageCode); // 'en' | 'zh' | 'ms' — see aiAssistantPickVoiceFrom()

    // RCA instrumentation (3 Aug 2026) — see chat writeup for what each targets.
    window.__aiAssistantTurnId = 0;          // monotonic turn counter — detects/discards stale, out-of-order responses
    window.__aiAssistantPinnedVoiceId = null; // first voice_id ElevenLabs actually used THIS conversation — reused for every later call so a global setting change elsewhere can't swap it mid-conversation
    window.__aiAssistantPinnedSpeed = null;
    window.__aiAssistantState = 'idle';      // explicit state machine: idle | listening | processing | speaking

    // NEW 3 Aug 2026 — restore the conversation from this browser tab's
    // sessionStorage, if any, instead of always starting fresh. This is
    // what makes the chat CONTINUE across page navigations (menu clicks,
    // Prev/Next, Guided Navigation moving between screens) instead of
    // resetting — it only clears when the tab itself closes, or the user
    // explicitly clicks "End Chat" (🗑️ in the panel header).
    var __restored = null;
    try { __restored = JSON.parse(sessionStorage.getItem(window.__aiAssistantStorageKey) || 'null'); } catch (e) {}

    window.__aiAssistantHistory = (__restored && __restored.history) || [];
    window.__aiAssistantOpened = !!(__restored && __restored.opened);
    window.__aiAssistantDisplayLog = (__restored && __restored.displayLog) || []; // [{who, text}, ...] — purely for re-rendering visible bubbles on restore
    window.__aiAssistantPanelWasOpen = !!(__restored && __restored.panelOpen);
})();

// Persists the whole restorable state after anything changes (a new turn,
// opening/closing the panel). Deliberately excludes the ElevenLabs voice
// pin and mic state — those are meant to be per-page-load, not persisted.
function aiAssistantPersistState() {
    try {
        sessionStorage.setItem(window.__aiAssistantStorageKey, JSON.stringify({
            history: window.__aiAssistantHistory,
            opened: window.__aiAssistantOpened,
            displayLog: window.__aiAssistantDisplayLog,
            panelOpen: document.getElementById('aiAssistantPanel').style.display === 'flex'
        }));
    } catch (e) { aiAssistantLog('persist: sessionStorage write failed', e.message); }
}

// Explicit, user-initiated reset — the ONLY thing that clears an
// in-progress conversation before the tab itself closes.
function aiAssistantEndChat() {
    aiAssistantStopAllAudio();
    clearTimeout(aiAssistantStartWatchdog);
    window.__aiAssistantVoiceModeOn = false;
    if (aiAssistantListening && aiAssistantRecognition) { aiAssistantRecognition.stop(); }
    aiAssistantSetMicUi('idle');
    aiAssistantSetState('idle');

    window.__aiAssistantHistory = [];
    window.__aiAssistantDisplayLog = [];
    window.__aiAssistantOpened = false;
    try { sessionStorage.removeItem(window.__aiAssistantStorageKey); } catch (e) {}

    document.getElementById('aiAssistantMessages').innerHTML = '';
    aiAssistantLog('chat: ended by user — starting fresh');
    aiAssistantAppend('assistant', window.__aiAssistantGreeting);
    aiAssistantSpeak(window.__aiAssistantGreeting);
    window.__aiAssistantOpened = true;
    aiAssistantPersistState();
}

// ---- RCA logging helper — every lifecycle event funnels through this,
// prefixed consistently so it's easy to filter in DevTools console. ----
function aiAssistantLog(event, data) {
    var ts = new Date().toISOString().split('T')[1].replace('Z', '');
    console.log('[Carolyn ' + ts + '] ' + event, data !== undefined ? data : '');
}

function aiAssistantSetState(state, detail) {
    if (window.__aiAssistantState !== state) {
        aiAssistantLog('STATE ' + window.__aiAssistantState + ' -> ' + state, detail || '');
        window.__aiAssistantState = state;
    }
}

// Carolyn's BACKUP voice — used only when ElevenLabs can't be reached
// (e.g. quota exhausted). FIX 3 Aug 2026 (voice-consistency RCA): this used
// to be re-picked from scratch on every single fallback call via name-hint
// matching, with two real bugs: (1) Chrome loads its voice list
// ASYNCHRONOUSLY — if speechSynthesis.getVoices() was called before that
// finished (very likely on the very first thing Carolyn ever says, e.g.
// the auto-greeting), it returned an empty list, so no voice was picked at
// all and the browser silently used its own random OS default for just
// that one line — a real, confirmed source of "a different voice
// sometimes." (2) Nothing was ever actually PERSISTED (the localStorage
// key was read but never written anywhere), so the pick could vary
// between browsers/devices with no way to make it consistent.
// Fix: resolve the voice ONCE, properly waiting for the async voice list
// if needed, then PIN that exact voice by name in localStorage — every
// later fallback call, on any page, reuses the same pinned voice instead
// of re-guessing. This does not change which voice is used when
// ElevenLabs succeeds (that's still Jessica, via the existing pinned
// voice_id) — it only makes the BACKUP voice stop randomly varying.
// FIX 6 Aug 2026 — per Chris: this picker used to be English-only no
// matter what language Carolyn was actually replying in, so a Mandarin
// reply falling back to the browser voice got read by an English voice
// with an English accent — exactly the same complaint as the ElevenLabs
// side (see ElevenLabsService::speak()), just on this client-side path.
// Hints are now per-language, and voices are filtered to the agent's
// actual language (window.__aiAssistantLanguageCode) before picking.
var AI_VOICE_HINTS_BY_LANG = {
    en: ['female', 'zira', 'susan', 'hazel', 'samantha', 'moira', 'tessa', 'karen', 'fiona', 'google uk english female', 'google us english'],
    zh: ['female', 'huihui', 'yaoyao', 'ting-ting', 'tingting', 'mei-jia', 'meijia', 'google 普通话'],
    ms: ['female'],
};
var AI_FALLBACK_VOICE_STORAGE_KEY_PREFIX = 'aiAssistantFallbackVoiceName:'; // + language code — a pin for Mandarin must never leak into an English session or vice versa
var __aiAssistantFallbackVoiceResolved = false; // resolved once per page load, then reused for every subsequent fallback call on this page
var __aiAssistantFallbackVoiceCached = null;

function aiAssistantPickVoiceFrom(voices) {
    if (!voices || !voices.length) return null;
    var lang = window.__aiAssistantLanguageCode || 'en';
    var storageKey = AI_FALLBACK_VOICE_STORAGE_KEY_PREFIX + lang;

    var savedName = null;
    try { savedName = localStorage.getItem(storageKey); } catch (e) {}
    if (savedName) {
        var match = voices.find(function(v) { return v.name === savedName; });
        if (match) return match;
        aiAssistantLog('fallback voice: previously pinned voice not available on this device/browser, re-picking', savedName);
    }

    var langVoices = voices.filter(function(v) { return v.lang && v.lang.toLowerCase().indexOf(lang) === 0; });
    if (!langVoices.length && lang !== 'en') {
        aiAssistantLog('fallback voice: no ' + lang + ' voice installed on this device — using an English voice instead of the wrong-language one. For correct pronunciation, connect a real voice provider (ElevenLabs/OpenAI/Google) in Profile > Voice Assistant.', {});
    }
    var englishVoices = voices.filter(function(v) { return v.lang && v.lang.toLowerCase().indexOf('en') === 0; });
    var pool = langVoices.length ? langVoices : (englishVoices.length ? englishVoices : voices);

    var hints = AI_VOICE_HINTS_BY_LANG[lang] || ['female'];
    for (var i = 0; i < hints.length; i++) {
        var hint = hints[i];
        var found = pool.find(function(v) { return v.name.toLowerCase().indexOf(hint) !== -1; });
        if (found) return found;
    }
    return pool[0] || voices[0];
}

// Resolves (and pins) the ONE backup voice this device/browser will ever
// use, waiting properly for Chrome's async voice-list load instead of
// guessing early and getting an empty list. callback receives the chosen
// SpeechSynthesisVoice, or null if none is available at all.
function aiAssistantResolveFallbackVoice(callback) {
    if (__aiAssistantFallbackVoiceResolved) { callback(__aiAssistantFallbackVoiceCached); return; }
    if (!('speechSynthesis' in window)) { callback(null); return; }

    function finish(voices) {
        var chosen = aiAssistantPickVoiceFrom(voices);
        __aiAssistantFallbackVoiceResolved = true;
        __aiAssistantFallbackVoiceCached = chosen;
        if (chosen) {
            try { localStorage.setItem(AI_FALLBACK_VOICE_STORAGE_KEY_PREFIX + (window.__aiAssistantLanguageCode || 'en'), chosen.name); } catch (e) {}
            aiAssistantLog('fallback voice: PINNED for this device/browser', chosen.name);
        } else {
            aiAssistantLog('fallback voice: no voices available — browser will use its own default for this utterance', {});
        }
        callback(chosen);
    }

    var immediate = window.speechSynthesis.getVoices();
    if (immediate && immediate.length) { finish(immediate); return; }

    aiAssistantLog('fallback voice: voice list not ready yet, waiting for voiceschanged event', {});
    var settled = false;
    var timeoutId = setTimeout(function() {
        if (settled) return;
        settled = true;
        aiAssistantLog('fallback voice: voiceschanged never fired within 400ms, proceeding with whatever is available', {});
        finish(window.speechSynthesis.getVoices());
    }, 400);
    window.speechSynthesis.onvoiceschanged = function() {
        if (settled) return;
        settled = true;
        clearTimeout(timeoutId);
        finish(window.speechSynthesis.getVoices());
    };
}

function aiAssistantStopAllAudio() {
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    if (window.__aiAssistantAudio) {
        aiAssistantLog('audio: stopping in-flight/playing audio (new turn or manual stop)');
        try { window.__aiAssistantAudio.pause(); } catch (e) {}
        window.__aiAssistantAudio = null;
    }
}

// Safety net — strip markdown/formatting symbols before anything is
// spoken, so a stray **bold**, `code`, # heading, or bullet dash never
// gets read aloud as literal punctuation ("asterisk asterisk...").
// The system prompt already tells Carolyn not to use these, but this
// catches anything that slips through.
function aiAssistantCleanForSpeech(text) {
    return text
        .replace(/\*\*(.*?)\*\*/g, '$1')
        .replace(/\*(.*?)\*/g, '$1')
        .replace(/`(.*?)`/g, '$1')
        .replace(/^#{1,6}\s*/gm, '')
        .replace(/^[-*•]\s+/gm, '')
        .replace(/[*_`#]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
}

// Fallback voice — the browser's own built-in speech, used only if
// ElevenLabs can't be reached (no quota, offline, etc). Still tuned
// bright/cheerful so it's not jarring compared to Carolyn's real voice.
// onEnd (optional) fires when she's done talking — used by hands-free
// Voice Mode to know when it's safe to start listening again.
function aiAssistantSpeakBrowser(text, onEnd) {
    if (!('speechSynthesis' in window)) { if (onEnd) onEnd(); return; }
    aiAssistantResolveFallbackVoice(function(voice) {
        aiAssistantLog('playback: FALLING BACK to browser voice (ElevenLabs unavailable this turn)', { voiceName: voice ? voice.name : '(browser default)' });
        try {
            var utter = new SpeechSynthesisUtterance(text);
            utter.rate = 1.08;
            utter.pitch = 1.3;
            if (voice) { utter.voice = voice; utter.lang = voice.lang; } // explicit lang alongside voice — some browsers use this to pick pronunciation rules even with a voice object set
            if (onEnd) {
                utter.onend = function() { aiAssistantLog('playback: browser voice ended'); onEnd(); };
                utter.onerror = function(e) { aiAssistantLog('playback: browser voice ERROR', e.error); onEnd(); };
            }
            window.speechSynthesis.speak(utter);
        } catch (e) { aiAssistantLog('playback: browser voice threw synchronously', e.message); if (onEnd) onEnd(); }
    });
}

// Carolyn's real voice, via ElevenLabs (set up 3 Aug 2026). Falls back
// to the browser's built-in voice automatically if ElevenLabs can't
// serve the request for any reason — text is always shown either way.
// onEnd (optional) fires once she's finished speaking (used by
// hands-free Voice Mode to resume listening automatically afterward).
function aiAssistantSpeak(rawText, onEnd) {
    var myTurnId = window.__aiAssistantTurnId; // snapshot — used below to detect a newer turn superseding this one

    if (!window.__aiAssistantVoiceOn) { aiAssistantLog('speak(): skipped, speaker is muted'); if (onEnd) onEnd(); return; }
    aiAssistantStopAllAudio();
    aiAssistantSetState('speaking', { turn: myTurnId });

    if (window.__aiAssistantVoiceModeOn) { aiAssistantSetMicUi('speaking'); }

    var text = aiAssistantCleanForSpeech(rawText);
    if (!text) { if (onEnd) onEnd(); return; }

    // RCA fix — pin the voice_id ElevenLabs actually resolved on this
    // conversation's FIRST call, and send it explicitly on every call
    // after that. This stops a global setting change elsewhere (e.g.
    // voice-test.html re-saved in another tab) from swapping Carolyn's
    // voice mid-conversation — root cause #2 in the RCA.
    var requestBody = { text: text };
    if (window.__aiAssistantPinnedVoiceId) {
        requestBody.voice_id = window.__aiAssistantPinnedVoiceId;
        if (window.__aiAssistantPinnedSpeed) { requestBody.speed = window.__aiAssistantPinnedSpeed; }
    }

    aiAssistantLog('playback: requesting speech', { turn: myTurnId, pinnedVoiceId: window.__aiAssistantPinnedVoiceId, textLength: text.length });
    var requestStartedAt = performance.now();

    var speakHeaders = {'Content-Type': 'application/json'};
    var speakCsrfMeta = document.querySelector('meta[name="csrf-token"]');
    // NEW 3 Aug 2026 — /speak now lives on routes/web.php (session-aware,
    // so it can tell which agent is logged in for per-agent voice
    // providers), which means it DOES check CSRF — unlike before, when
    // it was a stateless routes/api.php proxy with no session at all.
    if (speakCsrfMeta) { speakHeaders['X-CSRF-TOKEN'] = speakCsrfMeta.content; }

    fetch(window.__aiAssistantSpeakEndpoint, {
        method: 'POST',
        headers: speakHeaders,
        body: JSON.stringify(requestBody)
    })
    .then(function(r) {
        var ct = r.headers.get('Content-Type') || '';
        var usedVoiceId = r.headers.get('X-Voice-Id');
        if (!r.ok || ct.indexOf('audio') === -1) { throw new Error('no audio'); }

        // Pin on first success this conversation. Also WARN loudly if a
        // later call ever resolves to a DIFFERENT voice than what's
        // pinned — that would mean the pin itself got bypassed
        // somehow, which should never happen but is worth surfacing.
        if (!window.__aiAssistantPinnedVoiceId && usedVoiceId) {
            window.__aiAssistantPinnedVoiceId = usedVoiceId;
            aiAssistantLog('playback: PINNED voice_id for this conversation', usedVoiceId);
        } else if (usedVoiceId && window.__aiAssistantPinnedVoiceId && usedVoiceId !== window.__aiAssistantPinnedVoiceId) {
            aiAssistantLog('playback: *** VOICE MISMATCH *** server used a different voice than pinned!', { pinned: window.__aiAssistantPinnedVoiceId, serverUsed: usedVoiceId });
        }

        return r.blob();
    })
    .then(function(blob) {
        var elapsedMs = Math.round(performance.now() - requestStartedAt);
        if (myTurnId !== window.__aiAssistantTurnId) {
            aiAssistantLog('playback: DISCARDING stale response (turn ' + myTurnId + ', current is ' + window.__aiAssistantTurnId + ')');
            if (onEnd) onEnd();
            return;
        }
        if (!window.__aiAssistantVoiceOn) { aiAssistantLog('playback: muted while request was in flight, discarding'); if (onEnd) onEnd(); return; }

        aiAssistantLog('playback: ElevenLabs audio received in ' + elapsedMs + 'ms, starting playback');
        var url = URL.createObjectURL(blob);
        var audio = new Audio(url);
        window.__aiAssistantAudio = audio;
        if (onEnd) {
            audio.onended = function() { aiAssistantLog('playback: ElevenLabs audio ended normally'); onEnd(); };
            audio.onerror = function(e) { aiAssistantLog('playback: ElevenLabs <audio> element ERROR', e); onEnd(); };
        }
        audio.play().catch(function(e) {
            aiAssistantLog('playback: audio.play() rejected, falling back to browser voice', e.message);
            aiAssistantSpeakBrowser(text, onEnd);
        });
    })
    .catch(function(e) {
        aiAssistantLog('playback: ElevenLabs request FAILED, falling back to browser voice', e.message);
        aiAssistantSpeakBrowser(text, onEnd); // ElevenLabs unavailable — use the browser voice instead
    });
}

function aiAssistantToggleVoice() {
    window.__aiAssistantVoiceOn = !window.__aiAssistantVoiceOn;
    var btn = document.getElementById('aiAssistantVoiceToggle');
    if (window.__aiAssistantVoiceOn) {
        btn.textContent = '🔊';
        btn.title = window.__aiAssistantI18n.voiceOffTooltip;
    } else {
        btn.textContent = '🔇';
        btn.title = window.__aiAssistantI18n.voiceOnTooltip;
        aiAssistantStopAllAudio();
    }
}

// Hands-free Voice Mode (3 Aug 2026, ChatGPT-style) — click the mic
// ONCE to start a whole voice conversation: speak, Carolyn replies out
// loud, then she automatically starts listening again for your next
// turn — no re-clicking per message. Click the mic again anytime to
// leave Voice Mode. Uses the browser's own free speech recognition
// (Chrome/Edge); separate from aiAssistantSpeak() above, which is
// Carolyn talking TO you — this is the reverse direction.
var aiAssistantRecognition = null;
var aiAssistantListening = false;
window.__aiAssistantVoiceModeOn = false;

function aiAssistantMicSupported() {
    return ('webkitSpeechRecognition' in window) || ('SpeechRecognition' in window);
}

function aiAssistantSetMicUi(state) {
    var micBtn = document.getElementById('aiAssistantMicBtn');
    var input = document.getElementById('aiAssistantInput');
    if (state === 'listening') {
        micBtn.textContent = '🔴';
        micBtn.title = window.__aiAssistantI18n.micListeningTooltip;
        input.placeholder = window.__aiAssistantI18n.listeningPlaceholder;
    } else if (state === 'speaking') {
        micBtn.textContent = '🔊';
        micBtn.title = window.__aiAssistantI18n.speakingTooltip;
        input.placeholder = window.__aiAssistantI18n.speakingPlaceholder;
    } else {
        micBtn.textContent = '🎤';
        micBtn.title = window.__aiAssistantI18n.micIdleTooltip;
        input.placeholder = window.__aiAssistantI18n.inputPlaceholder;
    }
}

var aiAssistantStartWatchdog = null; // RCA safeguard #3 — detects a recognizer that never fires onstart at all

function aiAssistantStartListening(retryCount) {
    retryCount = retryCount || 0;
    if (!window.__aiAssistantVoiceModeOn) { aiAssistantLog('mic: startListening() called but Voice Mode is off, ignoring'); return; }

    aiAssistantLog('mic: creating new SpeechRecognition instance and calling .start()', { retryCount: retryCount });

    var SpeechRecognitionImpl = window.SpeechRecognition || window.webkitSpeechRecognition;
    aiAssistantRecognition = new SpeechRecognitionImpl();
    var thisRecognition = aiAssistantRecognition; // local reference — guards against a later instance overwriting the global mid-callback
    // en-GB tends to transcribe Malaysian English accents more
    // accurately than the default en-US model — changed 3 Aug 2026
    // after Chris reported the recognized text didn't match what he said.
    thisRecognition.lang = 'en-GB';
    thisRecognition.continuous = false;
    thisRecognition.interimResults = false;

    var gotResult = false;
    var didStart = false;

    // RCA safeguard #3 (proposed + implemented) — Chrome's speech
    // recognizer can sometimes go silent after a few rapid restart
    // cycles: .start() doesn't throw, but NO callback (onstart included)
    // ever fires again. This watchdog detects that specific failure
    // mode (matches "mic stops capturing, no visible error") and forces
    // a clean recovery instead of leaving Voice Mode stuck silently.
    clearTimeout(aiAssistantStartWatchdog);
    aiAssistantStartWatchdog = setTimeout(function() {
        if (!didStart && window.__aiAssistantVoiceModeOn && aiAssistantRecognition === thisRecognition) {
            aiAssistantLog('mic: *** WATCHDOG *** onstart never fired within 4s — recognizer appears stuck, forcing recovery', { retryCount: retryCount });
            try { thisRecognition.abort(); } catch (e) {}
            if (retryCount < 2) {
                setTimeout(function() { aiAssistantStartListening(retryCount + 1); }, 400);
            } else {
                aiAssistantLog('mic: watchdog gave up after 2 retries — turning Voice Mode off');
                window.__aiAssistantVoiceModeOn = false;
                aiAssistantSetMicUi('idle');
                alert(window.__aiAssistantI18n.micWatchdogAlert);
            }
        }
    }, 4000);

    thisRecognition.onstart = function() {
        didStart = true;
        clearTimeout(aiAssistantStartWatchdog);
        aiAssistantListening = true;
        aiAssistantLog('mic: onstart fired — listening for speech');
        aiAssistantSetState('listening');
        aiAssistantSetMicUi('listening');
    };

    thisRecognition.onresult = function(event) {
        gotResult = true;
        var transcript = event.results[0][0].transcript;
        aiAssistantLog('mic: STT transcript received', transcript);
        aiAssistantSetState('processing');
        document.getElementById('aiAssistantInput').value = transcript;
        aiAssistantSend(); // Voice Mode resumes listening once Carolyn finishes replying — see aiAssistantSend()
    };

    thisRecognition.onerror = function(event) {
        aiAssistantLog('mic: onerror', event.error);
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
            window.__aiAssistantVoiceModeOn = false;
            aiAssistantSetMicUi('idle');
            alert(window.__aiAssistantI18n.micBlockedAlert);
        } else if (event.error !== 'no-speech' && event.error !== 'aborted') {
            alert(window.__aiAssistantI18n.micUnclearAlertTemplate.replace(':error', event.error));
        }
    };

    thisRecognition.onend = function() {
        aiAssistantListening = false;
        aiAssistantLog('mic: onend fired', { gotResult: gotResult, voiceModeOn: window.__aiAssistantVoiceModeOn });
        // Only auto-restart here if nothing was said (silence/false
        // start) — if we DID get a result, aiAssistantSend() is now
        // waiting on Carolyn's reply, and listening resumes only after
        // she's done speaking (see the onEnd callback below).
        if (window.__aiAssistantVoiceModeOn && !gotResult) {
            // RCA safeguard #3 — small delay before recreating the
            // recognizer, giving Chrome a moment to fully release the
            // previous mic session first instead of instantly re-acquiring it.
            setTimeout(function() { aiAssistantStartListening(); }, 300);
        } else if (!window.__aiAssistantVoiceModeOn) {
            aiAssistantSetMicUi('idle');
        }
    };

    try {
        thisRecognition.start();
    } catch (e) {
        aiAssistantLog('mic: .start() threw synchronously', e.message);
        clearTimeout(aiAssistantStartWatchdog);
        window.__aiAssistantVoiceModeOn = false;
        aiAssistantSetMicUi('idle');
        alert(window.__aiAssistantI18n.micStartFailedAlertTemplate.replace(':error', e.message));
    }
}

function aiAssistantToggleMic() {
    if (!aiAssistantMicSupported()) {
        alert(window.__aiAssistantI18n.micUnsupportedAlert);
        return;
    }

    if (window.__aiAssistantVoiceModeOn) {
        // Turn Voice Mode OFF — stop listening and stop any reply mid-speech.
        aiAssistantLog('mic: user clicked mic — turning Voice Mode OFF');
        window.__aiAssistantVoiceModeOn = false;
        clearTimeout(aiAssistantStartWatchdog);
        if (aiAssistantListening && aiAssistantRecognition) { aiAssistantRecognition.stop(); }
        aiAssistantStopAllAudio();
        aiAssistantSetMicUi('idle');
        aiAssistantSetState('idle');
        return;
    }

    // Turn Voice Mode ON.
    aiAssistantLog('mic: user clicked mic — turning Voice Mode ON');
    window.__aiAssistantVoiceModeOn = true;
    aiAssistantStopAllAudio(); // don't let Carolyn talk over you while you're speaking
    aiAssistantStartListening();
}

// NEW 8 Aug 2026 — draggable panel, per Chris: it "cover the screen
// field most of the time." Drag by the header bar (#aiAssistantPanelHeader);
// position is remembered across page loads (this is a full-reload MPA,
// not a SPA) via localStorage, and re-clamped to the CURRENT viewport
// every time it's applied so a position saved on a bigger screen never
// drags the panel off-screen on a smaller one.
(function() {
    var panel = document.getElementById('aiAssistantPanel');
    var header = document.getElementById('aiAssistantPanelHeader');
    if (!panel || !header) { return; }

    var POS_KEY = 'aiAssistantPanelPos';
    var dragging = false, moved = false, startX, startY, startLeft, startTop;

    function clamp(left, top) {
        var maxLeft = Math.max(4, window.innerWidth - panel.offsetWidth - 4);
        var maxTop = Math.max(4, window.innerHeight - panel.offsetHeight - 4);
        return { left: Math.min(Math.max(4, left), maxLeft), top: Math.min(Math.max(4, top), maxTop) };
    }

    function applyPosition(left, top) {
        var c = clamp(left, top);
        panel.style.left = c.left + 'px';
        panel.style.top = c.top + 'px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
    }

    // Called every time the panel is opened — re-reads the saved spot (if
    // any) and re-clamps it against whatever size the browser window
    // actually is right now.
    window.__aiAssistantRestorePanelPosition = function() {
        var saved = null;
        try { saved = JSON.parse(localStorage.getItem(POS_KEY) || 'null'); } catch (e) {}
        if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
            applyPosition(saved.left, saved.top);
        }
    };

    function onPointerDown(e) {
        // Ignore drags started on the header's own icon buttons (voice
        // toggle, end chat, close) — those keep their normal click behavior.
        if (e.target.closest('span[onclick]')) { return; }
        dragging = true;
        moved = false;
        var point = e.touches ? e.touches[0] : e;
        var rect = panel.getBoundingClientRect();
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
                var rect = panel.getBoundingClientRect();
                localStorage.setItem(POS_KEY, JSON.stringify({ left: rect.left, top: rect.top }));
            } catch (e) {}
        }
        dragging = false;
        document.removeEventListener('mousemove', onPointerMove);
        document.removeEventListener('mouseup', onPointerUp);
        document.removeEventListener('touchmove', onPointerMove);
        document.removeEventListener('touchend', onPointerUp);
    }

    header.addEventListener('mousedown', onPointerDown);
    header.addEventListener('touchstart', onPointerDown, { passive: true });
})();

// NEW 22 Sep 2026 -- per Chris: the collapsed bubble icon itself must
// also be movable, not just the opened panel -- it was sitting fixed
// top-right and could land on top of a page title.
(function () {
    var bubble = document.getElementById('aiAssistantBubble');
    if (!bubble) { return; }
    var POS_KEY = 'aiAssistantBubblePos';
    var dragging = false, moved = false, startX, startY, startLeft, startTop;

    function clamp(left, top) {
        var maxLeft = Math.max(4, window.innerWidth - bubble.offsetWidth - 4);
        var maxTop = Math.max(4, window.innerHeight - bubble.offsetHeight - 4);
        return { left: Math.min(Math.max(4, left), maxLeft), top: Math.min(Math.max(4, top), maxTop) };
    }

    function applyPosition(left, top) {
        var c = clamp(left, top);
        bubble.style.left = c.left + 'px';
        bubble.style.top = c.top + 'px';
        bubble.style.right = 'auto';
        bubble.style.bottom = 'auto';
    }

    (function restore() {
        var saved = null;
        try { saved = JSON.parse(localStorage.getItem(POS_KEY) || 'null'); } catch (e) {}
        if (saved && typeof saved.left === 'number' && typeof saved.top === 'number') {
            applyPosition(saved.left, saved.top);
        }
    })();

    function onPointerDown(e) {
        dragging = true;
        moved = false;
        var point = e.touches ? e.touches[0] : e;
        var rect = bubble.getBoundingClientRect();
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
                var rect = bubble.getBoundingClientRect();
                localStorage.setItem(POS_KEY, JSON.stringify({ left: rect.left, top: rect.top }));
            } catch (e) {}
            window.__aiAssistantJustDragged = true;
        }
        dragging = false;
        document.removeEventListener('mousemove', onPointerMove);
        document.removeEventListener('mouseup', onPointerUp);
        document.removeEventListener('touchmove', onPointerMove);
        document.removeEventListener('touchend', onPointerUp);
    }

    bubble.addEventListener('mousedown', onPointerDown);
    bubble.addEventListener('touchstart', onPointerDown, { passive: true });
})();

function aiAssistantToggle() {
    if (window.__aiAssistantJustDragged) { window.__aiAssistantJustDragged = false; return; }
    var panel = document.getElementById('aiAssistantPanel');
    var showing = panel.style.display === 'flex';
    panel.style.display = showing ? 'none' : 'flex';
    if (!showing) {
        if (window.__aiAssistantRestorePanelPosition) { window.__aiAssistantRestorePanelPosition(); }
    }
    if (!showing && !window.__aiAssistantOpened) {
        window.__aiAssistantOpened = true;
        aiAssistantAppend('assistant', window.__aiAssistantGreeting);
        aiAssistantSpeak(window.__aiAssistantGreeting);
    }
    if (!showing) {
        document.getElementById('aiAssistantInput').focus();
    }
    if (showing) {
        aiAssistantStopAllAudio(); // stop talking if the user minimizes the panel — conversation itself is kept, see aiAssistantPersistState()
        clearTimeout(aiAssistantStartWatchdog);
        window.__aiAssistantVoiceModeOn = false;
        if (aiAssistantListening && aiAssistantRecognition) { aiAssistantRecognition.stop(); }
        aiAssistantSetMicUi('idle');
        aiAssistantSetState('idle');
    }
    aiAssistantPersistState();
}

function aiAssistantAppend(who, text, skipLog) {
    var box = document.getElementById('aiAssistantMessages');
    var bubble = document.createElement('div');
    var isUser = who === 'user';
    bubble.style.cssText = 'max-width:85%; padding:7px 10px; border-radius:10px; line-height:1.4; white-space:pre-wrap; ' +
        (isUser
            ? 'align-self:flex-end; background:#1565C0; color:#fff; border-bottom-right-radius:2px;'
            : 'align-self:flex-start; background:#fff; color:#374151; border:1px solid #E2E8F0; border-bottom-left-radius:2px;');
    bubble.textContent = text;
    box.appendChild(bubble);
    box.scrollTop = box.scrollHeight;
    if (!skipLog) { window.__aiAssistantDisplayLog.push({ who: who, text: text }); }
}

// NEW 3 Aug 2026 — replays a restored conversation's visible bubbles (and
// reopens the panel, without re-speaking the greeting) so navigating to a
// new screen shows you exactly where you left off, instead of an empty
// panel with history silently loaded behind the scenes.
function aiAssistantRestoreUi() {
    if (window.__aiAssistantDisplayLog.length) {
        window.__aiAssistantDisplayLog.forEach(function(m) { aiAssistantAppend(m.who, m.text, true); });
    }
    if (window.__aiAssistantPanelWasOpen) {
        document.getElementById('aiAssistantPanel').style.display = 'flex';
    }
}
aiAssistantRestoreUi();

// NEW 5 Aug 2026 — checks for outstanding items (overdue tickets, unread
// notifications, pending approvals, renewal/quotation reminders) and:
// (1) always updates the small red badge count on the bubble, every page
//     load, so it's visible even if the agent never opens the panel;
// (2) auto-opens the panel with a warm, polite summary ONCE per agent per
//     calendar day (server-side flag — see AiAssistantController::alerts),
//     and only into a tab that hasn't already got a conversation going
//     (never interrupts something already in progress).
function aiAssistantRefreshAlerts() {
    if (window.__aiAssistantGuestMode || !window.__aiAssistantAlertsEndpoint) { return; }

    fetch(window.__aiAssistantAlertsEndpoint, { headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var badge = document.getElementById('aiAssistantBadge');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count > 99 ? '99+' : String(data.count);
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            }

            if (data.should_popup && data.message && !window.__aiAssistantOpened) {
                aiAssistantLog('alerts: showing proactive popup', { count: data.count });
                window.__aiAssistantOpened = true;
                document.getElementById('aiAssistantPanel').style.display = 'flex';
                aiAssistantAppend('assistant', data.message);
                aiAssistantSpeak(data.message);
                aiAssistantPersistState();
            }
        })
        .catch(function(e) { aiAssistantLog('alerts: fetch failed, skipping this load (badge/popup just won\'t show)', e.message); });
}
aiAssistantRefreshAlerts();

function aiAssistantSend() {
    var input = document.getElementById('aiAssistantInput');
    var message = input.value.trim();
    if (!message) return;
    input.value = '';

    window.__aiAssistantTurnId++; // RCA — new turn supersedes any in-flight speak() from a prior turn
    var myTurnId = window.__aiAssistantTurnId;
    aiAssistantSetState('processing', { turn: myTurnId });
    aiAssistantLog('chat: sending message to Claude', { turn: myTurnId, message: message });
    var requestStartedAt = performance.now();

    aiAssistantAppend('user', message);

    var thinking = document.createElement('div');
    thinking.id = 'aiAssistantThinking';
    thinking.style.cssText = 'align-self:flex-start; background:#fff; color:#9ca3af; border:1px solid #E2E8F0; padding:7px 10px; border-radius:10px; font-size:11px;';
    thinking.textContent = '...';
    document.getElementById('aiAssistantMessages').appendChild(thinking);
    document.getElementById('aiAssistantMessages').scrollTop = 999999;

    var pageContext = null;
    var titleEl = document.querySelector('.topbar-title');
    if (titleEl) {
        pageContext = titleEl.textContent.trim();
    } else if (window.__aiAssistantGuestMode) {
        // Guest pages (login/register — agent or vendor) have no topbar,
        // so fall back to the label the page itself declared.
        pageContext = window.__aiAssistantGuestPageLabel || 'Login Page';
    }

    var headers = {'Content-Type': 'application/json'};
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    if (csrfMeta) { headers['X-CSRF-TOKEN'] = csrfMeta.content; }

    fetch(window.__aiAssistantEndpoint, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify({
            message: message,
            history: window.__aiAssistantHistory,
            page_context: pageContext
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var elapsedMs = Math.round(performance.now() - requestStartedAt);
        var t = document.getElementById('aiAssistantThinking');
        if (t) t.remove();

        if (myTurnId !== window.__aiAssistantTurnId) {
            aiAssistantLog('chat: DISCARDING stale Claude response (turn ' + myTurnId + ', current is ' + window.__aiAssistantTurnId + ')');
            return;
        }

        if (data.status === 'OK') {
            aiAssistantLog('chat: Claude reply received in ' + elapsedMs + 'ms', { turn: myTurnId, reply: data.reply, ticketCreated: data.ticket_created });
            aiAssistantAppend('assistant', data.reply);
            aiAssistantSpeak(data.reply, aiAssistantResumeVoiceModeIfOn);
            window.__aiAssistantHistory = data.history || window.__aiAssistantHistory;

            // NEW 3 Aug 2026 — AI Guided Navigation hand-off. Carolyn
            // decided this message was a real intent to DO something, not
            // just a question — hand off to the separate guidance overlay
            // (ai-guidance-overlay.blade.php), which highlights/explains
            // on-screen. Carolyn never clicks anything herself from here.
            if (data.guidance_goal && window.aiGuidanceStart) {
                aiAssistantLog('chat: handing off to Guided Navigation', data.guidance_goal);
                window.aiGuidanceStart(data.guidance_goal);
            }
        } else {
            aiAssistantLog('chat: Claude returned an ERROR after ' + elapsedMs + 'ms', data.message);
            aiAssistantAppend('assistant', data.message || window.__aiAssistantI18n.genericErrorMessage);
            aiAssistantSpeak(data.message || window.__aiAssistantI18n.genericErrorMessage, aiAssistantResumeVoiceModeIfOn);
        }
        aiAssistantPersistState(); // NEW 3 Aug 2026 — save so this turn survives the next page navigation
    })
    .catch(function(e) {
        var t = document.getElementById('aiAssistantThinking');
        if (t) t.remove();
        aiAssistantLog('chat: fetch FAILED (network/parse error)', e.message);
        if (myTurnId !== window.__aiAssistantTurnId) {
            aiAssistantLog('chat: stale failed turn, not surfacing error to user');
            return;
        }
        aiAssistantAppend('assistant', window.__aiAssistantI18n.connectionErrorMessage);
        aiAssistantSpeak(window.__aiAssistantI18n.connectionErrorMessage, aiAssistantResumeVoiceModeIfOn);
        aiAssistantPersistState();
    });
}

// Voice Mode's loop: once Carolyn finishes speaking her reply, start
// listening again automatically — this is what makes it hands-free
// instead of needing a click per turn.
function aiAssistantResumeVoiceModeIfOn() {
    aiAssistantLog('speak(): onEnd fired', { voiceModeOn: window.__aiAssistantVoiceModeOn });
    if (window.__aiAssistantVoiceModeOn) {
        // RCA safeguard #3 — same short delay as the silence-restart
        // path, so we're consistent everywhere a recognizer gets
        // recreated: give the browser a beat to release the previous
        // audio session before asking for the mic again.
        setTimeout(function() { aiAssistantStartListening(); }, 300);
    } else {
        aiAssistantSetState('idle');
    }
}
</script>
