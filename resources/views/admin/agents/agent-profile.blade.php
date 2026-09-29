@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('agents.title_agent_profile') . ' — ' . $agent->full_name)
@section('page-title', __('agents.title_agent_profile'))

@section('content')

{{-- Breadcrumb --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:14px;">
    <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('agents.network_breadcrumb_link') }}</a>
    <span style="color:#718096;">›</span>
    <span style="color:#0D5A8E;font-weight:700;">{{ $agent->full_name }}</span>
    <a href="javascript:history.back()" style="margin-left:auto;background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;border-radius:8px;padding:6px 16px;font-size:13px;text-decoration:none;">{{ __('network.back') }}</a>
</div>

{{-- Agent Header Card --}}
<div class="card" style="margin-bottom:16px;">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
        {{-- Avatar --}}
        <div style="width:70px;height:70px;border-radius:50%;
            background:linear-gradient(135deg,
            {{ $agent->role === 'GROUP_LEADER' ? '#38A169,#0D5A8E' : ($agent->role === 'TEAM_LEADER' ? '#1B9AE4,#0D5A8E' : '#7C3AED,#4C1D95') }});
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:22px;font-weight:700;flex-shrink:0;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);">
            {{ strtoupper(substr($agent->full_name, 0, 2)) }}
        </div>

        {{-- Name + Role --}}
        <div style="flex:1;">
            <div style="font-size:22px;font-weight:700;color:#0D5A8E;">{{ $agent->full_name }}</div>
            <div style="display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap;">
                <span style="padding:4px 14px;border-radius:20px;font-size:13px;font-weight:700;
                    background:{{ $agent->role === 'GROUP_LEADER' ? '#C8E6C9' : ($agent->role === 'TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                    color:{{ $agent->role === 'GROUP_LEADER' ? '#1B5E20' : ($agent->role === 'TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                    {{ \App\Services\RoleLabelService::label($agent->role) }}
                </span>
                <span style="padding:4px 14px;border-radius:20px;font-size:13px;font-weight:700;
                    background:{{ $agent->status === 'ACTIVE' ? '#C8E6C9' : '#FFCDD2' }};
                    color:{{ $agent->status === 'ACTIVE' ? '#1B5E20' : '#B71C1C' }};">
                    {{ __('network.'.strtolower($agent->status)) }}
                </span>
                <span style="font-size:14px;color:#718096;">{{ $agent->agent_code }}</span>
                <span style="font-size:13px;color:#718096;">{{ __('agents.joined_colon_template', ['date' => \Carbon\Carbon::parse($agent->created_at)->format('d M Y')]) }}</span>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="mailto:{{ $agent->email }}" style="background:#1B9AE4;color:#fff;border-radius:8px;padding:8px 16px;font-size:13px;text-decoration:none;font-weight:600;">
                <i class="ti ti-mail"></i> {{ __('network.email') }}
            </a>
            <a href="tel:{{ $agent->phone }}" style="background:#38A169;color:#fff;border-radius:8px;padding:8px 16px;font-size:13px;text-decoration:none;font-weight:600;">
                <i class="ti ti-phone"></i> {{ __('agents.call_button') }}
            </a>
        </div>
    </div>
</div>

{{-- Performance Metrics --}}
<div style="display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:16px;">
    <div class="metric-card" style="border-left:4px solid #0D5A8E;">
        <div class="metric-label">{{ __('agents.metric_premium_mtd') }}</div>
        <div class="metric-value" style="color:#0D5A8E;">RM {{ number_format($metrics->premium_mtd ?? 0, 2) }}</div>
        <div class="metric-sub">{{ __('agents.metric_sub_this_month') }}</div>
    </div>
    <div class="metric-card" style="border-left:4px solid #1B9AE4;">
        <div class="metric-label">{{ __('agents.metric_premium_ytd') }}</div>
        <div class="metric-value" style="color:#1B9AE4;">RM {{ number_format($metrics->premium_ytd ?? 0, 2) }}</div>
        <div class="metric-sub">{{ __('agents.metric_sub_this_year') }}</div>
    </div>
    <div class="metric-card" style="border-left:4px solid #38A169;">
        <div class="metric-label">{{ __('agents.metric_commission_mtd') }}</div>
        <div class="metric-value" style="color:#38A169;">RM {{ number_format($commission->commission_mtd ?? 0, 2) }}</div>
        <div class="metric-sub">{{ __('agents.metric_sub_this_month') }}</div>
    </div>
    <div class="metric-card" style="border-left:4px solid #7C3AED;">
        <div class="metric-label">{{ __('agents.metric_total_commission') }}</div>
        <div class="metric-value" style="color:#7C3AED;">RM {{ number_format($commission->commission_total ?? 0, 2) }}</div>
        <div class="metric-sub">{{ __('agents.metric_sub_all_time') }}</div>
    </div>
    <div class="metric-card" style="border-left:4px solid #D97706;">
        <div class="metric-label">{{ __('agents.metric_commission_wallet') }}</div>
        <div class="metric-value" style="color:#D97706;">RM {{ number_format($agent->commission_balance ?? 0, 2) }}</div>
        <div class="metric-sub">{{ __('agents.metric_sub_available_balance') }}</div>
    </div>
</div>

{{-- Main Content Grid --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Personal Details --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-user-circle" style="color:#0D5A8E"></i> {{ __('agents.personal_details_heading') }}
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;width:40%;">{{ __('network.full_name') }}</td>
                <td style="padding:10px 0;color:#2D3748;font-weight:500;">{{ $agent->full_name }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('agents.nric_mykad_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ $agent->nric_encrypted }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('network.email') }}</td>
                <td style="padding:10px 0;color:#1B9AE4;">{{ $agent->email }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('agents.mobile_phone_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ $agent->phone }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('masterfile.address_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ $agent->address ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('agents.email_verified_label') }}</td>
                <td style="padding:10px 0;">
                    @if($agent->email_verified_at)
                        <span style="color:#38A169;font-weight:600;">✅ {{ \Carbon\Carbon::parse($agent->email_verified_at)->format('d M Y') }}</span>
                    @else
                        <span style="color:#E53E3E;">❌ {{ __('masterfile.not_verified_value') }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Affiliate & Bank Details --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-id-badge" style="color:#0D5A8E"></i> {{ __('agents.affiliate_bank_details_heading') }}
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:15px;">
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;width:40%;">{{ __('agents.affiliate_code_label') }}</td>
                <td style="padding:10px 0;color:#0D5A8E;font-weight:700;">{{ $agent->agent_code ?? '—' }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('network.role_label') }}</td>
                <td style="padding:10px 0;">
                    <span style="padding:3px 12px;border-radius:20px;font-size:12px;font-weight:700;
                        background:{{ $agent->role === 'GROUP_LEADER' ? '#C8E6C9' : ($agent->role === 'TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                        color:{{ $agent->role === 'GROUP_LEADER' ? '#1B5E20' : ($agent->role === 'TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                        {{ \App\Services\RoleLabelService::label($agent->role) }}
                    </span>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('network.status_field_label') }}</td>
                <td style="padding:10px 0;">
                    <span style="padding:3px 12px;border-radius:20px;font-size:13px;font-weight:600;
                        background:{{ $agent->status === 'ACTIVE' ? '#C8E6C9' : '#FFCDD2' }};
                        color:{{ $agent->status === 'ACTIVE' ? '#1B5E20' : '#B71C1C' }};">
                        {{ __('network.'.strtolower($agent->status)) }}
                    </span>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('agents.upline_label') }}</td>
                <td style="padding:10px 0;">
                    @if($upline)
                        <a href="{{ route('admin.agents.show', $upline->agent_id) }}"
                           style="color:#1B9AE4;text-decoration:none;font-weight:600;">
                            {{ $upline->full_name }}
                        </a>
                        <span style="font-size:12px;color:#718096;margin-left:6px;">{{ $upline->agent_code }}</span>
                    @else
                        <span style="color:#D97706;">{{ __('agents.pending_assignment_label') }}</span>
                    @endif
                </td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('network.bank_name_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ $agent->bank_name ?? '—' }}</td>
            </tr>
            <tr style="border-bottom:1px solid #F7FAFC;">
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('agents.bank_account_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ $agent->bank_account_encrypted ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding:10px 0;color:#718096;font-weight:600;">{{ __('network.joined_label') }}</td>
                <td style="padding:10px 0;color:#2D3748;">{{ \Carbon\Carbon::parse($agent->created_at)->format('d M Y, h:i A') }}</td>
            </tr>
        </table>
    </div>

</div>

{{-- Beneficiaries + Downlines --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Beneficiaries --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-heart" style="color:#E53E3E"></i> {{ __('profile.beneficiaries_heading') }}
        </div>
        @if($beneficiaries->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:24px 0;font-size:14px;">
                <i class="ti ti-heart-off" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                {{ __('agents.no_beneficiary_registered_note') }}
            </div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:#FFF5F5;">
                        <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #FED7D7;color:#742A2A;font-weight:700;">{{ __('agents.name_col') }}</th>
                        <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #FED7D7;color:#742A2A;font-weight:700;">{{ __('agents.relationship_col') }}</th>
                        <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #FED7D7;color:#742A2A;font-weight:700;">{{ __('network.phone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($beneficiaries as $b)
                    <tr style="border-bottom:1px solid #F7FAFC;">
                        <td style="padding:8px 10px;font-weight:600;">{{ $b->full_name }}</td>
                        <td style="padding:8px 10px;color:#718096;">{{ $b->relationship }}</td>
                        <td style="padding:8px 10px;color:#718096;">{{ $b->phone }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Direct Downlines --}}
    <div class="card">
        <div class="card-title">
            <i class="ti ti-hierarchy-2" style="color:#0D5A8E"></i> {{ __('agents.direct_downlines_heading') }}
            <span style="margin-left:auto;font-size:13px;color:#718096;">{{ __('agents.count_total_template', ['count' => $downlines->total()]) }}</span>
        </div>
        @if($downlines->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:24px 0;font-size:14px;">{{ __('agents.no_downlines_yet_note') }}</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:#F7FAFC;">
                        <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('agents.name_col') }}</th>
                        <th style="padding:8px 10px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_code') }}</th>
                        <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.role_label') }}</th>
                        <th style="padding:8px 10px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.status_field_label') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($downlines as $d)
                    <tr style="border-bottom:1px solid #F7FAFC;cursor:pointer;"
                        onclick="window.location='{{ route('admin.agents.show', $d->agent_id) }}'"
                        onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="padding:8px 10px;font-weight:600;">{{ $d->full_name }}</td>
                        <td style="padding:8px 10px;color:#718096;font-size:12px;">{{ $d->agent_code }}</td>
                        <td style="padding:8px 10px;text-align:center;">
                            <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                                background:{{ $d->role === 'GROUP_LEADER' ? '#C8E6C9' : ($d->role === 'TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                                color:{{ $d->role === 'GROUP_LEADER' ? '#1B5E20' : ($d->role === 'TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                                {{ \App\Services\RoleLabelService::label($d->role) }}
                            </span>
                        </td>
                        <td style="padding:8px 10px;text-align:center;">
                            <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                                background:{{ $d->status === 'ACTIVE' ? '#C8E6C9' : '#FFCDD2' }};
                                color:{{ $d->status === 'ACTIVE' ? '#1B5E20' : '#B71C1C' }};">
                                {{ __('network.'.strtolower($d->status)) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($downlines->hasPages())
            <div style="margin-top:12px;">{{ $downlines->links() }}</div>
            @endif
        @endif
    </div>

</div>

{{-- Recent Transactions --}}
<div class="card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
        <div class="card-title" style="margin-bottom:0;">
            <i class="ti ti-file-invoice" style="color:#0D5A8E"></i> {{ __('agents.recent_transactions_heading') }}
        </div>
        <a href="{{ route('admin.network') }}" style="font-size:13px;color:#1B9AE4;text-decoration:none;">{{ __('dashboard.view_all_link') }}</a>
    </div>
    @if($recentTransactions->isEmpty())
        <div style="text-align:center;color:#A0AEC0;padding:24px 0;font-size:14px;">{{ __('agents.no_transactions_yet_note') }}</div>
    @else
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr style="background:#F7FAFC;">
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('gl.col_transaction_no') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_vendor') }}</th>
                    <th style="padding:10px 12px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_product') }}</th>
                    <th style="padding:10px 12px;text-align:right;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('masterfile.col_amount_rm') }}</th>
                    <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_date') }}</th>
                    <th style="padding:10px 12px;text-align:center;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;">{{ __('network.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentTransactions as $tx)
                <tr style="border-bottom:1px solid #F7FAFC;" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="padding:10px 12px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                    <td style="padding:10px 12px;">{{ $tx->vendor_name }}</td>
                    <td style="padding:10px 12px;">{{ $tx->product_name }}</td>
                    <td style="padding:10px 12px;text-align:right;font-weight:700;">RM {{ number_format($tx->premium_amount, 2) }}</td>
                    <td style="padding:10px 12px;text-align:center;color:#718096;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}</td>
                    <td style="padding:10px 12px;text-align:center;">
                        <span style="padding:3px 10px;border-radius:20px;font-size:12px;font-weight:600;
                            background:{{ $tx->status === 'ACTIVE' ? '#C8E6C9' : ($tx->status === 'PENDING' ? '#FFF9C4' : '#FFCDD2') }};
                            color:{{ $tx->status === 'ACTIVE' ? '#1B5E20' : ($tx->status === 'PENDING' ? '#F57F17' : '#B71C1C') }};">
                            {{ __('agents.tx_status_'.strtolower($tx->status)) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

@endsection
