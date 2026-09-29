@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('notice_board.admin_create_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); overflow:hidden; display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:4px 10px; font-size:10px; color:#b71c1c; margin-bottom:8px; flex-shrink:0;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    {{-- FIXED 8 Aug 2026 — per Chris: "one screen no scroll." Adding the
         Carolyn help-to-write toggle/suggestion box made this form taller
         than one screen on some windows, which was silently scrolling the
         whole page (no overflow:hidden was set anywhere here before).
         Outer wrapper now never scrolls; this card is the bounded
         "safety net" scroll region instead, same pattern already used on
         Contests/Broadcast Campaigns' own forms. --}}
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow-y:auto;">
        <form method="POST" action="{{ route('admin.notice-board.store') }}" enctype="multipart/form-data" style="max-width:560px;">
            @csrf
            <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('notice_board.category_field_label') }} <span style="color:#e53935;">*</span></label>
            <select name="category" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box; background:#fff;">
                <option value="GENERAL">{{ __('notice_board.category_general') }}</option>
                <option value="IMPORTANT_UPDATE">{{ __('notice_board.category_important_update') }}</option>
                <option value="PROMOTION">{{ __('notice_board.category_promotion') }}</option>
                <option value="HOLIDAY_FESTIVE">{{ __('notice_board.category_holiday_festive_greeting') }}</option>
                <option value="CONTACT_INFO">{{ __('notice_board.category_contact_info') }}</option>
            </select>

            <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('notice_board.title_field_label') }} <span style="color:#e53935;">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">

            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; flex-wrap:wrap; gap:4px;">
                <label style="font-size:9px; font-weight:600; color:#374151;">{{ __('notice_board.message_field_label') }} <span style="color:#e53935;">*</span></label>
                {{-- NEW 8 Aug 2026 — "Carolyn help to write": type a rough
                     message first, then pick this radio and Carolyn
                     suggests a polished rewrite (spelling fixed, clearer
                     wording, a couple of emoji added) for you to review
                     and accept — nothing is changed until you click
                     "Use This". --}}
                <div style="display:flex; align-items:center; gap:12px; font-size:9px;">
                    <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#374151;">
                        <input type="radio" name="writeMode" id="writeModeMyself" checked onchange="noticeWriteModeChanged()"> {{ __('notice_board.write_myself_option') }}
                    </label>
                    <label style="display:flex; align-items:center; gap:3px; cursor:pointer; color:#7c3aed; font-weight:600;">
                        <input type="radio" name="writeMode" id="writeModeCarolyn" onchange="noticeWriteModeChanged()"> {{ __('notice_board.carolyn_help_write_option') }}
                    </label>
                </div>
            </div>
            <textarea name="body" id="noticeBody" rows="5" maxlength="3000" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:6px; box-sizing:border-box; resize:none;">{{ old('body') }}</textarea>

            <div id="carolynSuggestBox" style="display:none; background:#f5f3ff; border:1px solid #c4b5fd; border-radius:8px; padding:8px 10px; margin-bottom:10px; font-size:10px;">
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

            <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('notice_board.expires_on_label_create') }}</label>
            <input type="date" name="expires_at" value="{{ old('expires_at') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11px; margin-bottom:10px; box-sizing:border-box;">
            <div style="font-size:8.5px; color:#9ca3af; margin-top:-6px; margin-bottom:10px;">{{ __('notice_board.expires_on_help_note') }}</div>

            <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('notice_board.attachment_label_create') }}</label>
            <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; margin-bottom:14px; box-sizing:border-box; background:#fff;">

            <div style="display:flex; gap:8px; margin-top:4px;">
                <a href="{{ route('admin.notice-board.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:700; display:inline-flex; align-items:center;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('notice_board.post_to_board_button') }}</button>
            </div>
        </form>
    </div>

</div>

@push('scripts')
<script>
var NOTICE_I18N = {
    typeFirstAlert: @json(__('notice_board.carolyn_type_first_alert_js')),
    polishingStatus: @json(__('notice_board.carolyn_polishing_status')),
    errorFallback: @json(__('notice_board.carolyn_error_fallback_js')),
    connectionError: @json(__('notice_board.carolyn_connection_error_js'))
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

    var title = document.querySelector('input[name="title"]').value;
    var body = document.getElementById('noticeBody').value;

    fetch('{{ route('admin.notice-board.ai-assist') }}', {
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
    document.querySelector('input[name="title"]').value = document.getElementById('carolynTitlePreview').textContent;
    document.getElementById('noticeBody').value = document.getElementById('carolynBodyPreview').textContent;
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
}

function carolynCancel() {
    document.getElementById('carolynSuggestBox').style.display = 'none';
    document.getElementById('writeModeMyself').checked = true;
}
</script>
@endpush
@endsection
