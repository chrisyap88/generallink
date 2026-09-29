@extends('layouts.dashboard')

@section('page-title', __('growth.my_submissions_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; display:flex; flex-direction:column; align-items:center; box-sizing:border-box;">
    <div style="width:100%; max-width:640px; display:flex; flex-direction:column; height:100%; min-height:0; padding-top:6px;">

        <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
            <div>
                <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.my_submissions_subtitle') }}</div>
            </div>
            <a href="{{ route('content-submission.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; white-space:nowrap;">{{ __('growth.submit_new_button') }}</a>
        </div>

        @if(session('success'))
        <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:8px;">✅ {{ session('success') }}</div>
        @endif

        <div style="background:#fff; border-radius:10px; padding:10px 14px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            @include('partials.my-submissions-list', ['isVendor' => false])
        </div>
    </div>
</div>
@endsection
