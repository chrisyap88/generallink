<script>
var NOTICE_I18N = {
    typeFirstAlert: @json(__('notice_board.carolyn_type_first_alert_js')),
    polishingStatus: @json(__('notice_board.carolyn_polishing_status')),
    errorFallback: @json(__('notice_board.carolyn_error_fallback_js')),
    connectionError: @json(__('notice_board.carolyn_connection_error_js'))
};

var CBE_NOTICE_STYLES = {
    GENERAL: { label: @json(__('cbe_records.style_auto_option')), bg: '#FFFFFF', accent: '#546E7A', text: '#263238' },
    @foreach($styleOptions as $s)
    {{ $s->style_key }}: { label: @json($s->label), bg: @json($s->bg_color), accent: @json($s->accent_color), text: @json($s->text_color) },
    @endforeach
};

function noticeWriteModeChanged() {
    if (!document.getElementById('writeModeCarolyn').checked) {
        document.getElementById('carolynSuggestBox').style.display = 'none';
        return;
    }
    var body = document.getElementById('noticeBody').value.trim();
    if (!body) {
        alert(NOTICE_I18N.typeFirstAlert);
        document.getElementById('writeModeMyself').checked = true;
        return;
    }
    carolynAsk();
}

function carolynAsk() {
    var box = document.getElementById('carolynSuggestBox');
    var status = document.getElementById('carolynStatus');
    var result = document.getElementById('carolynResult');
    var error = document.getElementById('carolynError');
    box.style.display = 'block';
    status.style.display = 'block';
    status.textContent = NOTICE_I18N.polishingStatus;
    result.style.display = 'none';
    error.style.display = 'none';

    var title = document.getElementById('noticeTitle').value;
    var body = document.getElementById('noticeBody').value;

    fetch(@json($aiAssistUrl), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ title: title, body: body })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        status.style.display = 'none';
        if (data.status !== 'OK') {
            error.style.display = 'block';
            error.textContent = '⚠ ' + (data.message || NOTICE_I18N.errorFallback);
            document.getElementById('writeModeMyself').checked = true;
            return;
        }
        document.getElementById('carolynTitlePreview').textContent = data.title;
        document.getElementById('carolynBodyPreview').textContent = data.body;
        result.style.display = 'block';
    })
    .catch(function() {
        status.style.display = 'none';
        error.style.display = 'block';
        error.textContent = '⚠ ' + NOTICE_I18N.connectionError;
        document.getElementById('writeModeMyself').checked = true;
    });
}

function carolynAccept() {
    document.getElementById('noticeTitle').value = document.getElementById('carolynTitlePreview').textContent;
    document.getElementById('noticeBody').value = document.getElementById('carolynBodyPreview').textContent;
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
    cbeNoticePreviewRefresh();
}

function carolynCancel() {
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
}

function cbeAttachmentTypeChanged(key) {
    var type = document.querySelector('input[name="' + key + '_type"]:checked').value;
    document.getElementById(key + '_link_wrap').style.display = (type === 'LINK') ? 'block' : 'none';
    document.getElementById(key + '_file_wrap').style.display = (type === 'FILE') ? 'block' : 'none';
    cbeNoticePreviewRefresh();
}

var cbeStyleDetectTimer = null;
function cbeNoticePreviewRefresh() {
    var title = document.getElementById('noticeTitle').value;
    var body = document.getElementById('noticeBody').value;
    var styleSelect = document.getElementById('noticeStyleSelect').value;

    document.getElementById('cbeNoticePreviewTitle').textContent = title || @json(__('cbe_records.preview_placeholder_title'));
    document.getElementById('cbeNoticePreviewBody').textContent = body || @json(__('cbe_records.preview_placeholder_body'));

    var attNote = [];
    ['flyer', 'catalog', 'video'].forEach(function(key) {
        var radio = document.querySelector('input[name="' + key + '_type"]:checked');
        if (radio && radio.value !== 'NONE') { attNote.push(key); }
    });
    document.getElementById('cbeNoticePreviewAttachments').textContent = attNote.length ? ('📎 ' + attNote.join(' · ')) : '';

    if (styleSelect !== 'AUTO') {
        cbeApplyPreviewStyle(styleSelect);
        return;
    }

    clearTimeout(cbeStyleDetectTimer);
    cbeStyleDetectTimer = setTimeout(function() {
        fetch(@json($styleDetectUrl), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: JSON.stringify({ title: title, body: body })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.status === 'OK' && data.style) { cbeApplyPreviewStyle(data.style.style_key); }
        })
        .catch(function() {});
    }, 350);
}

function cbeApplyPreviewStyle(styleKey) {
    var s = CBE_NOTICE_STYLES[styleKey] || CBE_NOTICE_STYLES.GENERAL;
    var card = document.getElementById('cbeNoticePreviewCard');
    var pill = document.getElementById('cbeNoticePreviewPill');
    card.style.background = s.bg;
    card.style.border = '1px solid ' + s.accent;
    document.getElementById('cbeNoticePreviewTitle').style.color = s.text;
    document.getElementById('cbeNoticePreviewBody').style.color = s.text;
    document.getElementById('cbeNoticePreviewAttachments').style.color = s.accent;
    pill.style.background = s.accent;
    pill.style.color = s.bg === '#FFFFFF' ? '#FFFFFF' : s.bg;
    pill.textContent = s.label;
}

document.addEventListener('DOMContentLoaded', function() { cbeNoticePreviewRefresh(); });
</script>
