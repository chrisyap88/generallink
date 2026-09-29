{{-- NEW 8 Aug 2026 — shared JS for every "Carolyn help to write" field on
     the page (see partials.carolyn-write-helper). Included ONCE globally
     in layouts.dashboard and layouts.vendor so any number of fields on a
     page can each register their own config into window.__carolynWriteCfg
     without duplicating these functions per field. --}}
<script>
var CAROLYN_I18N = {
    noDraftAlert: @json(__('partials.carolyn_no_draft_alert')),
    polishingStatus: @json(__('partials.carolyn_polishing_status')),
    rewriteFailedFallback: @json(__('partials.carolyn_rewrite_failed_fallback')),
    connectionError: @json(__('partials.carolyn_connection_error')),
};
function carolynWriteModeChanged(uid) {
    var cfg = (window.__carolynWriteCfg || {})[uid];
    if (!cfg) { return; }
    var carolynRadio = document.getElementById('writeModeCarolyn_' + uid);
    if (!carolynRadio.checked) {
        document.getElementById('carolynBox_' + uid).style.display = 'none';
        return;
    }
    var bodyEl = document.getElementById(cfg.bodyFieldId);
    if (!bodyEl || !bodyEl.value.trim()) {
        alert(CAROLYN_I18N.noDraftAlert);
        document.getElementById('writeModeMyself_' + uid).checked = true;
        return;
    }
    carolynAsk(uid);
}

function carolynAsk(uid) {
    var cfg = (window.__carolynWriteCfg || {})[uid];
    if (!cfg) { return; }
    var box = document.getElementById('carolynBox_' + uid);
    var status = document.getElementById('carolynStatus_' + uid);
    var result = document.getElementById('carolynResult_' + uid);
    var error = document.getElementById('carolynError_' + uid);
    box.style.display = 'block';
    status.style.display = 'block';
    status.textContent = '✨ ' + CAROLYN_I18N.polishingStatus;
    result.style.display = 'none';
    error.style.display = 'none';

    var titleVal = cfg.titleFieldId ? (document.getElementById(cfg.titleFieldId).value || '') : '';
    var bodyVal = document.getElementById(cfg.bodyFieldId).value;

    fetch(cfg.assistUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ title: titleVal, body: bodyVal, content_type: cfg.contentType })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        status.style.display = 'none';
        if (data.status !== 'OK') {
            error.style.display = 'block';
            error.textContent = '⚠ ' + (data.message || CAROLYN_I18N.rewriteFailedFallback);
            document.getElementById('writeModeMyself_' + uid).checked = true;
            return;
        }
        if (cfg.titleFieldId) {
            document.getElementById('carolynTitlePreview_' + uid).textContent = data.title;
        }
        document.getElementById('carolynBodyPreview_' + uid).textContent = data.body;
        result.style.display = 'block';
    })
    .catch(function() {
        status.style.display = 'none';
        error.style.display = 'block';
        error.textContent = '⚠ ' + CAROLYN_I18N.connectionError;
        document.getElementById('writeModeMyself_' + uid).checked = true;
    });
}

function carolynAccept(uid) {
    var cfg = (window.__carolynWriteCfg || {})[uid];
    if (!cfg) { return; }
    if (cfg.titleFieldId) {
        document.getElementById(cfg.titleFieldId).value = document.getElementById('carolynTitlePreview_' + uid).textContent;
    }
    document.getElementById(cfg.bodyFieldId).value = document.getElementById('carolynBodyPreview_' + uid).textContent;
    document.getElementById('carolynBox_' + uid).style.display = 'none';
    document.getElementById('writeModeMyself_' + uid).checked = true;
}

function carolynCancel(uid) {
    document.getElementById('carolynBox_' + uid).style.display = 'none';
    document.getElementById('writeModeMyself_' + uid).checked = true;
}
</script>
