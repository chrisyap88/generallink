@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.audit_logs_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div>
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.back_to_dashboard') }}</a>
    </div>

    {{-- NEW 10 Aug 2026 — per Chris: a new Admin should be able to trace
         a specific record's full history (who created/changed it, when)
         even after the original Admin who did it has left the company.
         Other screens can deep-link here with ?table_name=X&record_id=Y
         to jump straight to just that one record's trail — e.g. a Video
         Library card links here to show exactly who uploaded that video
         and when. --}}
    @if(request('record_id'))
    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:8px 12px; margin-bottom:8px; font-size:10.5px; color:#1e40af; display:flex; align-items:center; justify-content:space-between;">
        <span>{{ __('masterfile.showing_history_record', ['id' => request('record_id')]) }}</span>
        <a href="{{ route('admin.masterfile.audit-logs', array_merge(request()->except('record_id'))) }}" style="color:#1e40af; font-weight:700; text-decoration:none;">{{ __('masterfile.clear_filter_arrow') }}</a>
    </div>
    @endif

    <form method="GET" action="{{ route('admin.masterfile.audit-logs') }}">
        <input type="hidden" name="record_id" value="{{ request('record_id') }}">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; margin-bottom:8px;">
            <div style="display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr; gap:8px; align-items:end; margin-bottom:8px;">
                <div>
                    <label style="font-size:9px; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.changed_by_name_label') }}</label>
                    <input type="text" name="agent_name" value="{{ request('agent_name') }}" placeholder="{{ __('masterfile.changed_by_name_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.table_label') }}</label>
                    <select name="table_name" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.any_dash_option') }}</option>
                        @foreach($tableNames as $t)
                            <option value="{{ $t }}" {{ request('table_name')==$t?'selected':'' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:9px; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.date_from_label') }}</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="font-size:9px; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.date_to_label') }}</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr auto; gap:8px; align-items:end;">
                <div>
                    <label style="font-size:9px; color:#374151; display:block; margin-bottom:2px;">{{ __('masterfile.col_action') }}</label>
                    <select name="action" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; box-sizing:border-box; background:#fff;">
                        <option value="">{{ __('masterfile.any_dash_option') }}</option>
                        @foreach($actions as $a)
                            <option value="{{ $a }}" {{ request('action')==$a?'selected':'' }}>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
            </div>
        </div>
    </form>

    @if(!$hasAnyFilter)
    <div style="text-align:center; color:#9ca3af; font-size:11px; padding:30px 0;">{{ __('masterfile.use_filters_prompt') }}</div>
    @else

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:11px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_datetime') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_changed_by') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_table') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_reason') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10px; color:#374151;">{{ __('masterfile.col_details') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:4px 8px; color:#9ca3af;">{{ $logs->firstItem() + $loop->index }}</td>
                    <td style="padding:4px 8px; white-space:nowrap;">{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') }}</td>
                    <td style="padding:4px 8px;">{{ $log->changed_by_name ?? '—' }} @if($log->changed_by_code)<span style="color:#9ca3af;">({{ $log->changed_by_code }})</span>@endif</td>
                    <td style="padding:4px 8px;">{{ $log->table_name }}</td>
                    <td style="padding:4px 8px;">
                        <span style="padding:2px 7px; border-radius:20px; font-size:9px; font-weight:600; background:#E0F7FA; color:#1565C0;">{{ $log->action }}</span>
                    </td>
                    <td style="padding:4px 8px;">{{ $log->reason_description ?? '—' }}</td>
                    <td style="padding:4px 8px;">
                        <span onclick='showLogDetails(@json($log->before_value), @json($log->after_value), @json($log->reason_notes), @json($log->changed_by_name), @json($log->created_at), @json($log->reason_description))' style="color:#1B9AE4; cursor:pointer; font-weight:600;">{{ __('masterfile.view') }}</span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding:20px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.no_matching_audit_logs') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs instanceof \Illuminate\Pagination\LengthAwarePaginator && $logs->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($logs->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $logs->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_range_page', ['first' => $logs->firstItem(), 'lastItem' => $logs->lastItem(), 'total' => $logs->total(), 'current' => $logs->currentPage(), 'lastPage' => $logs->lastPage()]) }}</span>
        @if($logs->hasMorePages())
            <a href="{{ $logs->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>

