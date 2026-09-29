@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.choose_practitioner'))

@section('content')
<div style="height:calc(100vh - 46px); padding:16px; box-sizing:border-box;">

    <div style="margin-bottom:8px;">
        <a href="{{ route('book-appointment', ['node' => $node->node_id]) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('booking.back_to_types') }}</a>
    </div>
    <div style="font-size:13px; font-weight:700; color:#263238; margin-bottom:10px;">{{ __('booking.choose_practitioner') }} &mdash; {{ $type->type_label }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden; max-width:480px;">
        @forelse($practitioners as $p)
        <a href="{{ route('book-appointment.calendar', $p->id) }}" style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-bottom:1px solid #f3f4f6; text-decoration:none; color:#263238; font-size:12px;">
            <span style="font-weight:600;">{{ $p->full_name }}</span>
            <span style="color:#1565C0; font-weight:700;">›</span>
        </a>
        @empty
        <div style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('booking.no_practitioners') }}</div>
        @endforelse
    </div>

</div>
@endsection
