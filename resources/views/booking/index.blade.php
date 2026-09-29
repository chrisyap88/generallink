@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); padding:16px; box-sizing:border-box;">

    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:10px;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('booking.choose_practitioner_type') }}</div>
            <div style="font-size:9px; color:#6b7280;">{{ $node->node_name }}</div>
        </div>
        <a href="{{ route('my-appointments') }}" style="background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:700; text-decoration:none;">{{ __('booking.my_appointments') }}</a>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px; max-width:820px;">
        @forelse($types as $t)
        <a href="{{ route('book-appointment.practitioners', ['typeId' => $t->id, 'node' => $node->node_id]) }}" style="background:#fff; border:1px solid #d1d5db; border-left:4px solid var(--gl-blue); border-radius:8px; padding:14px; text-decoration:none; color:#263238;">
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $t->type_label }}</div>
            <div style="font-size:10px; color:#6b7280; margin-top:4px;">{{ __('booking.practitioners_available', ['count' => $t->practitioner_count]) }}</div>
        </a>
        @empty
        <div style="grid-column:1/-1; text-align:center; color:#9ca3af; font-size:11.5px; padding:20px;">{{ __('booking.no_practitioner_types') }}</div>
        @endforelse
    </div>

</div>
@endsection
