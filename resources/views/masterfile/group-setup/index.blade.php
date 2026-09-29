@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('group_setup.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); padding:16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:10px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('drilldown.back_to_dashboard_link') }}</a>
    </div>

    <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:4px;">{{ __('group_setup.choose_type_heading') }}</div>
    <div style="font-size:10.5px; color:#6b7280; margin-bottom:16px;">{{ __('group_setup.choose_type_note') }}</div>

    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; flex:1;">

        <a href="{{ route('admin.group-setup.dsg') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:20px 16px; display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px;">
            <div style="width:48px; height:48px; border-radius:50%; background:#e3f2fd; color:#1565C0; display:flex; align-items:center; justify-content:center; font-size:20px;">🧭</div>
            <div style="font-size:12.5px; font-weight:700; color:#1565C0;">{{ __('sidebar.direct_selling_group_item') }}</div>
            <div style="font-size:9.5px; color:#9ca3af;">{{ __('group_setup.dsg_summary') }}</div>
        </a>

        <a href="{{ route('admin.group-setup.org') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:20px 16px; display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px;">
            <div style="width:48px; height:48px; border-radius:50%; background:#e3f2fd; color:#1565C0; display:flex; align-items:center; justify-content:center; font-size:20px;">🏢</div>
            <div style="font-size:12.5px; font-weight:700; color:#1565C0;">{{ __('sidebar.organization_rewards_group_item') }}</div>
            <div style="font-size:9.5px; color:#9ca3af;">{{ __('group_setup.org_summary') }}</div>
        </a>

        <a href="{{ route('admin.group-setup.cbe') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:10px; padding:20px 16px; display:flex; flex-direction:column; align-items:center; text-align:center; gap:8px;">
            <div style="width:48px; height:48px; border-radius:50%; background:#e3f2fd; color:#1565C0; display:flex; align-items:center; justify-content:center; font-size:20px;">🌐</div>
            <div style="font-size:12.5px; font-weight:700; color:#1565C0;">{{ __('sidebar.cbe_group_item') }}</div>
            <div style="font-size:9.5px; color:#9ca3af;">{{ __('group_setup.cbe_summary') }}</div>
        </a>

    </div>
</div>
@endsection
