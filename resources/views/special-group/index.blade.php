@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('sidebar.organization_rewards_groups'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:4px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('error') }}</div>
    @endif

    <div style="margin-bottom:6px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('network.back_dashboard') }}</a>
    </div>

    {{-- CLEAN LANDING — just the two options, same pattern as Group Name
         Maintenance, no table --}}
    <div style="display:flex; gap:10px;">
        <a href="{{ route('admin.special-group.create-gl') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.add_new', ['role' => \App\Services\RoleLabelService::label('GROUP_LEADER')]) }}</a>
        <a href="{{ route('admin.special-group.search') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

</div>
@endsection