@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_practitioners.edit_title'))

@section('hide-topbar', '1')

@section('content')
<div style="height:100vh; overflow-y:auto; padding:3px 16px; box-sizing:border-box;">

    <div style="margin-bottom:4px;">
        <a href="{{ route('admin.practitioners.index', ['node' => $node->node_id]) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('admin_practitioners.back_to_practitioners') }}</a>
    </div>

    <div style="margin-bottom:4px;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $profile->full_name }} <span style="color:#6b7280; font-weight:500; font-size:11px;">({{ $profile->agent_code }})</span></div>
        <div style="font-size:10px; color:#6b7280;">{{ $profile->type_label }} &middot; {{ $node->node_name }}</div>
    </div>

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin-bottom:5px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif
    @if(session('practitioner_saved') || session('practitioner_hours_saved'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:5px;">{{ __('admin_practitioners.saved') }}</div>
    @endif

    {{-- CHANGED 25 Sep 2026 -- per Chris: "all the folder tap must have
         prev and next" -- a small Prev/Next pair now sits beside the
         tab bar so these 3 tabs (Settings / Weekly Hours / Leave &
         Blocked Dates) can be stepped through in order, on top of
         clicking a tab directly. Each tab stays independently
         clickable -- this is a shortcut, not a replacement, so it's
         not the wizard-style "next next prev prev" flow Chris asked
         removed from Group Name & Hierarchy Levels (that one FORCED a
         linear path instead of direct tab clicks). --}}
    <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:2px solid #e5e7eb; margin-bottom:4px;">
        <div style="display:flex; gap:4px;">
            <div id="ppTabBtnSettings" class="gn-tab-btn gn-tab-btn-active" onclick="ppSwitchTab('settings')">{{ __('admin_practitioners.tab_settings') }}</div>
            <div id="ppTabBtnHours" class="gn-tab-btn" onclick="ppSwitchTab('hours')">{{ __('admin_practitioners.tab_weekly_hours') }}</div>
            <div id="ppTabBtnLeave" class="gn-tab-btn" onclick="ppSwitchTab('leave')">{{ __('admin_practitioners.tab_leave_dates') }} ({{ $leaveDates->count() }})</div>
        </div>
        <div style="display:flex; gap:5px; margin-bottom:2px;">
            <span id="ppNavPrev" onclick="ppTabNav(-1)" class="pp-nav-btn">{{ __('masterfile.prev') }}</span>
            <span id="ppNavNext" onclick="ppTabNav(1)" class="pp-nav-btn">{{ __('masterfile.next') }}</span>
        </div>
    </div>
    <style>
        .gn-tab-btn{padding:5px 14px; font-size:10.5px; font-weight:600; color:#6b7280; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px;}
        .gn-tab-btn-active{color:#1565C0; border-bottom-color:#1565C0;}
        .pp-nav-btn{font-size:9px; font-weight:600; color:#1565C0; cursor:pointer; padding:2px 9px; border:1px solid #B2EBF2; border-radius:20px; background:#fff;}
        .pp-nav-disabled{color:#c4c9d0; border-color:#e5e7eb; cursor:default; pointer-events:none;}
    </style>

    <div id="ppPanelSettings">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; max-width:520px;">
            <div style="font-size:9.5px; color:#6b7280; margin-bottom:6px;">{{ __('admin_practitioners.settings_helper') }}</div>
            <form method="POST" action="{{ route('admin.practitioners.update', $profile->id) }}">
                @csrf
                @method('PUT')
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:7px;">
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_slot_duration') }}</label>
                        <input type="number" name="slot_duration_minutes" value="{{ old('slot_duration_minutes', $profile->slot_duration_minutes) }}" min="5" max="480" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_max_per_day') }}</label>
                        <input type="number" name="max_slots_per_day" value="{{ old('max_slots_per_day', $profile->max_slots_per_day) }}" min="1" max="100" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_booking_window') }}</label>
                        <input type="number" name="booking_window_days" value="{{ old('booking_window_days', $profile->booking_window_days) }}" min="1" max="730" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.form_max_upcoming') }}</label>
                        <input type="number" name="max_upcoming_per_member" value="{{ old('max_upcoming_per_member', $profile->max_upcoming_per_member) }}" min="1" max="20" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('masterfile.status') }}</label>
                        <select name="is_active" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; background:#fff; box-sizing:border-box;">
                            <option value="1" {{ $profile->is_active ? 'selected' : '' }}>{{ __('masterfile.active') }}</option>
                            <option value="0" {{ !$profile->is_active ? 'selected' : '' }}>{{ __('masterfile.inactive') }}</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:8px;">
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save') }}</button>
                </div>
            </form>

            <div style="margin-top:10px; border-top:1px solid #f3f4f6; padding-top:8px;">
                <form method="POST" action="{{ route('admin.practitioners.destroy', $profile->id) }}" onsubmit="return confirm('{{ __('admin_practitioners.confirm_remove_practitioner') }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" style="background:none; border:1px solid #e53935; color:#e53935; font-size:11px; font-weight:600; cursor:pointer; padding:5px 14px; border-radius:6px;">{{ __('admin_practitioners.remove') }}</button>
                </form>
            </div>
        </div>
    </div>

    <div id="ppPanelHours" style="display:none;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:6px 10px;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:5px;">{{ __('admin_practitioners.hours_helper') }}</div>

            @php
                // Pre-fill up to 2 blocks per day from whatever is
                // already saved, so re-opening this tab shows the
                // current schedule instead of a blank grid.
                $hoursByDay = [];
                foreach ($weeklyHours as $wh) {
                    $hoursByDay[$wh->day_of_week][] = $wh;
                }
            @endphp

            <form method="POST" action="{{ route('admin.practitioners.hours.update', $profile->id) }}">
                @csrf
                <table style="width:100%; border-collapse:collapse; font-size:11px;">
                    <thead>
                        <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                            <th style="text-align:left; padding:3px 6px; font-size:10px; color:#374151; width:88px;"></th>
                            <th style="text-align:left; padding:3px 6px; font-size:10px; color:#374151;">{{ __('admin_practitioners.block_start_1') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:10px; color:#374151;">{{ __('admin_practitioners.block_end_1') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:10px; color:#374151;">{{ __('admin_practitioners.block_start_2') }}</th>
                            <th style="text-align:left; padding:3px 6px; font-size:10px; color:#374151;">{{ __('admin_practitioners.block_end_2') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($d = 0; $d <= 6; $d++)
                        @php $rowIdx = $d * 2; $b1 = $hoursByDay[$d][0] ?? null; $b2 = $hoursByDay[$d][1] ?? null; @endphp
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:3px 6px; font-weight:600; color:#374151;">{{ __('admin_practitioners.day_'.$d) }}</td>
                            <td style="padding:3px 6px;">
                                <input type="hidden" name="hours[{{ $rowIdx }}][day_of_week]" value="{{ $d }}">
                                <input type="time" name="hours[{{ $rowIdx }}][start_time]" value="{{ $b1?->start_time ? \Illuminate\Support\Carbon::parse($b1->start_time)->format('H:i') : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:11px; box-sizing:border-box;">
                            </td>
                            <td style="padding:3px 6px;">
                                <input type="time" name="hours[{{ $rowIdx }}][end_time]" value="{{ $b1?->end_time ? \Illuminate\Support\Carbon::parse($b1->end_time)->format('H:i') : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:11px; box-sizing:border-box;">
                            </td>
                            <td style="padding:3px 6px;">
                                <input type="hidden" name="hours[{{ $rowIdx + 1 }}][day_of_week]" value="{{ $d }}">
                                <input type="time" name="hours[{{ $rowIdx + 1 }}][start_time]" value="{{ $b2?->start_time ? \Illuminate\Support\Carbon::parse($b2->start_time)->format('H:i') : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:11px; box-sizing:border-box;">
                            </td>
                            <td style="padding:3px 6px;">
                                <input type="time" name="hours[{{ $rowIdx + 1 }}][end_time]" value="{{ $b2?->end_time ? \Illuminate\Support\Carbon::parse($b2->end_time)->format('H:i') : '' }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:3px 6px; font-size:11px; box-sizing:border-box;">
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
                <div style="margin-top:7px;">
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 20px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('admin_practitioners.save_hours') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div id="ppPanelLeave" style="display:none;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; max-width:560px;">
            <div style="font-size:9px; color:#6b7280; margin-bottom:6px;">{{ __('admin_practitioners.leave_helper') }}</div>

            <form method="POST" action="{{ route('admin.practitioners.leave.add', $profile->id) }}">
                @csrf
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <div style="flex:0 0 150px;">
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.leave_date_label') }}</label>
                        <input type="date" name="leave_date" required min="{{ date('Y-m-d') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('admin_practitioners.leave_reason_label') }}</label>
                        <input type="text" name="reason" placeholder="{{ __('admin_practitioners.leave_reason_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:12px; box-sizing:border-box;">
                    </div>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 18px; font-size:12px; font-weight:600; cursor:pointer; height:29px;">{{ __('admin_practitioners.add_leave_date') }}</button>
                </div>
            </form>

            <table style="width:100%; border-collapse:collapse; font-size:11.5px; margin-top:8px;">
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:4px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_leave_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:10.5px; color:#374151;">{{ __('admin_practitioners.col_leave_reason') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:10.5px; color:#374151; width:60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaveDates as $ld)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:4px 8px; font-weight:600; color:#374151;">{{ \Illuminate\Support\Carbon::parse($ld->leave_date)->format('d/m/Y') }}</td>
                        <td style="padding:4px 8px; color:#4b5563;">{{ $ld->reason ?: '—' }}</td>
                        <td style="padding:4px 8px;">
                            <form method="POST" action="{{ route('admin.practitioners.leave.remove', [$profile->id, $ld->id]) }}" onsubmit="return confirm('{{ __('admin_practitioners.confirm_remove_leave') }}');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:none; border:none; color:#e53935; font-size:11px; font-weight:600; cursor:pointer; padding:0;">{{ __('admin_practitioners.remove') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:14px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('admin_practitioners.no_leave_dates') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        var ppTabOrder = ['settings', 'hours', 'leave'];
        var ppCurrentTab = 'settings';

        function ppSwitchTab(tab) {
            ppCurrentTab = tab;
            ppTabOrder.forEach(function (t) {
                var panel = document.getElementById('ppPanel' + t.charAt(0).toUpperCase() + t.slice(1));
                var btn = document.getElementById('ppTabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
                if (panel) panel.style.display = (t === tab) ? 'block' : 'none';
                if (btn) btn.classList.toggle('gn-tab-btn-active', t === tab);
            });
            ppUpdateNav();
        }

        function ppTabNav(dir) {
            var idx = ppTabOrder.indexOf(ppCurrentTab);
            var next = idx + dir;
            if (next < 0 || next >= ppTabOrder.length) return;
            ppSwitchTab(ppTabOrder[next]);
        }

        function ppUpdateNav() {
            var idx = ppTabOrder.indexOf(ppCurrentTab);
            var prevBtn = document.getElementById('ppNavPrev');
            var nextBtn = document.getElementById('ppNavNext');
            if (prevBtn) prevBtn.classList.toggle('pp-nav-disabled', idx === 0);
            if (nextBtn) nextBtn.classList.toggle('pp-nav-disabled', idx === ppTabOrder.length - 1);
        }

        ppUpdateNav();
    </script>

</div>
@endsection
