@extends($isVendor ? 'layouts.vendor' : 'layouts.dashboard')

@section('page-title', __('offer_requests.create_title'))

@section('content')
@if($isVendor)
<div style="height:100%; display:flex; flex-direction:column; padding:20px 24px; box-sizing:border-box; overflow-y:auto;">
    <h1 style="font-family:'Rajdhani',sans-serif; font-size:18px; font-weight:700; color:#0D5A8E; margin-bottom:2px;">{{ __('offer_requests.vendor_heading') }}</h1>
    <p style="font-size:11px; color:#718096; margin-bottom:14px;">{{ __('offer_requests.vendor_subtitle') }}</p>
    @include('offer-requests._form')
</div>
@else
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; overflow:hidden;">
    <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ route('offer-requests.my') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; white-space:nowrap;">{{ __('offer_requests.my_submissions_link') }}</a>
    </div>
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px 16px; flex:1; min-height:0; overflow-y:auto;">
        @if($errors->any())
        <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:10px;">
            @foreach ($errors->all() as $error) {{ $error }}<br> @endforeach
        </div>
        @endif
        @include('offer-requests._form')
    </div>
</div>
@endif
@endsection
