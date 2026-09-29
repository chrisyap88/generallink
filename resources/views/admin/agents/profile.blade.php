@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('title', __('agents.title_agent_profile') . ' — ' . $agent->full_name)
@section('page-title', __('agents.title_agent_profile'))

@section('content')

{{-- Breadcrumb --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;font-size:13px;">
    <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('agents.network_breadcrumb_link') }}</a>
    <span style="color:#718096;">›</span>
    <span style="color:#0D5A8E;font-weight:700;">{{ $agent->full_name }}</span>
    <a href="javascript:history.back()" style="margin-left:auto;background:#F7FAFC;border:1px solid #E2E8F0;color:#4A5568;border-radius:7px;padding:5px 14px;font-size:12px;text-decoration:none;">{{ __('network.back') }}</a>
</div>

{{-- TOP ROW: Header + 6 Metrics --}}
<div style="display:grid;grid-template-columns:320px 1fr;gap:8px;margin-bottom:8px;">

    {{-- Agent Header --}}
    <div class="card" style="padding:10px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
            <div style="width:54px;height:54px;border-radius:50%;
                background:linear-gradient(135deg,{{ $agent->role==='GROUP_LEADER' ? '#38A169,#0D5A8E' : ($agent->role==='TEAM_LEADER' ? '#1B9AE4,#0D5A8E' : '#7C3AED,#4C1D95') }});
                display:flex;align-items:center;justify-content:center;
                color:#fff;font-size:18px;font-weight:700;flex-shrink:0;">
                {{ strtoupper(substr($agent->full_name,0,2)) }}
            </div>
            <div>
                <div style="font-size:17px;font-weight:700;color:#0D5A8E;">{{ $agent->full_name }}</div>
                <div style="font-size:12px;color:#718096;">{{ $agent->agent_code }} · {{ __('network.joined_label') }} {{ \Carbon\Carbon::parse($agent->created_at)->format('d M Y') }}</div>
            </div>
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                background:{{ $agent->role==='GROUP_LEADER' ? '#C8E6C9' : ($agent->role==='TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                color:{{ $agent->role==='GROUP_LEADER' ? '#1B5E20' : ($agent->role==='TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                {{ \App\Services\RoleLabelService::label($agent->role) }}
            </span>
            <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;
                background:{{ $agent->status==='ACTIVE' ? '#C8E6C9' : '#FFCDD2' }};
                color:{{ $agent->status==='ACTIVE' ? '#1B5E20' : '#B71C1C' }};">
                {{ __('network.'.strtolower($agent->status)) }}
            </span>
        </div>
        <div style="display:flex;gap:6px;">
            <a href="mailto:{{ $agent->email }}" style="flex:1;background:#1B9AE4;color:#fff;border-radius:6px;padding:6px;font-size:12px;text-decoration:none;font-weight:600;text-align:center;"><i class="ti ti-mail"></i> {{ __('network.email') }}</a>
            <a href="tel:{{ $agent->phone }}" style="flex:1;background:#38A169;color:#fff;border-radius:6px;padding:6px;font-size:12px;text-decoration:none;font-weight:600;text-align:center;"><i class="ti ti-phone"></i> {{ __('agents.call_button') }}</a>
        </div>
        {{-- NEW 5 Aug 2026 — Admin-assist path for a stuck Integration Hub
             vault. Only shown once this agent has actually set one up.
             Admin still can never read what was in it — this only clears
             the lock so the agent can start fresh. --}}
        @if($hasHubVault)
        <form method="POST" action="{{ route('admin.agents.clear-hub-vault', $agent->agent_id) }}" onsubmit="return confirm({{ json_encode(__('agents.clear_hub_vault_confirm')) }});" style="margin-top:6px;">
            @csrf
            <button type="submit" style="width:100%;background:#fff;color:#b71c1c;border:1px solid #f3d4d4;border-radius:6px;padding:6px;font-size:11px;font-weight:600;cursor:pointer;"><i class="ti ti-lock-open"></i> {{ __('agents.clear_hub_vault_button') }}</button>
        </form>
        @endif
    </div>

    {{-- 6 Metrics in 3x2 grid --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);grid-template-rows:repeat(2,1fr);gap:8px;">
        <div class="metric-card" style="border-left:4px solid #0D5A8E;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_premium_mtd') }}</div>
            <div class="metric-value" style="font-size:18px;color:#0D5A8E;">RM {{ number_format($metrics->premium_mtd ?? 0,2) }}</div>
        </div>
        <div class="metric-card" style="border-left:4px solid #1B9AE4;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_premium_ytd') }}</div>
            <div class="metric-value" style="font-size:18px;color:#1B9AE4;">RM {{ number_format($metrics->premium_ytd ?? 0,2) }}</div>
        </div>
        <div class="metric-card" style="border-left:4px solid #718096;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_total_transactions') }}</div>
            <div class="metric-value" style="font-size:18px;color:#718096;">{{ number_format($metrics->transactions_total ?? 0) }}</div>
        </div>
        <div class="metric-card" style="border-left:4px solid #38A169;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_earning_income_mtd') }}</div>
            <div class="metric-value" style="font-size:18px;color:#38A169;">RM {{ number_format($commission->commission_mtd ?? 0,2) }}</div>
        </div>
        <div class="metric-card" style="border-left:4px solid #7C3AED;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_total_earning_income') }}</div>
            <div class="metric-value" style="font-size:18px;color:#7C3AED;">RM {{ number_format($commission->commission_total ?? 0,2) }}</div>
        </div>
        <div class="metric-card" style="border-left:4px solid #D97706;padding:8px 12px;">
            <div class="metric-label" style="font-size:11px;">{{ __('agents.metric_earning_income_wallet') }}</div>
            <div class="metric-value" style="font-size:18px;color:#D97706;">RM {{ number_format($agent->commission_balance ?? 0,2) }}</div>
        </div>
    </div>

</div>

{{-- MIDDLE ROW: Personal + Affiliate + Bank --}}
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:8px;">

    {{-- Personal Details --}}
    <div class="card" style="padding:10px;">
        <div class="card-title" style="font-size:13px;margin-bottom:8px;"><i class="ti ti-user-circle" style="color:#0D5A8E"></i> {{ __('agents.personal_details_heading') }}</div>
        @php
        $pRows = [
            [__('network.full_name'),        $agent->full_name],
            [__('agents.nric_short_label'),  '******-**-****'],
            [__('network.email'),            $agent->email],
            [__('network.phone'),            $agent->phone ?? '—'],
            [__('masterfile.address_label'), $agent->address ?? '—'],
            [__('agents.verified_label'),    $agent->email_verified_at ? __('agents.yes_check_label') : __('agents.no_cross_label')],
        ];
        @endphp
        @foreach($pRows as $r)
        <div style="display:flex;gap:8px;padding:4px 0;border-bottom:1px solid #F0F7FF;font-size:12px;">
            <div style="width:70px;flex-shrink:0;color:#718096;font-weight:600;">{{ $r[0] }}</div>
            <div style="color:#2D3748;flex:1;word-break:break-word;">{{ $r[1] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Affiliate Details --}}
    <div class="card" style="padding:10px;">
        <div class="card-title" style="font-size:13px;margin-bottom:8px;"><i class="ti ti-id-badge" style="color:#0D5A8E"></i> {{ __('agents.affiliate_details_heading') }}</div>
        @php
        $aRows = [
            ['key' => 'code',     'label' => __('agents.code_label'),           'value' => $agent->agent_code ?? '—'],
            ['key' => 'role',     'label' => __('network.role_label'),          'value' => \App\Services\RoleLabelService::label($agent->role)],
            ['key' => 'status',   'label' => __('network.status_field_label'),  'value' => __('network.'.strtolower($agent->status))],
            ['key' => 'upline',   'label' => __('agents.upline_label'),         'value' => $upline ? $upline->full_name : __('agents.pending_word')],
            ['key' => 'upline_code', 'label' => __('agents.upline_code_label'), 'value' => $upline ? $upline->agent_code : '—'],
            ['key' => 'downlines', 'label' => __('agents.downlines_label'),     'value' => __('agents.downlines_count_template', ['count' => $downlines->total()])],
        ];
        @endphp
        @foreach($aRows as $r)
        <div style="display:flex;gap:8px;padding:4px 0;border-bottom:1px solid #F0F7FF;font-size:12px;">
            <div style="width:80px;flex-shrink:0;color:#718096;font-weight:600;">{{ $r['label'] }}</div>
            <div style="color:#2D3748;flex:1;word-break:break-word;font-weight:{{ $r['key']==='code' ? '700' : '400' }};color:{{ $r['key']==='code' ? '#0D5A8E' : '#2D3748' }};">{{ $r['value'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Bank + Beneficiary + Downlines --}}
    <div class="card" style="padding:10px;">
        <div class="card-title" style="font-size:13px;margin-bottom:8px;"><i class="ti ti-building-bank" style="color:#0D5A8E"></i> {{ __('agents.bank_beneficiary_heading') }}</div>
        @php
        $bRows = [
            ['key' => 'bank',    'label' => __('agents.bank_label'),    'value' => $agent->bank_name ?? '—'],
            ['key' => 'account', 'label' => __('agents.account_label'), 'value' => '****'.substr($agent->bank_account_number ?? '0000',-4)],
            ['key' => 'balance', 'label' => __('agents.balance_label'), 'value' => 'RM '.number_format($agent->commission_balance ?? 0,2)],
        ];
        @endphp
        @foreach($bRows as $r)
        <div style="display:flex;gap:8px;padding:4px 0;border-bottom:1px solid #F0F7FF;font-size:12px;">
            <div style="width:70px;flex-shrink:0;color:#718096;font-weight:600;">{{ $r['label'] }}</div>
            <div style="color:{{ $r['key']==='balance' ? '#D97706' : '#2D3748' }};font-weight:{{ $r['key']==='balance' ? '700' : '400' }};flex:1;">{{ $r['value'] }}</div>
        </div>
        @endforeach

        <div style="margin-top:8px;padding-top:8px;border-top:1px solid #E2E8F0;">
            <div style="font-size:11px;color:#0D5A8E;font-weight:700;margin-bottom:6px;"><i class="ti ti-heart" style="color:#E53E3E"></i> {{ __('profile.beneficiaries_heading') }}</div>
            @if($beneficiaries->isEmpty())
                <div style="font-size:12px;color:#A0AEC0;">{{ __('agents.none_registered_note') }}</div>
            @else
                @foreach($beneficiaries as $b)
                <div style="font-size:12px;color:#2D3748;padding:3px 0;">{{ $b->full_name }} · {{ $b->relationship }} · {{ $b->phone }}</div>
                @endforeach
            @endif
        </div>
    </div>

</div>

{{-- BOTTOM ROW: Downlines + Recent Transactions --}}
<div style="display:grid;grid-template-columns:1fr 2fr;gap:8px;">

    {{-- Direct Downlines --}}
    <div class="card" style="padding:10px;">
        <div class="card-title" style="font-size:13px;margin-bottom:8px;"><i class="ti ti-hierarchy-2" style="color:#0D5A8E"></i> {{ __('agents.direct_downlines_heading') }} <span style="margin-left:auto;font-size:11px;color:#718096;font-weight:400;">{{ __('agents.count_total_template', ['count' => $downlines->total()]) }}</span></div>
        @if($downlines->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:16px 0;font-size:12px;">{{ __('agents.no_downlines_yet_note') }}</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#F7FAFC;">
                    <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('agents.name_col') }}</th>
                    <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.role_label') }}</th>
                    <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.status_field_label') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($downlines as $d)
                    <tr style="border-bottom:1px solid #F7FAFC;cursor:pointer;"
                        onclick="window.location='{{ route('admin.agents.profile', $d->agent_id) }}'"
                        onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="padding:6px 8px;font-weight:600;">{{ $d->full_name }}<br><span style="font-size:10px;color:#718096;">{{ $d->agent_code }}</span></td>
                        <td style="padding:6px 8px;text-align:center;">
                            <span style="padding:1px 6px;border-radius:20px;font-size:10px;font-weight:600;
                                background:{{ $d->role==='GROUP_LEADER' ? '#C8E6C9' : ($d->role==='TEAM_LEADER' ? '#BBDEFB' : '#E9D5FF') }};
                                color:{{ $d->role==='GROUP_LEADER' ? '#1B5E20' : ($d->role==='TEAM_LEADER' ? '#1565C0' : '#4C1D95') }};">
                                {{ \App\Services\RoleLabelService::shortLabel($d->role) }}
                            </span>
                        </td>
                        <td style="padding:6px 8px;text-align:center;">
                            <span style="padding:1px 6px;border-radius:20px;font-size:10px;font-weight:600;
                                background:{{ $d->status==='ACTIVE' ? '#C8E6C9' : '#FFCDD2' }};
                                color:{{ $d->status==='ACTIVE' ? '#1B5E20' : '#B71C1C' }};">
                                {{ __('network.'.strtolower($d->status)) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Recent Transactions --}}
    <div class="card" style="padding:10px;">
        <div class="card-title" style="font-size:13px;margin-bottom:8px;"><i class="ti ti-file-invoice" style="color:#0D5A8E"></i> {{ __('agents.recent_transactions_heading') }} <span style="font-size:11px;color:#718096;font-weight:400;">{{ __('agents.last_5_note') }}</span></div>
        @if($recentTransactions->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:16px 0;font-size:12px;">{{ __('agents.no_transactions_yet_note') }}</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
                <thead><tr style="background:#F7FAFC;">
                    <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('gl.col_transaction_no') }}</th>
                    <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.col_vendor') }}</th>
                    <th style="padding:6px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.col_product') }}</th>
                    <th style="padding:6px 8px;text-align:right;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('masterfile.col_amount_rm') }}</th>
                    <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.col_date') }}</th>
                    <th style="padding:6px 8px;text-align:center;border-bottom:1px solid #E2E8F0;color:#0D5A8E;">{{ __('network.col_status') }}</th>
                </tr></thead>
                <tbody>
                    @foreach($recentTransactions as $tx)
                    <tr style="border-bottom:1px solid #F7FAFC;" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                        <td style="padding:6px 8px;font-weight:600;color:#0D5A8E;">{{ $tx->policy_number }}</td>
                        <td style="padding:6px 8px;">{{ $tx->vendor_name }}</td>
                        <td style="padding:6px 8px;">{{ $tx->product_name }}</td>
                        <td style="padding:6px 8px;text-align:right;font-weight:700;">RM {{ number_format($tx->premium_amount,2) }}</td>
                        <td style="padding:6px 8px;text-align:center;color:#718096;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}</td>
                        <td style="padding:6px 8px;text-align:center;">
                            <span style="padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;
                                background:{{ $tx->status==='ACTIVE' ? '#C8E6C9' : ($tx->status==='PENDING' ? '#FFF9C4' : '#FFCDD2') }};
                                color:{{ $tx->status==='ACTIVE' ? '#1B5E20' : ($tx->status==='PENDING' ? '#F57F17' : '#B71C1C') }};">
                                {{ __('agents.tx_status_'.strtolower($tx->status)) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

</div>

@endsection
