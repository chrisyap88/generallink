@extends('layouts.vendor')

@section('page-title', __('vendor.submit_content_title'))

{{-- NEW 10 Aug 2026 — per Chris: "all agents including vendor is allow
     to send attachment...marketing documents...to admin." Vendor side
     of the same feature agents get — see ContentSubmissionController. --}}
@section('content')
<div style="height:calc(100vh - 50px); overflow:hidden; padding:14px 18px; display:flex; flex-direction:column; align-items:center; box-sizing:border-box;">
    <div style="width:100%; max-width:520px; display:flex; flex-direction:column; height:100%; min-height:0;">

        <div style="flex-shrink:0; margin-bottom:10px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
            <div>
                <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('vendor.submit_content_intro') }}</div>
            </div>
            <a href="{{ route('vendor.content-submission.index') }}" style="font-size:10.5px; color:#0D5A8E; font-weight:600; text-decoration:none; white-space:nowrap; padding-top:2px;">{{ __('vendor.my_submissions_link') }}</a>
        </div>

        <div style="background:#fff; border-radius:10px; padding:16px 18px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; overflow-y:auto;">
            @include('partials.content-submission-form', ['isVendor' => true])
        </div>
    </div>
</div>
@endsection
