@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('group_setup.setup_suffix_template', ['type' => __('sidebar.direct_selling_group_item')]))

@section('content')
<div style="height:calc(100vh - 46px); padding:16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:10px;">
        <a href="{{ route('admin.group-setup.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('group_setup.prev_group_type_link') }}</a>
    </div>

    <div style="font-size:13px; font-weight:700; color:#1f2937; margin-bottom:4px;">{{ __('group_setup.programs_heading_template', ['type' => __('sidebar.direct_selling_group_item')]) }}</div>
    <div style="font-size:10.5px; color:#6b7280; margin-bottom:16px;">{{ __('group_setup.dsg_programs_note') }}</div>

    <div style="display:flex; flex-direction:column; gap:8px; max-width:520px;">
        <a href="{{ route('admin.masterfile.group-names', ['type' => 'DSG']) }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">🏷️</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ __('sidebar.group_name') }}</span>
        </a>
        <a href="{{ route('admin.masterfile.group-leaders') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">👥</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</span>
        </a>
        <a href="{{ route('admin.masterfile.team-leaders') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">👤</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</span>
        </a>
        <a href="{{ route('admin.masterfile.introducers') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">🙋</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</span>
        </a>
        <a href="{{ route('admin.masterfile.breakaway-bonus-rules') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">🎁</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ __('sidebar.breakaway_bonus_rules') }}</span>
        </a>
        <a href="{{ route('admin.masterfile.promotion-rules') }}" style="text-decoration:none; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px 14px; display:flex; align-items:center; gap:10px;">
            <span style="font-size:16px;">↕️</span>
            <span style="font-size:11.5px; font-weight:600; color:#1565C0;">{{ __('sidebar.promotion_demotion_rules') }}</span>
        </a>
    </div>
</div>
@endsection
