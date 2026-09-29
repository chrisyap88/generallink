@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.temple_calendar_page_title'))

@section('content')

{{-- NEW 17 Sep 2026 — per Chris: temple Calendar, every member reads,
     only officers/Secretary can add/edit/remove an event (see
     CbeTempleCalendarController). Month-at-a-time list (no scroll) —
     the Prev/Next pair here moves between MONTHS, same wording/style as
     every other Prev/Next pair in the app. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.temple_calendar_page_title') }}</div>
        @if($hasNode && $canManage)
        <a href="{{ route('cbe.temple-calendar.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.temple_calendar_add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <a href="{{ route('cbe.temple-calendar.index', ['month' => $prevMonth]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            <div style="font-size:12px; font-weight:700; color:#263238; min-width:120px; text-align:center;">{{ $monthLabel }}</div>
            <a href="{{ route('cbe.temple-calendar.index', ['month' => $nextMonth]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_event_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_event_title') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_event_type') }}</th>
                        @if($canManage)
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $e)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($e->event_date)->format('d M Y') }}{{ $e->event_time ? ' '.\Carbon\Carbon::parse($e->event_time)->format('g:i A') : '' }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px;" title="{{ $e->description }}">{{ $e->title }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.event_type_'.strtolower($e->event_type)) }}</td>
                        @if($canManage)
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            <a href="{{ route('cbe.temple-calendar.edit', $e->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; margin-right:8px;">{{ __('cbe_records.temple_calendar_edit_button') }}</a>
                            <form method="POST" action="{{ route('cbe.temple-calendar.destroy', $e->event_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.temple_calendar_delete_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; cursor:pointer; font-size:9.5px;">{{ __('cbe_records.temple_calendar_delete_button') }}</button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 4 : 3 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_calendar_events_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