<div id="logModalBackdrop" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.35); z-index:999;" onclick="closeLogModal()"></div>
<div id="logModal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:480px; max-height:80vh; overflow-y:auto; background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 30px rgba(0,0,0,.25); z-index:1000;">
    <div style="font-size:12px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('masterfile.change_details_title') }}</div>
    <div style="font-size:10.5px; color:#4b5563; margin-bottom:10px; padding-bottom:8px; border-bottom:1px solid #f3f4f6;">
        <div><strong>{{ __('masterfile.col_changed_by') }}:</strong> <span id="logChangedBy"></span></div>
        <div><strong>{{ __('masterfile.date_time_label') }}</strong> <span id="logDateTime"></span></div>
    </div>
    <div style="margin-bottom:8px;">
        <div style="font-size:9.5px; font-weight:600; color:#9ca3af; margin-bottom:5px;">{{ __('masterfile.what_changed_label') }}</div>
        <div id="logChanges" style="font-size:11px; color:#374151;"></div>
    </div>
    <div id="logReasonWrap" style="display:none; margin-bottom:10px;">
        <div style="font-size:9.5px; font-weight:600; color:#9ca3af; margin-bottom:3px;">{{ __('masterfile.reason_label_caps') }}</div>
        <div id="logReasonDescription" style="font-size:11px; color:#374151; font-weight:600; margin-bottom:3px;"></div>
        <div id="logReasonNotes" style="font-size:10.5px; color:#6b7280;"></div>
    </div>
    <div onclick="closeLogModal()" style="background:#f3f4f6; color:#374151; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer; display:inline-block;">{{ __('masterfile.close_button') }}</div>
</div>

<script>
// Friendly names for fields — technical/internal fields (tokens,
// encrypted values, timestamps) are hidden entirely since they mean
// nothing useful to a non-technical reader.
var fieldLabels = {
    phone: @json(__('masterfile.phone')), email: @json(__('masterfile.email')), bank_name: @json(__('masterfile.bank_name')), address: @json(__('masterfile.address_label')),
    postcode: @json(__('masterfile.postcode')), city: @json(__('masterfile.city')), state: @json(__('masterfile.state')), status: @json(__('masterfile.status')),
    full_name: @json(__('masterfile.full_name')), role: @json(__('masterfile.role_label'))
};
var hiddenFields = ['password_hash', 'nric_encrypted', 'bank_account_encrypted',
    'pending_email', 'pending_email_token', 'email_verification_token',
    'updated_at', 'created_at', 'updated_by', 'created_by', 'agent_id',
    'qr_code_token', 'security_phrase_hash'];
var alI18n = {
    blank: @json(__('masterfile.blank_placeholder')),
    yes: @json(__('masterfile.yes_label')),
    no: @json(__('masterfile.no_label')),
    wasSetTo: @json(__('masterfile.was_set_to_text')),
    changedFrom: @json(__('masterfile.changed_from_text')),
    to: @json(__('masterfile.to_text')),
    noVisibleChanges: @json(__('masterfile.no_visible_changes_text'))
};

function formatValue(v) {
    if (v === null || v === undefined || v === '') return alI18n.blank;
    if (typeof v === 'boolean') return v ? alI18n.yes : alI18n.no;
    return v;
}

function showLogDetails(before, after, notes, changedBy, dateTime, reasonDesc) {
    document.getElementById('logChangedBy').textContent = changedBy || '—';
    document.getElementById('logDateTime').textContent = dateTime ? new Date(dateTime).toLocaleString('en-MY', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) : '—';

    var beforeObj = before ? JSON.parse(before) : {};
    var afterObj = after ? JSON.parse(after) : {};
    var keys = new Set(Object.keys(beforeObj).concat(Object.keys(afterObj)));
    var lines = [];

    keys.forEach(function(key) {
        if (hiddenFields.indexOf(key) !== -1) return;
        var oldVal = beforeObj[key];
        var newVal = afterObj[key];
        if (JSON.stringify(oldVal) === JSON.stringify(newVal)) return; // unchanged, skip

        var label = fieldLabels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });

        if (oldVal === undefined) {
            lines.push('<div style="margin-bottom:5px;"><strong>' + label + '</strong> ' + alI18n.wasSetTo + ' <span style="color:#1b5e20;">' + formatValue(newVal) + '</span></div>');
        } else {
            lines.push('<div style="margin-bottom:5px;"><strong>' + label + '</strong> ' + alI18n.changedFrom + ' <span style="color:#b71c1c;">' + formatValue(oldVal) + '</span> ' + alI18n.to + ' <span style="color:#1b5e20;">' + formatValue(newVal) + '</span></div>');
        }
    });

    var noChangeMsg = '';
    if (!lines.length) {
        var shownFields = [];
        keys.forEach(function(key) {
            if (hiddenFields.indexOf(key) !== -1) return;
            var label = fieldLabels[key] || key.replace(/_/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
            shownFields.push('<div style="margin-bottom:3px;">' + label + ': ' + formatValue(afterObj[key]) + '</div>');
        });
        noChangeMsg = '<div style="color:#9ca3af; margin-bottom:6px;">' + alI18n.noVisibleChanges + '</div>' + shownFields.join('');
    }
    document.getElementById('logChanges').innerHTML = lines.length ? lines.join('') : noChangeMsg;

    var reasonWrap = document.getElementById('logReasonWrap');
    if (reasonDesc || notes) {
        document.getElementById('logReasonDescription').textContent = reasonDesc || '';
        document.getElementById('logReasonNotes').textContent = notes || '';
        reasonWrap.style.display = 'block';
    } else {
        reasonWrap.style.display = 'none';
    }

    document.getElementById('logModal').style.display = 'block';
    document.getElementById('logModalBackdrop').style.display = 'block';
}
function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
    document.getElementById('logModalBackdrop').style.display = 'none';
}
</script>
@endsection
