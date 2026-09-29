@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.my_appointments'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:16px; box-sizing:border-box;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('booking.my_appointments') }}</div>
        <a href="{{ route('book-appointment') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 16px; font-size:11.5px; font-weight:600;">{{ __('booking.book_new') }}</a>
    </div>

    @if(session('booking_confirmed'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:8px;">{{ __('booking.booking_confirmed') }}</div>
    @endif
    @if(session('booking_cancelled'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:5px 10px; font-size:11px; margin-bottom:8px;">{{ __('booking.booking_cancelled') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:11.5px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('booking.col_type') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('booking.col_practitioner') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('booking.col_date') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('booking.col_time') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('booking.col_status') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($bookings as $b)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $b->type_label }}</td>
                    <td style="padding:5px 8px; color:#374151;">{{ $b->practitioner_name }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ \Illuminate\Support\Carbon::parse($b->booking_date)->format('d/m/Y') }}</td>
                    <td style="padding:5px 8px; color:#4b5563;">{{ substr($b->start_time, 0, 5) }} - {{ substr($b->end_time, 0, 5) }}</td>
                    <td style="padding:5px 8px;">
                        <span style="padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:600; {{ $b->status === 'CONFIRMED' ? 'background:#e8f5e9;color:#1b5e20;' : 'background:#f3f4f6;color:#6b7280;' }}">{{ $b->status === 'CONFIRMED' ? __('booking.status_confirmed') : __('booking.status_cancelled') }}</span>
                    </td>
                    <td style="padding:5px 8px;">
                        @if($b->status === 'CONFIRMED' && $b->booking_date >= date('Y-m-d'))
                        <form method="POST" action="{{ route('my-appointments.cancel', $b->id) }}" onsubmit="return confirm('{{ __('booking.confirm_cancel') }}');">
                            @csrf
                            <button type="submit" style="background:none; border:none; color:#e53935; font-size:11px; font-weight:600; cursor:pointer; padding:0;">{{ __('booking.cancel_appointment') }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('booking.no_appointments') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
