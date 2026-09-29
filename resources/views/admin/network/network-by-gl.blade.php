@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('network.network_dash_name', ['name' => $gl->full_name]))
@section('page-title', __('network.network_drill_down_title'))
@section('content')

<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-size:13px;">
    <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.all_groups_plain') }}</a>
    <span style="color:#718096;">›</span>
    <span style="color:#0D5A8E;font-weight:700;">{{ $gl->full_name }}</span>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px;">
    <div class="metric-card">
        <div class="metric-label">{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</div>
        <div class="metric-value" style="font-size:16px;">{{ $gl->full_name }}</div>
        <div class="metric-sub">{{ $gl->agent_code }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('network.total_role_label', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</div>
        <div class="metric-value">{{ count($tls) }}</div>
        <div class="metric-sub">{{ __('network.under_this_role', ['role' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER')]) }}</div>
    </div>
    <div class="metric-card">
        <div class="metric-label">{{ __('network.total_transactions_label') }}</div>
        <div class="metric-value">{{ $tls->sum('total_transactions') }}</div>
        <div class="metric-sub">{{ __('network.all_role_combined', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER').'s']) }}</div>
    </div>
</div>

<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div class="card-title" style="margin-bottom:0;">
            <i class="ti ti-users" style="color:#0D5A8E"></i> {{ __('network.role_under_name', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER'), 'name' => $gl->full_name]) }}
        </div>
        <a href="{{ route('admin.network') }}" style="background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;border-radius:7px;padding:6px 14px;font-size:12px;text-decoration:none;">{{ __('network.back') }}</a>
    </div>

    @if(count($tls) === 0)
        <div style="text-align:center;color:#A0AEC0;padding:40px 0;font-size:13px;">{{ __('network.no_role_found_under_gl', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</div>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#F7FAFC;">
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_affiliate_code') }}</th>
                    <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ \App\Services\RoleLabelService::plural('INTRODUCER') }}</th>
                    <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_transactions_full') }}</th>
                    <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_commission_rm') }}</th>
                    <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tls as $tl)
                <tr style="border-bottom:1px solid #F7FAFC;cursor:pointer;"
                    onclick="window.location='{{ route('admin.network.tl', [$gl->agent_id, $tl->agent_id]) }}'"
                    onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="padding:10px 12px;font-weight:600;">{{ $tl->full_name }}</td>
                    <td style="padding:10px 12px;color:#718096;font-size:12px;">{{ $tl->agent_code }}</td>
                    <td style="padding:10px 12px;text-align:right;">{{ number_format($tl->member_count) }}</td>
                    <td style="padding:10px 12px;text-align:right;">{{ number_format($tl->total_transactions) }}</td>
                    <td style="padding:10px 12px;text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($tl->total_commission, 2) }}</td>
                    <td style="padding:10px 12px;text-align:center;">
                        <a href="{{ route('admin.network.tl', [$gl->agent_id, $tl->agent_id]) }}"
                           style="background:#1B9AE4;color:#fff;border-radius:6px;padding:4px 12px;font-size:11px;text-decoration:none;font-weight:600;">
                            {{ __('network.view_role_arrow', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
