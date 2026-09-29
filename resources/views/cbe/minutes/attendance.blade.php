@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.attendance_page_title'))

@section('content')

{{-- REBUILT 18 Sep 2026 — per Chris: "remove Join Time and Leave Time
     completely... only record Participant Name, Attendance Status,
     Attendance Duration." Organizer picks a status and, for
     Present/Partially Present, types the duration directly — no
     time-of-day entry at all. --}}

<style>
.mma-row{display:flex; align-items:center; gap:10px; padding:6px 8px; border-bottom:1px solid #f3f4f6; font-size:10px;}
.mma-name{flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; color:#263238;}
.mma-select{width:150px; border:1px solid #d1d5db; border-radius:6px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;}
.mma-input{width:80px; border:1px solid #d1d5db; border-radius:6px; padding:4px 6px; font-size:9.5px; box-sizing:border-box;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:6px;">
    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $minute->title }}</div>
        <div style="font-size:9px; color:#6b7280;">{{ \Carbon\Carbon::parse($minute->meeting_date)->format('d M Y') }} &middot; {{ __('cbe_records.attendance_page_subtitle') }}</div>
    </div>

    <form method="POST" action="{{ route('cbe.minutes.attendance.update', $minute->minute_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px;">
        @csrf
        <div style="flex-shrink:0; display:flex; gap:10px; padding:0 8px; font-size:8px; font-weight:700; color:#94A3B8; text-transform:uppercase;">
            <span style="flex:1;">{{ __('cbe_records.field_attendee') }}</span>
            <span style="width:150px;">{{ __('cbe_records.field_attendance_status') }}</span>
            <span style="width:80px;">{{ __('cbe_records.field_duration_minutes') }}</span>
        </div>
        <div style="flex:1; min-height:0; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
            @forelse($attendees as $at)
            <div class="mma-row">
                <input type="hidden" name="attendee_id[]" value="{{ $at->attendee_id }}">
                <span class="mma-name">{{ $at->full_name ?: $at->guest_name }}</span>
                <select class="mma-select" name="status[{{ $at->attendee_id }}]" onchange="mmaToggleDuration(this);">
                    <option value="PRESENT" @selected($at->attendance_status === 'PRESENT')>{{ __('cbe_records.attendance_status_present') }}</option>
                    <option value="PARTIAL" @selected($at->attendance_status === 'PARTIAL')>{{ __('cbe_records.attendance_status_partial') }}</option>
                    <option value="ABSENT" @selected(!$at->attendance_status || $at->attendance_status === 'ABSENT')>{{ __('cbe_records.attendance_status_absent') }}</option>
                </select>
                <input type="number" min="0" max="1440" class="mma-input" name="duration[{{ $at->attendee_id }}]" value="{{ $at->duration_minutes }}" placeholder="{{ __('cbe_records.minutes_unit') }}" {{ (!$at->attendance_status || $at->attendance_status === 'ABSENT') ? 'disabled' : '' }}>
            </div>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9.5px;">{{ __('cbe_records.no_attendees_note') }}</div>
            @endforelse
        </div>
        <div style="flex-shrink:0; display:flex; gap:8px;">
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('cbe_records.save_attendance_button') }}</button>
            <a href="{{ route('cbe.minutes.show', $minute->minute_id) }}" style="background:#f5f6f8; color:#374151; text-decoration:none; border-radius:6px; padding:7px 18px; font-size:10.5px; font-weight:700;">{{ __('cbe_records.back_to_minutes') }}</a>
        </div>
    </form>
</div>

<script>
function mmaToggleDuration(select){
    var row = select.closest('.mma-row');
    var input = row.querySelector('.mma-input');
    if (select.value === 'ABSENT') {
        input.value = '';
        input.disabled = true;
    } else {
        input.disabled = false;
    }
}
</script>
@endsection
