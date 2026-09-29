@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.affiliate_partner_profile_setting_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif

    <div style="flex-shrink:0;">
    </div>

    <div style="font-size:9.5px; color:#9ca3af; flex-shrink:0;">
        {{ __('masterfile.recipient_profile_intro') }}
    </div>

    <form method="GET" action="{{ route('admin.masterfile.override-recipient-profile') }}" style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('masterfile.search_name_code_email_placeholder') }}" style="border:1px solid #d1d5db; border-radius:5px; padding:5px 10px; font-size:11px; width:280px;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
        @if($search)
        <a href="{{ route('admin.masterfile.override-recipient-profile') }}" style="font-size:10.5px; color:#6b7280;">{{ __('masterfile.clear') }}</a>
        @endif
    </form>

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; overflow-y:auto; min-height:0;">
            <table style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead style="position:sticky; top:0; background:#f8fafc; z-index:1;">
                    <tr>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_name') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_agent_code') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_role') }}</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">{{ __('masterfile.col_override_profile') }}</th>
                        <th style="text-align:right; padding:8px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agents as $agent)
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:7px 12px;">{{ $agent->full_name }}</td>
                        <td style="padding:7px 12px; color:#6b7280;">{{ $agent->agent_code }}</td>
                        <td style="padding:7px 12px;">{{ \App\Services\RoleLabelService::label($agent->role, $agent->group_label_id ?? null) }}</td>
                        <td style="padding:7px 12px;">
                            @if(in_array($agent->agent_id, $agentIdsWithOverrides))
                            <span style="background:#e0f2fe; color:#0369a1; font-size:9px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.configured_label') }}</span>
                            @else
                            <span style="background:#f3f4f6; color:#9ca3af; font-size:9px; font-weight:600; padding:2px 8px; border-radius:20px;">{{ __('masterfile.none_yet_label') }}</span>
                            @endif
                        </td>
                        <td style="padding:7px 12px; text-align:right;">
                            <a href="{{ route('admin.masterfile.override-recipient-profile.show', $agent->agent_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.manage_overrides_button') }}</a>
                        </td>
                    </tr>
                    @endforeach
                    @if($agents->isEmpty())
                    <tr><td colspan="5" style="text-align:center; padding:30px; color:#9ca3af;">{{ __('masterfile.no_agents_found') }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; padding:8px 12px; border-top:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
            <div style="font-size:10px; color:#9ca3af;">{{ __('masterfile.agent_count_label', ['count' => $agents->total()]) }}</div>
            <div style="display:flex; gap:6px;">
                @if($agents->onFirstPage())
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</span>
                @else
                <a href="{{ $agents->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.prev') }}</a>
                @endif
                @if($agents->hasMorePages())
                <a href="{{ $agents->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:700;">{{ __('masterfile.next') }}</a>
                @else
                <span style="background:#93c5fd; color:#fff; border-radius:5px; padding:4px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</span>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
