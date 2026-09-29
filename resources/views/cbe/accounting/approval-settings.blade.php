@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.approval_settings_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.approval_settings_page_title') }}</div>
        <a href="{{ route('cbe.accounting.approvals') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_approvals') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="font-size:9.5px; color:#6b7280; margin-bottom:14px; max-width:520px;">{{ __('cbe_accounting.approval_settings_helper_note') }}</div>

        <form method="POST" action="{{ route('cbe.accounting.approval-settings.store') }}" style="max-width:420px; display:flex; flex-direction:column; gap:14px;">
            @csrf

            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                <input type="checkbox" name="maker_checker_enabled" value="1" {{ $settings->maker_checker_enabled ? 'checked' : '' }} style="width:16px; height:16px;">
                <span style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.field_maker_checker_enabled') }}</span>
            </label>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_threshold_amount') }}</label>
                <input type="number" step="0.01" min="0" name="threshold_amount" value="{{ old('threshold_amount', number_format($settings->threshold_amount, 2, '.', '')) }}" required style="width:180px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                <div style="font-size:8.5px; color:#9ca3af; margin-top:4px;">{{ __('cbe_accounting.threshold_helper_note') }}</div>
            </div>

            <div style="padding-top:6px;">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:7px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
