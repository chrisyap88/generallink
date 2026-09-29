@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('calendar.title'))

@section('content')

{{-- REBUILT 20 Jul 2026 — per Chris: replaced the plain list-by-month
     table with a real month-grid calendar (Google Calendar style) — a
     7-column week grid with day boxes, events shown as small colored
     chips on the day they fall on. Same 3 data sources as before
     (Company Events / My Follow-up Reminders / Policy Renewal
     Reminders), fed by the same CalendarController@index — this is
     purely a different way of drawing the same data, nothing else
     changed underneath. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:10.5px; margin-bottom:6px; flex-shrink:0;">{{ session('success') }}</div>
    @endif

    {{-- CHANGED 8 Aug 2026 per Chris: strict rule — Prev/Next must ONLY
         sit bottom-left/bottom-right; this month switcher used to live up
         here. Moved to the bottom bar below the grid; this row is now
         just the month label, legend, and Add Event. --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; flex-shrink:0;">
        <span style="font-size:13px; font-weight:700; color:var(--gl-blue);">{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</span>
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:9px; color:#9ca3af;">
                <span style="background:var(--gl-light);color:var(--gl-blue);padding:1px 7px;border-radius:20px;font-weight:600;">{{ __('calendar.legend_company_event') }}</span>
                <span style="background:#DBEAFE;color:#1e40af;padding:1px 7px;border-radius:20px;font-weight:600; margin-left:3px;">{{ __('calendar.legend_follow_up') }}</span>
                <span style="background:#EDE9FE;color:#5b21b6;padding:1px 7px;border-radius:20px;font-weight:600; margin-left:3px;">{{ __('calendar.legend_renewal') }}</span>
            </div>
            @if($isAdmin)
            <a href="{{ route('admin.calendar.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:11px; font-weight:600; white-space:nowrap;">{{ __('calendar.add_new_event_button') }}</a>
            @endif
        </div>
    </div>

    @php
        $monthStart = \Carbon\Carbon::parse($month . '-01');
        $daysInMonth = $monthStart->daysInMonth;
        $startWeekday = $monthStart->dayOfWeek; // 0 = Sunday
        $totalCells = (int) ceil(($startWeekday + $daysInMonth) / 7) * 7;
        $today = now()->format('Y-m-d');
        $prevMonthDays = $monthStart->copy()->subMonth()->daysInMonth;

        // Group the already-scoped/already-filtered $events collection
        // by day-of-month, so each grid cell just looks up its own day
        // number instead of re-querying anything.
        $eventsByDay = [];
        foreach ($events as $e) {
            $d = (int) \Carbon\Carbon::parse($e->event_date)->format('j');
            $eventsByDay[$d][] = $e;
        }
    @endphp

    <div style="display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:#E2E8F0; border:1px solid #E2E8F0; border-radius:8px 8px 0 0; overflow:hidden; flex-shrink:0;">
        @foreach([__('calendar.weekday_sun'),__('calendar.weekday_mon'),__('calendar.weekday_tue'),__('calendar.weekday_wed'),__('calendar.weekday_thu'),__('calendar.weekday_fri'),__('calendar.weekday_sat')] as $wd)
        <div style="background:#f0f9ff; text-align:center; padding:5px 0; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; letter-spacing:0.3px;">{{ $wd }}</div>
        @endforeach
    </div>

    {{-- CHANGED 8 Aug 2026 per Chris: strict no-scroll rule — was
         overflow-y:auto. Rows now share the remaining height exactly
         (1fr, no 80px floor) instead of scrolling once 6 rows of 80px
         don't fit. --}}
    <div style="flex:1 1 auto; min-height:0; overflow:hidden; border:1px solid #E2E8F0; border-top:none; border-radius:0 0 8px 8px;">
    <div style="display:grid; grid-template-columns:repeat(7,1fr); grid-auto-rows:1fr; gap:1px; background:#E2E8F0; height:100%;">
        @for($i = 0; $i < $totalCells; $i++)
            @php
                $dayNum = $i - $startWeekday + 1;
                $isTrailing = $dayNum < 1;
                $isLeading = $dayNum > $daysInMonth;
                $displayNum = $isTrailing ? ($prevMonthDays + $dayNum) : ($isLeading ? ($dayNum - $daysInMonth) : $dayNum);
                $cellDateStr = (!$isTrailing && !$isLeading) ? $monthStart->copy()->day($dayNum)->format('Y-m-d') : null;
                $isToday = $cellDateStr === $today;
                $dayEvents = (!$isTrailing && !$isLeading) ? ($eventsByDay[$dayNum] ?? []) : [];
                $cellId = 'cal-day-' . $i;
            @endphp
            <div style="background:{{ $isToday ? '#E6F1FB' : '#fff' }}; padding:4px; overflow:hidden; {{ ($isTrailing || $isLeading) ? 'opacity:0.4;' : '' }}">
                <div style="font-size:{{ $isToday ? '10.5px' : '10px' }}; font-weight:{{ $isToday ? '700' : '600' }}; color:{{ $isToday ? 'var(--gl-blue)' : '#546E7A' }}; margin-bottom:3px;">
                    {{ $displayNum }}
                    @if($isToday)<span style="font-size:8px; font-weight:600; color:var(--gl-blue);"> &bull; {{ __('calendar.today_word') }}</span>@endif
                </div>

                @if(count($dayEvents) > 0)
                <div id="{{ $cellId }}-visible">
                    @foreach(array_slice($dayEvents, 0, 2) as $e)
                        @php
                            $isAdminSource = $e->source === 'ADMIN';
                            $chipHref = $isAdminSource ? ($isAdmin ? route('admin.calendar.edit', $e->event_id) : null) : $e->link;
                        @endphp
                        @if($chipHref)
                        <a href="{{ $chipHref }}" style="display:block; background:{{ $e->type_color[0] }}; color:{{ $e->type_color[1] }}; text-decoration:none; border-radius:4px; padding:1px 5px; font-size:8.5px; font-weight:600; margin-bottom:2px; white-space:normal; word-break:break-word; line-height:1.2;" title="{{ $e->title }}">{{ $e->title }}</a>
                        @else
                        <div style="background:{{ $e->type_color[0] }}; color:{{ $e->type_color[1] }}; border-radius:4px; padding:1px 5px; font-size:8.5px; font-weight:600; margin-bottom:2px; white-space:normal; word-break:break-word; line-height:1.2;" title="{{ $e->title }}">{{ $e->title }}</div>
                        @endif
                    @endforeach
                </div>

                @if(count($dayEvents) > 2)
                <div id="{{ $cellId }}-more" style="display:none;">
                    @foreach(array_slice($dayEvents, 2) as $e)
                        @php
                            $isAdminSource = $e->source === 'ADMIN';
                            $chipHref = $isAdminSource ? ($isAdmin ? route('admin.calendar.edit', $e->event_id) : null) : $e->link;
                        @endphp
                        @if($chipHref)
                        <a href="{{ $chipHref }}" style="display:block; background:{{ $e->type_color[0] }}; color:{{ $e->type_color[1] }}; text-decoration:none; border-radius:4px; padding:1px 5px; font-size:8.5px; font-weight:600; margin-bottom:2px; white-space:normal; word-break:break-word; line-height:1.2;" title="{{ $e->title }}">{{ $e->title }}</a>
                        @else
                        <div style="background:{{ $e->type_color[0] }}; color:{{ $e->type_color[1] }}; border-radius:4px; padding:1px 5px; font-size:8.5px; font-weight:600; margin-bottom:2px; white-space:normal; word-break:break-word; line-height:1.2;" title="{{ $e->title }}">{{ $e->title }}</div>
                        @endif
                    @endforeach
                </div>
                <a href="javascript:void(0)" id="{{ $cellId }}-toggle" onclick="calToggleMore('{{ $cellId }}')" style="display:block; font-size:8px; color:#546E7A; text-decoration:none; font-weight:600;">+{{ count($dayEvents) - 2 }}{{ __('calendar.more_suffix_js') }}</a>
                @endif
                @endif
            </div>
        @endfor
    </div>
    </div>

    {{-- NEW 8 Aug 2026 per Chris: strict rule — month switcher moved down
         here, bottom-left/bottom-right, blue-filled, matching every other
         screen's Prev/Next. --}}
    <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
        <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($month . '-01')->subMonth()->format('Y-m')]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('network.prev') }}</a>
        <a href="{{ route('calendar.index', ['month' => \Carbon\Carbon::parse($month . '-01')->addMonth()->format('Y-m')]) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('network.next') }}</a>
    </div>

</div>

<script>
function calToggleMore(cellId) {
    var more = document.getElementById(cellId + '-more');
    var toggle = document.getElementById(cellId + '-toggle');
    if (!more || !toggle) return;
    if (!toggle.dataset.label) toggle.dataset.label = toggle.textContent;
    var showing = more.style.display === 'block';
    more.style.display = showing ? 'none' : 'block';
    toggle.textContent = showing ? toggle.dataset.label : @json(__('calendar.show_less_js'));
}
</script>

@endsection
