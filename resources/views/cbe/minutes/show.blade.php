@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.minutes_detail_title'))

@section('content')

{{-- REBUILT 28 Aug 2026 — per Chris: full structured minutes now have a
     meeting type, time, venue, an attendee list, and numbered 1.0/1.1
     content sections on top of the original date/title/summary/
     attachment. Three tabs (Details/Attendees/Content) keep all of that
     fitting one no-scroll screen instead of one long stacked page. --}}

<style>
.mms-tab{padding:6px 14px; font-size:9.5px; font-weight:700; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; user-select:none;}
.mms-tab.active{color:var(--gl-blue); border-bottom-color:var(--gl-blue);}
.mms-field-label{font-size:8px; font-weight:700; color:#94A3B8; text-transform:uppercase;}
.mms-field-value{font-size:10px; color:#263238; font-weight:600; margin-top:2px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:6px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $minute->title }}</div>
        <div style="font-size:9px; color:#6b7280;">
            {{ \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y') }}
            @if($meetingType) &middot; {{ app()->getLocale() === 'zh' && $meetingType->type_name_zh ? $meetingType->type_name_zh : $meetingType->type_name }} @endif
        </div>
    </div>

    <div style="flex-shrink:0; display:flex; gap:6px; border-bottom:1px solid #e5e7eb;">
        <div class="mms-tab active" data-tab="details" onclick="mmsSwitchTab('details')">{{ __('cbe_records.tab_details') }}</div>
        <div class="mms-tab" data-tab="attendees" onclick="mmsSwitchTab('attendees')">{{ __('cbe_records.tab_attendees') }} ({{ $attendees->count() }})</div>
        <div class="mms-tab" data-tab="content" onclick="mmsSwitchTab('content')">{{ __('cbe_records.tab_content') }}</div>
        <div class="mms-tab" data-tab="resolutions" onclick="mmsSwitchTab('resolutions')">{{ __('cbe_records.tab_resolutions') }} ({{ $resolutions->count() }})</div>
    </div>

    {{-- DETAILS --}}
    <div id="mmsPanel-details" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; display:flex; flex-direction:column; gap:10px; box-sizing:border-box;">
        <div style="flex-shrink:0; display:flex; gap:24px; flex-wrap:wrap;">
            <div style="min-width:0;">
                <div class="mms-field-label">{{ __('cbe_records.field_meeting_date') }}</div>
                <div class="mms-field-value">{{ \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y') }}</div>
            </div>
            <div style="min-width:0;">
                <div class="mms-field-label">{{ __('cbe_records.field_meeting_time') }}</div>
                <div class="mms-field-value">{{ $minute->meeting_time ? \Carbon\Carbon::parse($minute->meeting_time)->format('g:i A') : '—' }}</div>
            </div>
            <div style="min-width:0; flex:1;">
                <div class="mms-field-label">{{ __('cbe_records.field_venue') }}</div>
                <div class="mms-field-value" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    @if($minute->meeting_mode === 'ONLINE')
                        {{ __('cbe_records.mode_online') }}@if($minute->meeting_link) &middot; <a href="{{ $minute->meeting_link }}" target="_blank" style="color:var(--gl-blue);">{{ __('cbe_records.meeting_link_label') }}</a>@endif
                    @else
                        {{ $minute->venue ?: '—' }}
                    @endif
                </div>
            </div>
            <div style="min-width:0;">
                <div class="mms-field-label">{{ __('cbe_records.field_attachment') }}</div>
                <div style="font-size:10px; margin-top:2px;">
                    @if($minute->attachment_path)
                    <a href="{{ route('cbe.minutes.download', $minute->minute_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none;">📎 {{ __('cbe_records.view_attachment') }}</a>
                    @else
                    <span style="color:#9ca3af;">{{ __('cbe_records.no_attachment') }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- NEW 17 Sep 2026 — Meeting Notice/Agenda/Quorum, shown only
             for a SCHEDULED (upcoming) meeting — a COMPLETED one already
             has its attendee roll recorded on the Attendees tab instead. --}}
        @if($minute->status === 'SCHEDULED')
        <div style="flex-shrink:0; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px; display:flex; flex-direction:column; gap:6px;">
            @if($minute->quorum_required)
                @php($confirmedGoing = (int) ($rsvpCounts['GOING'] ?? 0))
                <div style="font-size:9.5px; font-weight:700; color:{{ $confirmedGoing >= $minute->quorum_required ? '#2e7d32' : '#c62828' }};">
                    {{ $confirmedGoing >= $minute->quorum_required
                        ? __('cbe_records.quorum_met_note', ['confirmed' => $confirmedGoing, 'required' => $minute->quorum_required])
                        : __('cbe_records.quorum_at_risk_note', ['confirmed' => $confirmedGoing, 'required' => $minute->quorum_required]) }}
                </div>
            @endif
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <span class="mms-field-label" style="margin-right:2px;">{{ __('cbe_records.field_rsvp') }}</span>
                <form method="POST" action="{{ route('cbe.minutes.rsvp', $minute->minute_id) }}" style="display:flex; gap:4px;">
                    @csrf
                    <button type="submit" name="response" value="GOING" style="background:{{ $myRsvp === 'GOING' ? '#2e7d32' : '#f5f6f8' }}; color:{{ $myRsvp === 'GOING' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_going') }}</button>
                    <button type="submit" name="response" value="MAYBE" style="background:{{ $myRsvp === 'MAYBE' ? '#f9a825' : '#f5f6f8' }}; color:{{ $myRsvp === 'MAYBE' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_maybe') }}</button>
                    <button type="submit" name="response" value="NOT_GOING" style="background:{{ $myRsvp === 'NOT_GOING' ? '#c62828' : '#f5f6f8' }}; color:{{ $myRsvp === 'NOT_GOING' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_not_going') }}</button>
                </form>
                @if($canManage)
                <span style="margin-left:auto; display:flex; align-items:center; gap:8px;">
                    @if($minute->notice_sent_at)
                    <span style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_records.notice_already_sent_note', ['date' => \Carbon\Carbon::parse($minute->notice_sent_at)->format('d M Y')]) }}</span>
                    @endif
                    <form method="POST" action="{{ route('cbe.minutes.send-notice', $minute->minute_id) }}" onsubmit="return confirm({{ json_encode(__('cbe_records.send_notice_confirm_js')) }});">
                        @csrf
                        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_records.send_notice_button') }}</button>
                    </form>
                </span>
                @endif
            </div>
        </div>
        @endif

        <div style="flex-shrink:0;" class="mms-field-label">{{ __('cbe_records.field_agenda') }}</div>
        <div style="flex-shrink:0; max-height:60px; overflow-y:auto; font-size:10px; color:#263238; line-height:1.5; white-space:pre-wrap;">
            @if($minute->agenda)
                {{ $minute->agenda }}
            @else
                <span style="color:#9ca3af;">{{ __('cbe_records.no_agenda_note') }}</span>
            @endif
        </div>

        <div style="flex-shrink:0;" class="mms-field-label">{{ __('cbe_records.field_summary') }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto; font-size:10px; color:#263238; line-height:1.6; white-space:pre-wrap;">
            @if($minute->summary)
                {{ $minute->summary }}
            @else
                <span style="color:#9ca3af;">{{ __('cbe_records.no_summary_note') }}</span>
            @endif
        </div>
    </div>

    {{-- ATTENDEES --}}
    <div id="mmsPanel-attendees" style="display:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; flex-direction:column; box-sizing:border-box; gap:6px;">
        @if($canManage)
        <div style="flex-shrink:0; display:flex; justify-content:flex-end; align-items:center; gap:8px; flex-wrap:wrap;">
            @if($minute->checkin_opened_at)
            <span style="font-size:8px; color:#9ca3af;">{{ __('cbe_records.checkin_opened_note_short', ['time' => \Carbon\Carbon::parse($minute->checkin_opened_at)->format('g:i A')]) }}</span>
            @endif
            <form method="POST" action="{{ route('cbe.minutes.checkin.start', $minute->minute_id) }}" onsubmit="return confirm({{ json_encode(__('cbe_records.checkin_start_confirm_js')) }});">
                @csrf
                <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_records.start_checkin_button') }}</button>
            </form>
            <a href="{{ route('cbe.minutes.attendance', $minute->minute_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:5px 12px; font-size:9px; font-weight:700;">{{ __('cbe_records.record_attendance_button') }}</a>
        </div>
        @endif
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($attendees as $at)
            @php($statusColor = $at->attendance_status === 'PRESENT' ? '#2e7d32' : ($at->attendance_status === 'PARTIAL' ? '#f9a825' : '#c62828'))
            <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 8px; border-bottom:1px solid #f3f4f6; font-size:10px; gap:8px;">
                <span style="font-weight:600; color:#263238; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $at->full_name ?: $at->guest_name }} @if(!$at->full_name) <span style="color:#94A3B8; font-weight:400; font-size:8.5px;">({{ __('cbe_records.guest_label') }})</span> @endif</span>
                <span style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    @if($at->duration_minutes !== null)
                    <span style="color:#6b7280; font-size:8.5px;">{{ __('cbe_records.duration_label') }}: {{ $at->duration_minutes }} {{ __('cbe_records.minutes_unit') }}</span>
                    @endif
                    <span style="font-weight:700; color:{{ $statusColor }};">{{ __('cbe_records.attendance_status_' . strtolower($at->attendance_status)) }}</span>
                </span>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9.5px;">{{ __('cbe_records.no_attendees_note') }}</div>
            @endforelse
        </div>
    </div>

    {{-- CONTENT --}}
    <div id="mmsPanel-content" style="display:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; flex-direction:column; box-sizing:border-box; gap:8px;">
        @if($canManage)
        <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;">
            @php($draftColor = $minute->ai_draft_status === 'APPROVED' ? '#2e7d32' : ($minute->ai_draft_status === 'DRAFTED' ? '#f9a825' : '#9ca3af'))
            <span style="font-size:9px; font-weight:700; color:{{ $draftColor }};">{{ __('cbe_records.ai_draft_status_' . strtolower($minute->ai_draft_status)) }}@if($minute->minutes_approved_at) &middot; {{ \Carbon\Carbon::parse($minute->minutes_approved_at)->format('d M Y g:i A') }} @endif</span>
            <div style="display:flex; gap:6px;">
                <a href="{{ route('cbe.minutes.minutes-pdf', $minute->minute_id) }}" style="background:#455A64; color:#fff; text-decoration:none; border-radius:6px; padding:5px 10px; font-size:8.5px; font-weight:700;">{{ __('cbe_records.download_minutes_pdf') }}</a>
                @if($minute->ai_draft_status === 'DRAFTED')
                <form method="POST" action="{{ route('cbe.minutes.approve', $minute->minute_id) }}">
                    @csrf
                    <button type="submit" style="background:#2e7d32; color:#fff; border:none; border-radius:6px; padding:5px 10px; font-size:8.5px; font-weight:700; cursor:pointer;">{{ __('cbe_records.approve_minutes_button') }}</button>
                </form>
                @endif
            </div>
        </div>
        @if($minute->ai_draft_status !== 'APPROVED')
        <div style="flex-shrink:0; border:1px solid #e5e7eb; border-radius:8px; padding:8px 10px;">
            <div class="mms-field-label" style="margin-bottom:4px;">{{ __('cbe_records.ai_draft_upload_label') }}</div>
            <form method="POST" action="{{ route('cbe.minutes.ai-draft', $minute->minute_id) }}" enctype="multipart/form-data" style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                @csrf
                <input type="file" name="transcript" style="font-size:9px;">
                <textarea name="pasted_notes" rows="1" placeholder="{{ __('cbe_records.ai_draft_paste_placeholder') }}" style="flex:1; min-width:160px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:9.5px; box-sizing:border-box; resize:vertical;"></textarea>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:9px; font-weight:700; cursor:pointer;">{{ __('cbe_records.ai_draft_generate_button') }}</button>
            </form>
            @if($minute->transcript_original_name)
            <div style="font-size:8px; color:#9ca3af; margin-top:3px;">{{ __('cbe_records.ai_draft_current_transcript') }}: {{ $minute->transcript_original_name }}</div>
            @endif
        </div>
        @endif
        @endif

        @if($canManage && $minute->ai_draft_status !== 'APPROVED' && count($sections) > 0)
        <form method="POST" action="{{ route('cbe.minutes.sections.update', $minute->minute_id) }}" id="mmsSectionsForm" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:6px;">
            @csrf
            <input type="hidden" name="content_sections_json" id="mmsSectionsJson">
            <div style="flex:1; min-height:0; overflow-y:auto;">
                @foreach($sections as $si => $sec)
                <div style="margin-bottom:8px;">
                    <div style="font-size:10.5px; font-weight:800; color:var(--gl-blue); margin-bottom:3px;">{{ $sec['heading'] ?? '' }}</div>
                    <textarea class="mms-section-text" data-heading="{{ $sec['heading'] ?? '' }}" rows="2" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:9.5px; box-sizing:border-box; resize:vertical;">{{ collect($sec['paragraphs'] ?? [])->pluck('text')->implode("\n") }}</textarea>
                </div>
                @endforeach
            </div>
            <button type="submit" onclick="return mmsSubmitSections();" style="flex-shrink:0; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:9.5px; font-weight:700; cursor:pointer; align-self:flex-start;">{{ __('cbe_records.save_edits_button') }}</button>
        </form>
        @else
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($sections as $sec)
            <div style="margin-bottom:10px;">
                <div style="font-size:11px; font-weight:800; color:var(--gl-blue);">{{ $sec['number'] }} {{ $sec['heading'] ?? '' }}</div>
                @foreach(($sec['paragraphs'] ?? []) as $p)
                <div style="margin-top:4px; padding-left:14px; border-left:2px solid #eef2f7;">
                    <div style="font-size:9px; font-weight:700; color:#6b7280;">{{ $p['number'] }}</div>
                    <div style="font-size:10px; color:#263238; line-height:1.6; white-space:pre-wrap;">{{ $p['text'] }}</div>
                </div>
                @endforeach
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9.5px;">{{ __('cbe_records.no_content_note') }}</div>
            @endforelse
        </div>
        @endif
    </div>

    {{-- RESOLUTIONS --}}
    <div id="mmsPanel-resolutions" style="display:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1; min-height:0; flex-direction:column; box-sizing:border-box; gap:8px;">
        @if($canManage)
        <div style="flex-shrink:0; border:1px solid #e5e7eb; border-radius:8px; padding:10px;">
            <form method="POST" action="{{ route('cbe.minutes.resolutions.store', $minute->minute_id) }}">
                @csrf
                <textarea name="resolution_text" id="resolutionText" maxlength="3000" rows="2" required placeholder="{{ __('cbe_records.resolution_text_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:4px; box-sizing:border-box; resize:vertical;"></textarea>
                @include('partials.carolyn-write-assist', ['carolynBodyId' => 'resolutionText', 'carolynType' => 'cbe_resolution_text'])
                <div style="display:flex; gap:8px; align-items:flex-end; margin-top:8px; flex-wrap:wrap;">
                    <div>
                        <div class="mms-field-label">{{ __('cbe_records.field_votes_for') }}</div>
                        <input type="number" name="votes_for" min="0" value="0" required style="width:70px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div class="mms-field-label">{{ __('cbe_records.field_votes_against') }}</div>
                        <input type="number" name="votes_against" min="0" value="0" required style="width:70px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div class="mms-field-label">{{ __('cbe_records.field_votes_abstain') }}</div>
                        <input type="number" name="votes_abstain" min="0" value="0" required style="width:70px; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                    </div>
                    <div>
                        <div class="mms-field-label">{{ __('cbe_records.field_outcome') }}</div>
                        <select name="outcome" required style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
                            <option value="PASSED">{{ __('cbe_records.outcome_passed') }}</option>
                            <option value="REJECTED">{{ __('cbe_records.outcome_rejected') }}</option>
                            <option value="DEFERRED">{{ __('cbe_records.outcome_deferred') }}</option>
                        </select>
                    </div>
                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.resolution_add_button') }}</button>
                </div>
            </form>
        </div>
        @endif
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($resolutions as $r)
            <div style="padding:8px; border-bottom:1px solid #f3f4f6;">
                <div style="font-size:10px; color:#263238; line-height:1.5;">{{ $r->resolution_text }}</div>
                <div style="display:flex; gap:10px; align-items:center; margin-top:4px; font-size:8.5px; color:#6b7280;">
                    <span>{{ __('cbe_records.field_votes_for') }}: {{ $r->votes_for }}</span>
                    <span>{{ __('cbe_records.field_votes_against') }}: {{ $r->votes_against }}</span>
                    <span>{{ __('cbe_records.field_votes_abstain') }}: {{ $r->votes_abstain }}</span>
                    <span style="font-weight:700; color:{{ $r->outcome === 'PASSED' ? '#2e7d32' : ($r->outcome === 'REJECTED' ? '#c62828' : '#f9a825') }};">{{ __('cbe_records.outcome_' . strtolower($r->outcome)) }}</span>
                </div>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9.5px;">{{ __('cbe_records.no_resolutions_note') }}</div>
            @endforelse
        </div>
    </div>

    <div style="flex-shrink:0; display:flex; gap:8px; align-items:center;">
        <a href="{{ route('cbe.minutes.index') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('cbe_records.back_to_minutes') }}</a>
        <a href="{{ route('cbe.minutes.attendance-report-pdf', $minute->minute_id) }}" style="background:#455A64; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600;">{{ __('cbe_records.download_attendance_pdf') }}</a>
    </div>
</div>

<script>
function mmsSubmitSections(){
    var sections = [];
    document.querySelectorAll('.mms-section-text').forEach(function(ta){
        sections.push({ heading: ta.getAttribute('data-heading'), paragraphs: [{ text: ta.value }] });
    });
    document.getElementById('mmsSectionsJson').value = JSON.stringify(sections);
    return true;
}
function mmsSwitchTab(tab){
    ['details','attendees','content','resolutions'].forEach(function(t){
        document.getElementById('mmsPanel-' + t).style.display = (t === tab) ? 'flex' : 'none';
        document.querySelector('.mms-tab[data-tab="' + t + '"]').classList.toggle('active', t === tab);
    });
}
</script>
@endsection
