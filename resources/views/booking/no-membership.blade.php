@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('booking.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); display:flex; align-items:center; justify-content:center; padding:16px; box-sizing:border-box;">
    <div style="text-align:center; color:#6b7280; font-size:12px; max-width:400px;">{{ __('booking.no_membership') }}</div>
</div>
@endsection
