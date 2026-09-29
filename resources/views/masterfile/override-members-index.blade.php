@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_maintenance_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <div style="display:flex; gap:8px;">
            <a href="{{ route('admin.masterfile.override-members.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.add_affiliate_partner_heading') }}</a>
        </div>
    </div>

    <div style="font-size:9.5px; color:#9ca3af; flex-shrink:0;">
        {{ __('masterfile.override_members_intro') }}
    </div>

    <form method="GET" action="{{ route('admin.masterfile.override-members') }}" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('masterfile.search_name_code_title_placeholder') }}" style="border:1px solid #d1d5db; border-radius:5px; padding:5px 10px; font-size:11px; width:240px;">
        <select name="vendor_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:11px; background:#fff;">
            <option value="">{{ __('masterfile.all_companies_option') }}</option>
            @foreach($vendors as $v)
            <option value="{{ $v->vendor_id }}" {{ $vendorId === $v->vendor_id ? 'selected' : '' }}>{{ $v->vendor_name }}</option>
            @endforeach
        </select>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
        @if($search || $vendorId)
        <a href="{{ route('admin.masterfile.override-members') }}" style="font-size:10.5px; color:#6b7280;">{{ __('masterfile.clear') }}</a>
        @endif
    </form>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; overflow-y:auto; min-height:0;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead style="position:sticky; top:0; background:#f8fafc; z-index:1;">
                    <tr>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_code') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_name') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_position') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.company_vendor_label') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.organization_rewards_group_label') }}</th>
                        <th style="text-align:center; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_settlement') }}</th>
                        <th style="text-align:center; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.status') }}</th>
                        <th style="text-align:right; padding:8px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $m)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:7px 12px; color:#6b7280;">{{ $m->override_member_code }}</td>
                        <td style="padding:7px 12px; font-weight:600;">{{ $m->full_name }}</td>
                        <td style="padding:7px 12px;">{{ $m->position_title }}</td>
                        <td style="padding:7px 12px;">{{ $m->vendor_name }}</td>
                        <td style="padding:7px 12px;">{{ $m->group_name }}</td>
                        <td style="padding:7px 12px; text-align:center; font-size:9.5px;">{{ $m->settlement_method === 'DEDUCT_FROM_CLAIM' ? __('masterfile.deduct_from_claim') : __('masterfile.claim_back_report') }}</td>
                        <td style="padding:7px 12px; text-align:center;">
                            <span style="background:{{ $m->is_active ? '#d1fae5' : '#f3f4f6' }}; color:{{ $m->is_active ? '#065f46' : '#6b7280' }}; font-size:9px; font-weight:700; padding:3px 9px; border-radius:20px;">{{ $m->is_active ? __('masterfile.active') : __('masterfile.inactive') }}</span>
                        </td>
                        <td style="padding:7px 12px; text-align:right;">
                            <a href="{{ route('admin.masterfile.override-members.show', $m->override_member_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.manage_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                    @if($members->isEmpty())
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#9ca3af;">{{ __('masterfile.no_override_members') }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding:8px 12px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:10px; color:#9ca3af;">{{ __('masterfile.member_count_label', ['count' => $members->total()]) }}</div>
            <div style="display:flex; gap:6px;">
                @if($members->onFirstPage())
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</span>
                @else
                <a href="{{ $members->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev') }}</a>
                @endif
                @if($members->hasMorePages())
                <a href="{{ $members->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next') }}</a>
                @else
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
