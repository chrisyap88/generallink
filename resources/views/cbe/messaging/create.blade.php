@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.messaging_new_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.messaging_new_button') }}</div>
        <a href="{{ route('cbe.messaging.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.messaging.store') }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:9px; max-width:520px;">
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_message_to') }}</label>
                    <input type="text" id="recipientSearch" autocomplete="off" placeholder="{{ __('cbe_records.field_message_to_placeholder') }}" value="{{ old('recipient_name') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    <input type="hidden" name="recipient_agent_id" id="recipientAgentId" value="{{ old('recipient_agent_id') }}">
                    <div id="recipientResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; max-height:160px; overflow-y:auto; z-index:10; box-shadow:0 4px 10px rgba(0,0,0,.08);"></div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_message_subject') }}</label>
                    <input type="text" name="subject" id="msgSubject" value="{{ old('subject') }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_message_category') }}</label>
                    <select name="category" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; background:#fff;">
                        @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ __('cbe_records.message_category_' . strtolower($cat)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:-3px; flex-wrap:wrap; gap:4px;">
                    <label style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_message_body') }}</label>
                    {{-- NEW 17 Sep 2026 — per Chris: "use back ask carolyn help
                         to rephrase" applies to messaging too, same pattern as
                         the Notice Board. --}}
                    <div style="display:flex; align-items:center; gap:10px; font-size:9px;">
                        <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#546E7A;">
                            <input type="radio" name="writeMode" id="writeModeMyself" checked onchange="msgWriteModeChanged()"> {{ __('notice_board.write_myself_option') }}
                        </label>
                        <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
                            <input type="radio" name="writeMode" id="writeModeCarolyn" onchange="msgWriteModeChanged()"> {{ __('notice_board.carolyn_help_write_option') }}
                        </label>
                    </div>
                </div>
                <div style="flex:1; min-height:0; display:flex; flex-direction:column;">
                    <textarea name="body" id="msgBody" maxlength="3000" required style="width:100%; flex:1; min-height:0; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; resize:none;">{{ old('body') }}</textarea>
                </div>

                <div id="carolynSuggestBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px;">
                    <div id="carolynStatus" style="color:#7c3aed; font-weight:600;">{{ __('notice_board.carolyn_polishing_status') }}</div>
                    <div id="carolynResult" style="display:none;">
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_title_label') }}</div>
                        <div id="carolynTitlePreview" style="font-weight:600; color:#111827; margin-bottom:6px;"></div>
                        <div style="font-size:8.5px; color:#6b7280; margin-bottom:2px;">{{ __('notice_board.suggested_message_label') }}</div>
                        <div id="carolynBodyPreview" style="white-space:pre-wrap; color:#111827; margin-bottom:8px;"></div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" onclick="carolynAccept()" style="background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.use_this_button') }}</button>
                            <button type="button" onclick="carolynAsk()" style="background:#7c3aed; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.try_again_button') }}</button>
                            <button type="button" onclick="carolynCancel()" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('notice_board.cancel_button') }}</button>
                        </div>
                    </div>
                    <div id="carolynError" style="display:none; color:#b71c1c;"></div>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:8px; display:flex; align-items:center; justify-content:space-between; gap:8px; max-width:520px;">
                <div style="font-size:9.5px;">
                    <label style="display:block; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_message_attachment') }}</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="font-size:9.5px;">
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="{{ route('cbe.messaging.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.messaging_send_button') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var input = document.getElementById('recipientSearch');
    var hidden = document.getElementById('recipientAgentId');
    var box = document.getElementById('recipientResults');
    var timer = null;

    input.addEventListener('input', function() {
        hidden.value = '';
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { box.style.display = 'none'; box.innerHTML = ''; return; }
        timer = setTimeout(function() {
            fetch('{{ route("cbe.messaging.recipient-typeahead") }}?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    box.innerHTML = '';
                    if (!rows.length) { box.style.display = 'none'; return; }
                    rows.forEach(function(row) {
                        var item = document.createElement('div');
                        item.textContent = row.full_name;
                        item.style.padding = '6px 10px';
                        item.style.fontSize = '11px';
                        item.style.cursor = 'pointer';
                        item.onmouseover = function() { item.style.background = '#f3f4f6'; };
                        item.onmouseout = function() { item.style.background = '#fff'; };
                        item.onclick = function() {
                            input.value = row.full_name;
                            hidden.value = row.agent_id;
                            box.style.display = 'none';
                        };
                        box.appendChild(item);
                    });
                    box.style.display = 'block';
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input) { box.style.display = 'none'; }
    });
})();

var MSG_I18N = {
    typeFirstAlert: @json(__('notice_board.carolyn_type_first_alert_js')),
    polishingStatus: @json(__('notice_board.carolyn_polishing_status')),
    errorFallback: @json(__('notice_board.carolyn_error_fallback_js')),
    connectionError: @json(__('notice_board.carolyn_connection_error_js'))
};

function msgWriteModeChanged() {
    if (!document.getElementById('writeModeCarolyn').checked) {
        document.getElementById('carolynSuggestBox').style.display = 'none';
        return;
    }
    var body = document.getElementById('msgBody').value.trim();
    if (!body) {
        alert(MSG_I18N.typeFirstAlert);
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
    status.textContent = MSG_I18N.polishingStatus;
    result.style.display = 'none';
    error.style.display = 'none';

    var title = document.getElementById('msgSubject').value;
    var body = document.getElementById('msgBody').value;

    fetch(@json(route('cbe.messaging.ai-assist')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ title: title, body: body })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        status.style.display = 'none';
        if (data.status !== 'OK') {
            error.style.display = 'block';
            error.textContent = '⚠ ' + (data.message || MSG_I18N.errorFallback);
            document.getElementById('writeModeMyself').checked = true;
            return;
        }
        document.getElementById('carolynTitlePreview').textContent = data.title || '';
        document.getElementById('carolynBodyPreview').textContent = data.body;
        result.style.display = 'block';
    })
    .catch(function() {
        status.style.display = 'none';
        error.style.display = 'block';
        error.textContent = '⚠ ' + MSG_I18N.connectionError;
        document.getElementById('writeModeMyself').checked = true;
    });
}

function carolynAccept() {
    if (document.getElementById('carolynTitlePreview').textContent) {
        document.getElementById('msgSubject').value = document.getElementById('carolynTitlePreview').textContent;
    }
    document.getElementById('msgBody').value = document.getElementById('carolynBodyPreview').textContent;
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
}

function carolynCancel() {
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
}
</script>
@endsection
