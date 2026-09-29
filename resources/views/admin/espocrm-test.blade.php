@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', 'EspoCRM Connection Test')

@section('content')

{{-- NEW 28 Jul 2026 — EspoCRM integration (task #251). A simple one-shot
     connection-test screen so Chris can click a button and confirm
     GeneralLink can reach his EspoCRM install and the API key is valid,
     before any real follow-up/calendar feature is built on top of it. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px 0; box-sizing:border-box;">

<div style="flex-shrink:0;">
    <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:9.5px; font-weight:600;">{{ __('masterfile.back_to_dashboard') }}</a>
</div>

<div style="max-width:520px;">
    @if($result['ok'])
        <div style="background:#e8f5e9; border-left:4px solid #38A169; color:#1b5e20; border-radius:6px; padding:12px 16px; font-size:12px; margin-bottom:12px;">
            <strong>✅ {{ $result['message'] }}</strong>
            @if(!empty($result['detail']))
                <div style="margin-top:6px; font-size:10.5px; color:#2e7d32;">{{ __('admin_ops.espo_logged_in_as_label') }} {{ is_string($result['detail']) ? $result['detail'] : json_encode($result['detail']) }}</div>
            @endif
        </div>
    @else
        <div style="background:#fdecea; border-left:4px solid #e53935; color:#b71c1c; border-radius:6px; padding:12px 16px; font-size:12px; margin-bottom:12px;">
            <strong>❌ {{ $result['message'] }}</strong>
            @if(!empty($result['detail']))
                <div style="margin-top:6px; font-size:10.5px; color:#c62828; word-break:break-word;">{{ is_string($result['detail']) ? $result['detail'] : json_encode($result['detail']) }}</div>
            @endif
        </div>
    @endif

    <a href="{{ route('admin.espocrm.test') }}" style="display:inline-block; background:#1565C0; color:#fff; border-radius:8px; padding:6px 16px; font-size:11px; font-weight:600; text-decoration:none;">{{ __('admin_ops.espo_retest_button') }}</a>
</div>

</div>

@endsection
