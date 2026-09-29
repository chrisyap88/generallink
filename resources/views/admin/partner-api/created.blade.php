@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.pa_created_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; align-items:center; justify-content:center;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:20px; width:100%; max-width:520px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">
            <span style="background:#e8f5e9; color:#1b5e20; border-radius:20px; padding:2px 10px; font-size:9px; font-weight:700;">{{ __('admin_ops.pa_created_badge') }}</span>
            <h4 style="font-weight:700; margin:0; font-size:13px; color:#1565C0;">{{ $keyName }}</h4>
        </div>
        <div style="font-size:10.5px; color:#b71c1c; font-weight:600; margin-bottom:10px;">{{ __('admin_ops.pa_copy_now_warning') }}</div>

        <div style="background:#f8fafc; border:1px dashed #1565C0; border-radius:6px; padding:12px; font-family:monospace; font-size:12px; word-break:break-all; margin-bottom:10px; user-select:all;" id="rawKeyBox">{{ $rawKey }}</div>

        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('rawKeyBox').innerText); this.innerText={{ json_encode(__('admin_ops.pa_copied_text')) }};" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 16px; font-size:11px; font-weight:600; cursor:pointer; margin-bottom:16px;">{{ __('admin_ops.pa_copy_clipboard_button') }}</button>

        <div style="font-size:9.5px; color:#6b7280; margin-bottom:14px;">{!! __('admin_ops.pa_auth_note', ['snippet' => '<span style="font-family:monospace;">Authorization: Bearer '.substr($rawKey, 0, 16).'...</span>']) !!}</div>

        <a href="{{ route('admin.partner-api.index') }}" style="display:block; text-align:center; background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px; font-size:11px; font-weight:600;">{{ __('admin_ops.pa_done_back_button') }}</a>
    </div>

</div>
@endsection
