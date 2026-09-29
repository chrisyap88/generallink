{{--
    NEW 17 Sep 2026 — per Chris: "this apply all module when need to
    write message please check all." Reusable "Carolyn help to write"
    block — drop this @include right after your <textarea>, pass the
    params below, and it wires up: a Write Myself/Carolyn Help Write
    toggle, the suggestion box, and the JS that calls the shared
    App\Http\Controllers\Shared\AiWriteAssistController endpoint.

    Required params:
      carolynBodyId    — the id of your <textarea> (the message/body field)
      carolynType      — one of App\Services\AiAssistantService::WRITE_ASSIST_TYPES

    Optional params:
      carolynTitleId   — id of a companion title/subject <input>, if this
                          content type has_title (omit/null if none)
      carolynRoute     — defaults to route('ai-write-assist'); pass
                          route('vendor.write-assist') on vendor-guard screens
      carolynInstance  — unique suffix (e.g. 'clear', 'row-3') REQUIRED
                          whenever a page includes this partial more than
                          once, so each instance's ids/JS don't collide
--}}
@php
    $carolynRouteUrl = $carolynRoute ?? route('ai-write-assist');
    $cx = $carolynInstance ?? '';
    $cid = fn ($base) => $base . ($cx !== '' ? '_' . $cx : '');
@endphp

<div style="display:flex; align-items:center; gap:10px; font-size:9px; margin-bottom:4px;">
    <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#546E7A;">
        <input type="radio" name="carolynWriteMode{{ $cx }}" id="{{ $cid('carolynWriteModeMyself') }}" checked onchange="carolynWriteModeChanged_{{ $cx ?: 'x' }}()"> {{ __('notice_board.write_myself_option') }}
    </label>
    <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
        <input type="radio" name="carolynWriteMode{{ $cx }}" id="{{ $cid('carolynWriteModeCarolyn') }}" onchange="carolynWriteModeChanged_{{ $cx ?: 'x' }}()"> {{ __('notice_board.carolyn_help_write_option') }}
    </label>
</div>

<div id="{{ $cid('carolynSuggestBox') }}" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px; margin-bottom:8px;">
    <div id="{{ $cid('carolynStatus') }}" style="color:#7c3aed; font-weight:600;">{{ __('notice_board.carolyn_polishing_status') }}</div>
    <div id="{{ $cid('carolynResult') }}" style="display:none;">
        @if(!empty($carolynTitleId))
        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_title_label') }}</div>
        <div id="{{ $cid('carolynTitlePreview') }}" style="font-weight:600; color:#111827; margin-bottom:6px;"></div>
        @endif
        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_message_label') }}</div>
        <div id="{{ $cid('carolynBodyPreview') }}" style="white-space:pre-wrap; color:#111827; margin-bottom:8px;"></div>
        <div style="display:flex; gap:6px;">
            <button type="button" onclick="carolynAccept_{{ $cx ?: 'x' }}()" style="background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.use_this_button') }}</button>
            <button type="button" onclick="carolynAsk_{{ $cx ?: 'x' }}()" style="background:#7c3aed; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.try_again_button') }}</button>
            <button type="button" onclick="carolynCancel_{{ $cx ?: 'x' }}()" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.cancel_button') }}</button>
        </div>
    </div>
    <div id="{{ $cid('carolynError') }}" style="display:none; color:#b71c1c;"></div>
</div>

<script>
(function() {
    var SUF = @json($cx ?: 'x');
    var CAROLYN_I18N = {
        typeFirstAlert: @json(__('notice_board.carolyn_type_first_alert_js')),
        polishingStatus: @json(__('notice_board.carolyn_polishing_status')),
        errorFallback: @json(__('notice_board.carolyn_error_fallback_js')),
        connectionError: @json(__('notice_board.carolyn_connection_error_js'))
    };
    // carolynBodyId/carolynTitleId are the CALLER's own field ids —
    // used exactly as given, never suffixed (only this partial's own
    // generated elements below get the instance suffix).
    var CAROLYN_BODY_ID = @json($carolynBodyId);
    var CAROLYN_TITLE_ID = @json($carolynTitleId ?? null);
    var CAROLYN_TYPE = @json($carolynType);
    var CAROLYN_URL = @json($carolynRouteUrl);
    var CAROLYN_BOX_PREFIX = @json($cx);

    function id(base) { return base + (CAROLYN_BOX_PREFIX ? ('_' + CAROLYN_BOX_PREFIX) : ''); }

    window['carolynWriteModeChanged_' + SUF] = function() {
        if (!document.getElementById(id('carolynWriteModeCarolyn')).checked) {
            document.getElementById(id('carolynSuggestBox')).style.display = 'none';
            return;
        }
        var body = document.getElementById(CAROLYN_BODY_ID).value.trim();
        if (!body) {
            alert(CAROLYN_I18N.typeFirstAlert);
            document.getElementById(id('carolynWriteModeMyself')).checked = true;
            return;
        }
        window['carolynAsk_' + SUF]();
    };

    window['carolynAsk_' + SUF] = function() {
        var box = document.getElementById(id('carolynSuggestBox'));
        var status = document.getElementById(id('carolynStatus'));
        var result = document.getElementById(id('carolynResult'));
        var error = document.getElementById(id('carolynError'));
        box.style.display = 'block';
        status.style.display = 'block';
        status.textContent = CAROLYN_I18N.polishingStatus;
        result.style.display = 'none';
        error.style.display = 'none';

        var payload = { content_type: CAROLYN_TYPE, body: document.getElementById(CAROLYN_BODY_ID).value };
        if (CAROLYN_TITLE_ID) { payload.title = document.getElementById(CAROLYN_TITLE_ID).value; }

        fetch(CAROLYN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            status.style.display = 'none';
            if (data.status !== 'OK') {
                error.style.display = 'block';
                error.textContent = '⚠ ' + (data.message || CAROLYN_I18N.errorFallback);
                document.getElementById(id('carolynWriteModeMyself')).checked = true;
                return;
            }
            if (CAROLYN_TITLE_ID) { document.getElementById(id('carolynTitlePreview')).textContent = data.title || ''; }
            document.getElementById(id('carolynBodyPreview')).textContent = data.body;
            result.style.display = 'block';
        })
        .catch(function() {
            status.style.display = 'none';
            error.style.display = 'block';
            error.textContent = '⚠ ' + CAROLYN_I18N.connectionError;
            document.getElementById(id('carolynWriteModeMyself')).checked = true;
        });
    };

    window['carolynAccept_' + SUF] = function() {
        if (CAROLYN_TITLE_ID) { document.getElementById(CAROLYN_TITLE_ID).value = document.getElementById(id('carolynTitlePreview')).textContent; }
        document.getElementById(CAROLYN_BODY_ID).value = document.getElementById(id('carolynBodyPreview')).textContent;
        document.getElementById(id('carolynSuggestBox')).style.display = 'none';
        document.getElementById(id('carolynWriteModeMyself')).checked = true;
    };

    window['carolynCancel_' + SUF] = function() {
        document.getElementById(id('carolynSuggestBox')).style.display = 'none';
        document.getElementById(id('carolynWriteModeMyself')).checked = true;
    };
})();
</script>
