@extends('layouts.dashboard')

@section('page-title', __('growth.submit_content_title'))

{{-- NEW 10 Aug 2026 — per Chris: "all agents including vendor is allow
     to send attachment...marketing documents...to admin." Any agent
     role can send video/slideshow/flyer/link straight to Admin for
     review — see ContentSubmissionController. --}}
@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; display:flex; flex-direction:column; align-items:center; box-sizing:border-box;">
    <div style="width:100%; max-width:520px; display:flex; flex-direction:column; height:100%; min-height:0; padding-top:6px;">

        <div style="flex-shrink:0; margin-bottom:8px; display:flex; align-items:flex-start; justify-content:space-between; gap:10px;">
            <div>
                <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.submit_content_subtitle') }}</div>
            </div>
            <a href="{{ route('content-submission.index') }}" style="font-size:10px; color:#1565C0; font-weight:600; text-decoration:none; white-space:nowrap; padding-top:2px;">{{ __('growth.my_submissions_link') }}</a>
        </div>

        @if(session('success'))
        <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:6px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0; margin-bottom:8px;">✅ {{ session('success') }}</div>
        @endif
        @if($errors->any())
        <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:6px 12px; color:#991b1b; font-size:11px; flex-shrink:0; margin-bottom:8px;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
        @endif

        <div style="background:#fff; border-radius:10px; padding:14px 16px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; overflow-y:auto;">
            @include('partials.content-submission-form', ['isVendor' => false])
        </div>
    </div>
</div>
@endsection
