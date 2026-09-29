@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.approvals_title'))

@section('content')
{{-- REBUILT 8 Aug 2026 per Chris: strict no-scroll rule — this screen was
     one long overflow-y:auto page (availability card, Director settings
     card, then 2 unbounded tables stacked). Rebuilt as tabs (same pattern
     as Help Desk/Notification Setup/Document Credit): Awaiting Your
     Decision / My Requests / Settings, each fitting one screen with real
     bottom Prev/Next pagination on the 2 list tabs. The top "← Back to
     Dashboard" link was also removed — per Chris, only the bottom
     Prev/Next pair may navigate; use the sidebar to leave this screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10.5px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:5px 10px; font-size:10.5px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    {{-- My Availability — compact single-line strip, any Admin. --}}
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:6px 10px; margin-bottom:6px; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="font-size:10px; color:#374151; white-space:nowrap;">
            <strong>{{ __('masterfile.my_availability_label') }}</strong>
            <span style="padding:2px 8px; border-radius:20px; font-size:9px; font-weight:600; margin-left:4px;
                {{ $me->availability_status === 'AVAILABLE' ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#fff8e1;color:#92400e;' }}">
                {{ $me->availability_status === 'AVAILABLE' ? __('masterfile.available_label') : __('masterfile.on_leave_until', ['date' => \Carbon\Carbon::parse($me->availability_return_date)->format('d M Y')]) }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.approvals.availability') }}" style="display:flex; align-items:center; gap:6px;" id="availForm">
            @csrf
            <select name="availability_status" id="availStatus" onchange="document.getElementById('returnDateWrap').style.display = this.value === 'ON_LEAVE' ? 'inline-block' : 'none';" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:9.5px;">
                <option value="AVAILABLE" {{ $me->availability_status === 'AVAILABLE' ? 'selected' : '' }}>{{ __('masterfile.available_label') }}</option>
                <option value="ON_LEAVE" {{ $me->availability_status === 'ON_LEAVE' ? 'selected' : '' }}>{{ __('masterfile.on_leave_option') }}</option>
            </select>
            <span id="returnDateWrap" style="display:{{ $me->availability_status === 'ON_LEAVE' ? 'inline-block' : 'none' }};">
                <input type="date" name="availability_return_date" value="{{ $me->availability_return_date }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:9.5px;">
            </span>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
        </form>
    </div>

    <div style="display:flex; align-items:center; gap:3px; margin:0 2px;">
        <button type="button" class="apTabBtn" data-tab="apPending" style="background:#fff; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#1565C0; cursor:pointer; position:relative; top:1px;">{{ __('masterfile.awaiting_decision_tab', ['count' => $pendingForMe->total()]) }}</button>
        <button type="button" class="apTabBtn" data-tab="apMine" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('masterfile.my_requests_tab') }}</button>
        @if($me->department === 'DIRECTOR')
        <button type="button" class="apTabBtn" data-tab="apSettings" style="background:#F7FAFC; border:1px solid #E2E8F0; border-bottom:none; border-radius:6px 6px 0 0; padding:5px 12px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; position:relative; top:1px;">{{ __('masterfile.settings_tab') }}</button>
        @endif
    </div>
</div>

<div style="flex:1 1 auto; min-height:0; padding-bottom:10px;">

    {{-- TAB 1 — Awaiting Your Decision --}}
    <div id="apPending" class="apTabPanel" style="background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; height:100%; box-sizing:border-box; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_requested_by') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_about') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_reason_notes') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_date') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_decision') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pendingForMe as $p)
                    @php
                        $targetAgent = $p->target_agent_id ? \App\Models\Agent::find($p->target_agent_id) : null;
                        $payloadData = json_decode($p->payload, true);
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; word-break:break-word;">{{ $p->requested_by_name }}</td>
                        <td style="padding:5px 8px;">
                            <span style="padding:2px 7px; border-radius:20px; font-size:8.5px; font-weight:600; background:#fff8e1; color:#92400e;">{{ $p->action_type }}</span>
                            @if($p->action_type === 'WITHDRAWAL_APPROVAL' && isset($payloadData['withdrawal_request_id']))
                                <a href="{{ route('admin.finance.withdrawal.show', $payloadData['withdrawal_request_id']) }}" style="color:#1B9AE4; text-decoration:none; font-size:9px; font-weight:600; margin-left:4px;">{{ __('masterfile.view') }}</a>
                            @endif
                        </td>
                        <td style="padding:5px 8px; word-break:break-word;">{{ $targetAgent->full_name ?? '—' }}</td>
                        <td style="padding:5px 8px; font-size:10px; color:#4b5563; word-break:break-word;">{{ $p->reason_description ?? '—' }}@if($p->request_notes) — {{ $p->request_notes }}@endif</td>
                        <td style="padding:5px 8px; white-space:nowrap;">{{ \Carbon\Carbon::parse($p->created_at)->format('d M, h:i A') }}</td>
                        <td style="padding:5px 8px;">
                            <div style="display:flex; gap:6px;">
                                <form method="POST" action="{{ route('approvals.approve', $p->approval_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.approve_confirm')) }});">
                                    @csrf
                                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.approve_button') }}</button>
                                </form>
                                <span onclick="showRejectBox('{{ $p->approval_id }}')" style="background:#f3f4f6; color:#374151; border-radius:5px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.reject_button') }}</span>
                            </div>
                            <div id="rejectBox-{{ $p->approval_id }}" style="display:none; margin-top:4px;">
                                <form method="POST" action="{{ route('approvals.reject', $p->approval_id) }}">
                                    @csrf
                                    <textarea name="notes" placeholder="{{ __('masterfile.reject_reason_placeholder') }}" required rows="2" style="width:150px; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9.5px; margin-bottom:4px; resize:none;"></textarea>
                                    <button type="submit" style="background:#e53935; color:#fff; border:none; border-radius:5px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.confirm_reject_button') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.nothing_awaiting_decision') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($pendingForMe->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $pendingForMe->previousPageUrl() }}#apPending" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('masterfile.page_of_count', ['current' => $pendingForMe->currentPage(), 'last' => $pendingForMe->lastPage(), 'total' => $pendingForMe->total()]) }}</span>
            @if($pendingForMe->hasMorePages())
                <a href="{{ $pendingForMe->nextPageUrl() }}#apPending" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    {{-- TAB 2 — My Requests --}}
    <div id="apMine" class="apTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:8px; height:100%; box-sizing:border-box; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_action') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_decided_by') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_notes') }}</th>
                        <th style="text-align:left; padding:5px 8px; font-size:9px; color:#374151; text-transform:uppercase;">{{ __('masterfile.col_date') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($myRequests as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; word-break:break-word;">{{ $r->action_type }}</td>
                        <td style="padding:5px 8px;">
                            <span style="padding:2px 7px; border-radius:20px; font-size:8.5px; font-weight:600;
                                {{ $r->status === 'APPROVED' ? 'background:#e8f5e9;color:#1b5e20;' : ($r->status === 'REJECTED' ? 'background:#fde8e8;color:#b71c1c;' : 'background:#fff8e1;color:#92400e;') }}">
                                {{ $r->status }}
                            </span>
                        </td>
                        <td style="padding:5px 8px; word-break:break-word;">{{ $r->approved_by_name ?? '—' }}</td>
                        <td style="padding:5px 8px; font-size:10px; color:#4b5563; word-break:break-word;">{{ $r->approval_notes ?? '—' }}</td>
                        <td style="padding:5px 8px; white-space:nowrap;">{{ \Carbon\Carbon::parse($r->created_at)->format('d M, h:i A') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.no_requests_submitted') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($myRequests->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.prev') }}</span>
            @else
                <a href="{{ $myRequests->previousPageUrl() }}#apMine" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('masterfile.page_of_count', ['current' => $myRequests->currentPage(), 'last' => $myRequests->lastPage(), 'total' => $myRequests->total()]) }}</span>
            @if($myRequests->hasMorePages())
                <a href="{{ $myRequests->nextPageUrl() }}#apMine" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
    </div>

    @if($me->department === 'DIRECTOR')
    {{-- TAB 3 — Settings (Director-only) --}}
    <div id="apSettings" class="apTabPanel" style="display:none; background:#fff; border:1px solid #E2E8F0; border-radius:0 8px 8px 8px; padding:10px; height:100%; box-sizing:border-box; overflow:hidden;">
        <div style="font-size:10.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('masterfile.reminder_escalation_settings_title') }}</div>
        <form method="POST" action="{{ route('admin.approvals.settings') }}" style="display:flex; align-items:end; gap:10px;">
            @csrf
            <div>
                <label style="font-size:9px; color:#374151; display:block;">{{ __('masterfile.remind_after_hours_label') }}</label>
                <input type="number" name="reminder_hours" value="{{ $reminderHours }}" min="1" max="720" required style="width:90px; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10.5px;">
            </div>
            <div>
                <label style="font-size:9px; color:#374151; display:block;">{{ __('masterfile.escalate_director_hours_label') }}</label>
                <input type="number" name="escalation_hours" value="{{ $escalationHours }}" min="1" max="720" required style="width:90px; border:1px solid #d1d5db; border-radius:5px; padding:4px 8px; font-size:10.5px;">
            </div>
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_settings_button') }}</button>
        </form>
        <div style="font-size:9px; color:#9ca3af; margin-top:8px;">{{ __('masterfile.escalation_settings_note') }}</div>
    </div>
    @endif

</div>
</div>

<script>
(function() {
    var tabBtns = document.querySelectorAll('.apTabBtn');
    var tabPanels = document.querySelectorAll('.apTabPanel');

    function activateTab(tabId) {
        tabPanels.forEach(function(p) { p.style.display = (p.id === tabId) ? 'flex' : 'none'; });
        tabBtns.forEach(function(b) {
            var active = b.dataset.tab === tabId;
            b.style.background = active ? '#fff' : '#F7FAFC';
            b.style.color = active ? '#1565C0' : '#6b7280';
        });
    }
    tabBtns.forEach(function(b) {
        b.addEventListener('click', function() { activateTab(b.dataset.tab); });
    });

    // Land on whichever tab a Prev/Next link points back to (#apPending / #apMine), else default.
    var hash = window.location.hash.replace('#', '');
    activateTab(hash && document.getElementById(hash) ? hash : 'apPending');
})();

function showRejectBox(id) {
    document.getElementById('rejectBox-' + id).style.display = 'block';
}
</script>
@endsection
