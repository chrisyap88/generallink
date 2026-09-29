@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.account_groups_page_title'))

@section('content')

{{-- REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
     records by default. Now just a hub with 2 buttons, same pattern as
     Chart of Accounts -- see createAccountGroup() (Add) and
     accountGroupsSearch() (Search/Edit) in CbeAccountingController. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:14px; font-weight:700; color:#263238;">{{ __('cbe_accounting.account_groups_page_title') }}</div>
        <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_coa') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 10px; font-size:10.5px; margin-bottom:10px;">{{ session('success') }}</div>
    @endif

    <div style="flex:1; min-height:0; display:flex; flex-direction:column; align-items:flex-start; gap:14px; margin-top:10px;">
        <div style="font-size:11px; color:#6b7280; text-align:left; max-width:420px;">{{ __('cbe_accounting.account_groups_hub_intro') }}</div>
        <div style="display:flex; gap:12px;">
            <a href="{{ route('cbe.accounting.account-groups.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:8px; padding:12px 28px; font-size:12.5px; font-weight:700;">{{ __('cbe_accounting.add_account_group_button') }}</a>
            <a href="{{ route('cbe.accounting.account-groups.search') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:8px; padding:12px 28px; font-size:12.5px; font-weight:700;">{{ __('cbe_accounting.coa_search_edit_button') }}</a>
        </div>
    </div>
</div>
@endsection
