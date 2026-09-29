@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $event->event_name)

@section('content')

@php
    $statusColor = ['PLANNING' => '#f9a825', 'IN_PROGRESS' => 'var(--gl-blue)', 'CLOSED' => '#6b7280'][$event->status] ?? '#6b7280';
    $net = (float) $contributionTotals->received - $expenseTotal;
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ $event->event_name }}@if($event->event_name_zh) <span style="color:#6b7280; font-weight:400;">({{ $event->event_name_zh }})</span>@endif</div>
            <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ \Carbon\Carbon::parse($event->event_start_date)->format('d M Y') }}@if($event->event_end_date) &ndash; {{ \Carbon\Carbon::parse($event->event_end_date)->format('d M Y') }}@endif</div>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="background:{{ $statusColor }}22; color:{{ $statusColor }}; border-radius:10px; padding:3px 12px; font-size:10px; font-weight:700;">{{ __('cbe_events.status_' . strtolower($event->status)) }}</span>
            <a href="{{ route('cbe.events.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="flex-shrink:0; display:flex; gap:10px; margin-bottom:10px;">
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;">
            <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.stat_received') }}</div>
            <div style="font-size:15px; font-weight:700; color:#2e7d32;">RM {{ number_format($contributionTotals->received, 2) }}</div>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;">
            <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.stat_pledged') }}</div>
            <div style="font-size:15px; font-weight:700; color:#263238;">RM {{ number_format($contributionTotals->pledged, 2) }}</div>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;">
            <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.stat_expenses') }}</div>
            <div style="font-size:15px; font-weight:700; color:#c62828;">RM {{ number_format($expenseTotal, 2) }}</div>
        </div>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;">
            <div style="font-size:8.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_events.stat_net') }}</div>
            <div style="font-size:15px; font-weight:700; color:{{ $net >= 0 ? '#2e7d32' : '#c62828' }};">RM {{ number_format($net, 2) }}</div>
        </div>
    </div>

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        @if($canManage)
        <a href="{{ route('cbe.contributions.index', $event->event_id) }}" style="flex:1; text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; display:flex; flex-direction:column; justify-content:center;">
            <div style="font-size:20px;">🧾</div>
            <div style="font-size:11.5px; font-weight:700; color:#263238; margin-top:6px;">{{ __('cbe_events.tile_contributions') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_events.tile_contributions_desc') }} ({{ $contributionTotals->cnt }})</div>
        </a>
        <a href="{{ route('cbe.expenses.index', $event->event_id) }}" style="flex:1; text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; display:flex; flex-direction:column; justify-content:center;">
            <div style="font-size:20px;">💸</div>
            <div style="font-size:11.5px; font-weight:700; color:#263238; margin-top:6px;">{{ __('cbe_events.tile_expenses') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_events.tile_expenses_desc') }}</div>
        </a>
        <a href="{{ route('cbe.event-reports.index', $event->event_id) }}" style="flex:1; text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; display:flex; flex-direction:column; justify-content:center;">
            <div style="font-size:20px;">📊</div>
            <div style="font-size:11.5px; font-weight:700; color:#263238; margin-top:6px;">{{ __('cbe_events.tile_reports') }}</div>
            <div style="font-size:9.5px; color:#6b7280; margin-top:2px;">{{ __('cbe_events.tile_reports_desc') }}</div>
        </a>
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; display:flex; flex-direction:column; justify-content:center; align-items:flex-start; gap:8px;">
            @if($event->status === 'PLANNING')
            <form method="POST" action="{{ route('cbe.events.start', $event->event_id) }}" style="width:100%;">
                @csrf
                <button type="submit" style="width:100%; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_events.start_event_button') }}</button>
            </form>
            @endif
            @if($event->status !== 'CLOSED')
            <form method="POST" action="{{ route('cbe.events.close', $event->event_id) }}" style="width:100%;" onsubmit="return confirm({{ json_encode(__('cbe_events.close_confirm_js')) }});">
                @csrf
                <button type="submit" style="width:100%; background:#c62828; color:#fff; border:none; border-radius:6px; padding:8px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_events.close_event_button') }}</button>
            </form>
            <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_events.close_event_hint') }}</div>
            @else
            <div style="font-size:9.5px; color:#6b7280;">{{ __('cbe_events.closed_on_note', ['date' => \Carbon\Carbon::parse($event->closed_at)->format('d M Y')]) }}</div>
            @endif
        </div>
        @endif

        {{-- NEW 17 Sep 2026 — per Chris ("yes build all this for me"):
             Event RSVP/attendance. Every member (not just officers) can
             respond; officers additionally see the response tally. --}}
        <div style="flex:1; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; display:flex; flex-direction:column; justify-content:center; gap:8px; box-sizing:border-box;">
            <div style="font-size:11.5px; font-weight:700; color:#263238;">{{ __('cbe_events.tile_rsvp') }}</div>
            @if($canManage)
            <div style="font-size:9px; color:#6b7280;">
                {{ __('cbe_events.rsvp_going') }}: {{ $rsvpCounts['GOING'] ?? 0 }} &middot;
                {{ __('cbe_events.rsvp_maybe') }}: {{ $rsvpCounts['MAYBE'] ?? 0 }} &middot;
                {{ __('cbe_events.rsvp_not_going') }}: {{ $rsvpCounts['NOT_GOING'] ?? 0 }}
            </div>
            @endif
            <form method="POST" action="{{ route('cbe.events.rsvp', $event->event_id) }}" style="display:flex; gap:6px;">
                @csrf
                <button type="submit" name="response" value="GOING" style="flex:1; background:{{ $myRsvp === 'GOING' ? '#2e7d32' : '#f5f6f8' }}; color:{{ $myRsvp === 'GOING' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:7px 4px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_going') }}</button>
                <button type="submit" name="response" value="MAYBE" style="flex:1; background:{{ $myRsvp === 'MAYBE' ? '#f9a825' : '#f5f6f8' }}; color:{{ $myRsvp === 'MAYBE' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:7px 4px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_maybe') }}</button>
                <button type="submit" name="response" value="NOT_GOING" style="flex:1; background:{{ $myRsvp === 'NOT_GOING' ? '#c62828' : '#f5f6f8' }}; color:{{ $myRsvp === 'NOT_GOING' ? '#fff' : '#374151' }}; border:none; border-radius:6px; padding:7px 4px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('cbe_events.rsvp_not_going') }}</button>
            </form>
            @if($myRsvp)
            <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_events.rsvp_your_response', ['response' => __('cbe_events.rsvp_' . strtolower($myRsvp))]) }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
