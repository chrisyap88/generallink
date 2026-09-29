@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $thread->subject)

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:center; gap:8px; max-width:70%; overflow:hidden;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $thread->subject }}">{{ $thread->subject }} — {{ $otherPartyName }}</div>
            @php($statusColors = ['OPEN' => '#607D8B', 'RESPONDED' => '#1565C0', 'OUTSTANDING' => '#F57C00', 'ESCALATED' => '#e53935', 'RESOLVED' => '#2e7d32'])
            <span style="flex-shrink:0; background:{{ $statusColors[$thread->status ?? 'OPEN'] ?? '#607D8B' }}; color:#fff; font-size:8.5px; font-weight:700; padding:2px 8px; border-radius:10px; text-transform:uppercase;">{{ __('cbe_records.message_status_' . strtolower($thread->status ?? 'OPEN')) }}</span>
        </div>
        <a href="{{ route('cbe.messaging.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600; flex-shrink:0;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('whatsapp_warning'))
    <div style="background:#fff8e1; border-left:3px solid #F57C00; color:#7a5200; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('whatsapp_warning') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
            @foreach($messages as $m)
            <div style="background:#f8f9fb; border-radius:8px; padding:8px 10px; max-width:78%; {{ $m->sender_agent_id === auth('agent')->id() ? 'align-self:flex-end; background:#e3f0ff;' : 'align-self:flex-start;' }}">
                <div style="font-size:9px; font-weight:700; color:#546E7A; margin-bottom:2px;">{{ $m->sender_name }} · {{ \Carbon\Carbon::parse($m->created_at)->format('d M Y g:i A') }}</div>
                <div style="font-size:11px; color:#263238; white-space:pre-wrap;">{{ $m->body }}</div>
                @if($m->attachment_path ?? null)
                <div style="margin-top:6px; padding-top:6px; border-top:1px solid rgba(0,0,0,.08);">
                    <a href="{{ route('cbe.messaging.attachment', $m->message_id) }}" target="_blank" style="font-size:10px; color:var(--gl-blue); text-decoration:none; font-weight:600;">📎 {{ $m->attachment_original_name }}</a>
                    @if($m->ai_confidence ?? null)
                    @php($confColors = ['HIGH' => '#2e7d32', 'MEDIUM' => '#F57C00', 'LOW' => '#e53935'])
                    <div style="margin-top:4px; background:#fff; border:1px dashed {{ $confColors[$m->ai_confidence] ?? '#9ca3af' }}; border-radius:6px; padding:5px 8px; font-size:9.5px; color:#37474F;">
                        <span style="font-weight:700; color:#7c3aed;">Carolyn:</span>
                        {{ __('cbe_records.ai_read_amount') }}: <strong>{{ $m->ai_extracted_amount ?? __('cbe_records.ai_read_unknown') }}</strong> ·
                        {{ __('cbe_records.ai_read_payer') }}: <strong>{{ $m->ai_extracted_payer ?? __('cbe_records.ai_read_unknown') }}</strong> ·
                        <span style="color:{{ $confColors[$m->ai_confidence] ?? '#9ca3af' }}; font-weight:700;">{{ __('cbe_records.ai_confidence_' . strtolower($m->ai_confidence)) }}</span>
                    </div>
                    @elseif($m->ai_notes ?? null)
                    <div style="margin-top:4px; font-size:9px; color:#9ca3af;">{{ $m->ai_notes }}</div>
                    @endif

                    @if($canIssueReceipt && $thread->status !== 'RESOLVED')
                    <button type="button" onclick="toggleIssueForm('{{ $m->message_id }}')" style="margin-top:6px; background:#16a34a; color:#fff; border:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.confirm_issue_button') }}</button>
                    <form id="issueForm-{{ $m->message_id }}" method="POST" action="{{ route('cbe.messaging.confirm-issue', $m->message_id) }}" style="display:none; margin-top:6px; background:#fff; border:1px solid #d1d5db; border-radius:6px; padding:8px; font-size:10px;">
                        @csrf
                        <div style="font-weight:700; color:#263238; margin-bottom:5px;">{{ __('cbe_records.confirm_issue_title') }}</div>
                        <label style="display:block; font-size:8.5px; color:#546E7A; margin-bottom:1px;">{{ __('cbe_records.field_payer_name') }}</label>
                        <input type="text" name="payer_name" required value="{{ $m->ai_extracted_payer }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; margin-bottom:5px;">
                        <label style="display:block; font-size:8.5px; color:#546E7A; margin-bottom:1px;">{{ __('cbe_records.field_confirmed_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required value="{{ $m->ai_extracted_amount }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; margin-bottom:5px;">
                        <label style="display:block; font-size:8.5px; color:#546E7A; margin-bottom:1px;">{{ __('cbe_records.field_payer_email') }}</label>
                        <input type="email" name="payer_email" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; margin-bottom:5px;">
                        <label style="display:block; font-size:8.5px; color:#546E7A; margin-bottom:1px;">{{ __('cbe_records.field_payer_whatsapp') }}</label>
                        <input type="text" name="payer_whatsapp" placeholder="{{ __('cbe_records.field_payer_whatsapp_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box; margin-bottom:6px;">
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:5px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('cbe_records.confirm_issue_button') }}</button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
            @endforeach
        </div>
        {{-- NEW 17 Sep 2026 — per Chris: Carolyn help applies to the reply
             box too, same as compose. --}}
        <div id="carolynSuggestBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; font-size:10px; margin-top:8px;">
            <div id="carolynStatus" style="color:#7c3aed; font-weight:600;">{{ __('notice_board.carolyn_polishing_status') }}</div>
            <div id="carolynResult" style="display:none;">
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
        <form method="POST" action="{{ route('cbe.messaging.reply', $thread->thread_id) }}" enctype="multipart/form-data" style="flex-shrink:0; margin-top:8px; display:flex; flex-direction:column; gap:4px;">
            @csrf
            <div style="display:flex; justify-content:flex-end; gap:10px; font-size:9px;">
                <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#546E7A;">
                    <input type="radio" name="writeMode" id="writeModeMyself" checked onchange="msgWriteModeChanged()"> {{ __('notice_board.write_myself_option') }}
                </label>
                <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
                    <input type="radio" name="writeMode" id="writeModeCarolyn" onchange="msgWriteModeChanged()"> {{ __('notice_board.carolyn_help_write_option') }}
                </label>
            </div>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <textarea name="body" id="msgBody" maxlength="3000" required placeholder="{{ __('cbe_records.field_message_reply_placeholder') }}" style="flex:1; resize:none; height:44px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;"></textarea>
                <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="font-size:9px; max-width:110px;">
                <label style="display:flex; align-items:center; gap:3px; font-size:9px; color:#546E7A; cursor:pointer; white-space:nowrap;">
                    <input type="checkbox" name="mark_resolved" value="1"> {{ __('cbe_records.message_mark_resolved') }}
                </label>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:0 18px; height:30px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.messaging_reply_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleIssueForm(id) {
    var f = document.getElementById('issueForm-' + id);
    f.style.display = (f.style.display === 'none') ? 'block' : 'none';
}
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

    var body = document.getElementById('msgBody').value;

    fetch(@json(route('cbe.messaging.ai-assist')), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ body: body })
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
