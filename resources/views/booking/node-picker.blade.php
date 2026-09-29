@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.select_your_entity'))

@section('content')
<div style="height:calc(100vh - 46px); padding:16px; box-sizing:border-box;">
    <div style="font-size:13px; font-weight:700; color:#263238; margin-bottom:10px;">{{ __('booking.select_your_entity') }}</div>
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden; max-width:480px;">
        @foreach($nodes as $n)
        <a href="{{ route('book-appointment', ['node' => $n->node_id]) }}" style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; border-bottom:1px solid #f3f4f6; text-decoration:none; color:#263238; font-size:12px;">
            <span>{{ $n->node_name }}</span>
            <span style="color:#1565C0; font-weight:700;">›</span>
        </a>
        @endforeach
    </div>
</div>
@endsection
