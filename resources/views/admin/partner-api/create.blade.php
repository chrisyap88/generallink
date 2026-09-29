@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_ops.pa_create_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; align-items:center; justify-content:center;">

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:20px; width:100%; max-width:460px;">
        <div style="font-size:10px; color:#6b7280; margin-bottom:14px;">{{ __('admin_ops.pa_create_intro') }}</div>

        <form method="POST" action="{{ route('admin.partner-api.store') }}">
            @csrf
            <label style="font-size:10.5px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_ops.pa_key_name_label') }}</label>
            <input type="text" name="key_name" placeholder="{{ __('admin_ops.pa_key_name_placeholder') }}" value="{{ old('key_name') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; outline:none; box-sizing:border-box; margin-bottom:4px;">
            @error('key_name') <div style="color:#dc2626; font-size:11px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <label style="font-size:10.5px; font-weight:600; color:#374151; display:block; margin:10px 0 6px;">{{ __('admin_ops.pa_what_can_key_do_label') }}</label>
            @foreach($availableScopes as $scopeKey => $scopeLabel)
            <label style="display:flex; align-items:flex-start; gap:8px; font-size:11px; color:#374151; margin-bottom:6px; cursor:pointer;">
                <input type="checkbox" name="scopes[]" value="{{ $scopeKey }}" checked style="margin-top:2px;">
                <span>{{ $scopeLabel }}</span>
            </label>
            @endforeach
            @error('scopes') <div style="color:#dc2626; font-size:11px; margin-bottom:8px;">{{ $message }}</div> @enderror

            <div style="display:flex; gap:8px; margin-top:16px;">
                <a href="{{ route('admin.partner-api.index') }}" style="flex:1; text-align:center; background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:8px; font-size:11px; font-weight:600;">{{ __('masterfile.cancel') }}</a>
                <button type="submit" style="flex:1; background:#1565C0; color:#fff; border:none; border-radius:6px; padding:8px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('admin_ops.pa_generate_key_button') }}</button>
            </div>
        </form>
    </div>

</div>
@endsection
