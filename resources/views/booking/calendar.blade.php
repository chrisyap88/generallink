@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.select_date'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:16px; box-sizing:border-box;">

    <div style="margin-bottom:8px;">
        <a href="javascript:history.back()" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('booking.back_to_practitioners') }}</a>
    </div>

    <div style="margin-bottom:10px;">
        <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $profile->full_name }}</div>
        <div style="font-size:10px; color:#6b7280;">{{ $profile->type_label }}</div>
    </div>

    @if($upcomingCount > 0)
    <div style="background:#fff8e1; border-left:3px solid #f9a825; border-radius:6px; padding:5px 10px; font-size:11px; color:#8d6e00; margin-bottom:8px;">{{ __('booking.your_upcoming_with_them', ['count' => $upcomingCount]) }}</div>
    @endif

    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; border-radius:6px; padding:5px 10px; font-size:11px; color:#b71c1c; margin-bottom:8px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:0 0 220px;">
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">{{ __('booking.select_date') }}</label>
            <input type="date" id="bkDateInput" min="{{ $minDate }}" max="{{ $maxDate }}" value="{{ $minDate }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:12px; box-sizing:border-box;">
        </div>

        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; flex:1 1 280px; min-width:260px;">
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:5px;">{{ __('booking.available_slots') }}</label>
            <div id="bkSlotsWrap" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(80px, 1fr)); gap:6px;">
                <div id="bkSlotsEmpty" style="grid-column:1/-1; color:#9ca3af; font-size:11px; text-align:center; padding:10px 0;">—</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('book-appointment.book', $profile->id) }}" id="bkBookForm" style="margin-top:12px; max-width:480px; display:none;">
        @csrf
        <input type="hidden" name="date" id="bkFormDate">
        <input type="hidden" name="start_time" id="bkFormStart">
        <input type="hidden" name="end_time" id="bkFormEnd">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px;">
            <div id="bkSelectedSlotLabel" style="font-size:12px; font-weight:600; color:#1565C0; margin-bottom:8px;"></div>
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('booking.notes_label') }}</label>
            <textarea name="notes" id="bookingNotesField" rows="2" placeholder="{{ __('booking.notes_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:12px; box-sizing:border-box; resize:none;"></textarea>
@include('partials.carolyn-write-assist', ['carolynBodyId' => 'bookingNotesField', 'carolynType' => 'booking_note'])
            <button type="submit" style="margin-top:8px; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('booking.confirm_booking') }}</button>
        </div>
    </form>

    <script>
    (function () {
        var dateInput = document.getElementById('bkDateInput');
        var slotsWrap = document.getElementById('bkSlotsWrap');
        var bookForm = document.getElementById('bkBookForm');
        var selectedLabel = document.getElementById('bkSelectedSlotLabel');
        var formDate = document.getElementById('bkFormDate');
        var formStart = document.getElementById('bkFormStart');
        var formEnd = document.getElementById('bkFormEnd');

        var i18n = {
            noSlots: @json(__('booking.no_slots_this_date')),
            leave: @json(__('booking.closed_leave')),
            noHours: @json(__('booking.closed_no_hours')),
        };

        function loadSlots(date) {
            bookForm.style.display = 'none';
            slotsWrap.innerHTML = '<div style="grid-column:1/-1; color:#9ca3af; font-size:11px; text-align:center; padding:10px 0;">…</div>';
            fetch('{{ route('book-appointment.slots', $profile->id) }}?date=' + encodeURIComponent(date))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.slots || !data.slots.length) {
                        var msg = data.reason === 'leave' ? i18n.leave : (data.reason === 'out_of_window' ? i18n.noSlots : i18n.noHours);
                        slotsWrap.innerHTML = '<div style="grid-column:1/-1; color:#9ca3af; font-size:11px; text-align:center; padding:10px 0;">' + msg + '</div>';
                        return;
                    }
                    slotsWrap.innerHTML = '';
                    data.slots.forEach(function (s) {
                        var btn = document.createElement('div');
                        btn.textContent = s.start_time;
                        btn.style.cssText = 'text-align:center; padding:7px 4px; border:1px solid #B2EBF2; border-radius:6px; font-size:11.5px; font-weight:600; color:#1565C0; cursor:pointer; background:#f0f9ff;';
                        btn.onclick = function () {
                            document.querySelectorAll('#bkSlotsWrap > div').forEach(function (el) { el.style.background = '#f0f9ff'; el.style.color = '#1565C0'; });
                            btn.style.background = '#1565C0';
                            btn.style.color = '#fff';
                            formDate.value = date;
                            formStart.value = s.start_time;
                            formEnd.value = s.end_time;
                            selectedLabel.textContent = date + '  ' + s.start_time + ' - ' + s.end_time;
                            bookForm.style.display = 'block';
                        };
                        slotsWrap.appendChild(btn);
                    });
                });
        }

        dateInput.addEventListener('change', function () { loadSlots(this.value); });
        loadSlots(dateInput.value);
    })();
    </script>

</div>
@endsection
