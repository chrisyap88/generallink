@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_exec.not_officer_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; align-items:center; justify-content:center; padding:8px 16px; box-sizing:border-box;">
    <div style="max-width:420px; text-align:center; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:24px;">
        <div style="font-size:28px; margin-bottom:10px;">🔒</div>
        <div style="font-size:13px; font-weight:700; color:#263238; margin-bottom:6px;">{{ __('cbe_exec.not_officer_title') }}</div>
        <div style="font-size:10.5px; color:#6b7280; line-height:1.5;">{{ __('cbe_exec.not_officer_body') }}</div>
    </div>
</div>
@endsection
