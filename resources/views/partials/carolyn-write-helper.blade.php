{{-- NEW 8 Aug 2026 — "Carolyn help to write," reusable on ANY screen with
     a textarea the user composes. Drop this @include right after the
     closing </textarea> tag. Required: 'uid' (unique string on this page),
     'bodyFieldId' (id of the textarea), 'contentType' (a key from
     AiAssistantService::WRITE_ASSIST_TYPES), 'assistUrl' (resolved route
     URL — differs by guard, so the including page passes it explicitly).
     Optional: 'titleFieldId' (id of a companion title/subject input, if
     one exists on this form); 'openUpward' (bool, default false) — set
     true when this widget sits at the very BOTTOM of a fixed-height,
     overflow:hidden panel with no room below it (e.g. Help Desk's
     compose/reply boxes), so the suggestion box opens above the radio
     row instead of below, where it would otherwise be invisibly clipped
     by the panel's overflow:hidden.

     Deliberately built as a position:absolute overlay (not part of normal
     page flow) so dropping this into an already tightly-fit, no-scroll
     screen never pushes other fields around or breaks that screen's fit —
     the suggestion box floats over whatever's on the open side only while
     open. The actual JS functions (carolynWriteModeChanged/Ask/Accept/
     Cancel) live once in partials.carolyn-write-helper-script, included
     globally in layouts.dashboard and layouts.vendor — this partial only
     registers this one field's config into window.__carolynWriteCfg. --}}
@php($uid = $uid ?? 'aiw')
@php($openUpward = $openUpward ?? false)
<div style="position:relative; margin:2px 0 8px;">
    <div style="display:flex; align-items:center; gap:10px; font-size:8.5px;">
        <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#374151;">
            <input type="radio" name="writeMode_{{ $uid }}" id="writeModeMyself_{{ $uid }}" checked onchange="carolynWriteModeChanged('{{ $uid }}')"> {{ __('partials.write_it_myself_label') }}
        </label>
        <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
            <input type="radio" name="writeMode_{{ $uid }}" id="writeModeCarolyn_{{ $uid }}" onchange="carolynWriteModeChanged('{{ $uid }}')"> ✨ {{ __('partials.carolyn_help_write_label') }}
        </label>
    </div>
    <div id="carolynBox_{{ $uid }}" style="display:none; position:absolute; {{ $openUpward ? 'bottom:100%; margin-bottom:3px;' : 'top:100%; margin-top:3px;' }} left:0; right:0; z-index:80; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px; box-shadow:0 6px 18px rgba(0,0,0,.18); max-height:220px; overflow-y:auto; box-sizing:border-box;">
        <div id="carolynStatus_{{ $uid }}" style="color:#7c3aed; font-weight:600;">✨ {{ __('partials.carolyn_polishing_status') }}</div>
        <div id="carolynResult_{{ $uid }}" style="display:none;">
            @if(!empty($titleFieldId))
            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('partials.suggested_title_label') }}</div>
            <div id="carolynTitlePreview_{{ $uid }}" style="font-weight:600; color:#111827; margin-bottom:6px;"></div>
            @endif
            <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('partials.suggested_text_label') }}</div>
            <div id="carolynBodyPreview_{{ $uid }}" style="white-space:pre-wrap; color:#111827; margin-bottom:8px;"></div>
            <div style="display:flex; gap:6px;">
                <button type="button" onclick="carolynAccept('{{ $uid }}')" style="background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">✅ {{ __('partials.use_this_button') }}</button>
                <button type="button" onclick="carolynAsk('{{ $uid }}')" style="background:#7c3aed; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">🔁 {{ __('partials.try_again_button') }}</button>
                <button type="button" onclick="carolynCancel('{{ $uid }}')" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">✖ {{ __('partials.cancel_button') }}</button>
            </div>
        </div>
        <div id="carolynError_{{ $uid }}" style="display:none; color:#b71c1c;"></div>
    </div>
</div>
<script>
(function() {
    window.__carolynWriteCfg = window.__carolynWriteCfg || {};
    window.__carolynWriteCfg['{{ $uid }}'] = {
        bodyFieldId: {!! json_encode($bodyFieldId) !!},
        titleFieldId: {!! !empty($titleFieldId) ? json_encode($titleFieldId) : 'null' !!},
        contentType: {!! json_encode($contentType) !!},
        assistUrl: {!! json_encode($assistUrl) !!}
    };
})();
</script>
