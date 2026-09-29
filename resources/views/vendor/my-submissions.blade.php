@extends('layouts.vendor')

@section('page-title', __('vendor.my_submissions_title'))

@section('content')
<div style="height:calc(100vh - 50px); overflow:hidden; padding:14px 18px; display:flex; flex-direction:column; align-items:center; box-sizing:border-box;">
    <div style="width:100%; max-width:640px; display:flex; flex-direction:column; height:100%; min-height:0;">

        <div style="flex-shrink:0; margin-bottom:10px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
            <div>
                <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('vendor.my_submissions_intro') }}</div>
            </div>
            <a href="{{ route('vendor.content-submission.create') }}" style="background:#0D5A8E; color:#fff; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:11px; font-weight:600; white-space:nowrap;">{{ __('vendor.submit_new_button') }}</a>
        </div>

        <div style="background:#fff; border-radius:10px; padding:10px 14px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
            @include('partials.my-submissions-list', ['isVendor' => true])
        </div>
    </div>
</div>
@endsection
