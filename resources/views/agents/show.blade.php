@extends('layouts.dashboard')
@section('title', __('agents.agent_detail_title'))
@section('page-title', __('agents.agent_detail_title_template', ['name' => $agent->full_name]))

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

    {{-- Agent info card --}}
    <div class="card">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #F7FAFC">
            <div style="width:52px;height:52px;border-radius:50%;background:#0D5A8E;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;font-weight:700;flex-shrink:0">
                {{ strtoupper(substr($agent->full_name,0,2)) }}
            </div>
            <div>
                <div style="font-size:16px;font-weight:700;color:#1A202C">{{ $agent->full_name }}</div>
                <div style="font-size:12px;color:#718096">{{ $agent->email }}</div>
            </div>
        </div>

        <table style="width:100%;font-size:13px;border-collapse:collapse">
            @foreach([
                [__('agents.member_code_label'),   $agent->member_code ?? '—'],
                [__('agents.agent_code_label'),    $agent->agent_code  ?? '—'],
                [__('network.role_label'),          str_replace('_',' ',$agent->role)],
                [__('agents.nric_label'),          $nricMasked],
                [__('network.phone'),         $agent->phone],
                [__('agents.group_label'),         ($agent->group_code ?? '—') . ' — ' . ($agent->group_name ?? '')],
                [__('agents.sponsor_label'),       ($agent->parent_name ?? '—') . ' (' . ($agent->parent_code ?? '—') . ')'],
                [__('network.col_joined'),        \Carbon\Carbon::parse($agent->created_at)->format('d M Y')],
                [__('agents.commission_balance_label'), 'RM ' . number_format($agent->commission_balance,2)],
                [__('agents.total_earned_label'),  'RM ' . number_format($commissionTotal,2)],
                [__('agents.reward_points_label'), number_format($pointsBalance,0) . ' pts'],
            ] as [$lbl,$val])
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:7px 0;color:#718096;width:140px;font-size:12px">{{ $lbl }}</td>
                <td style="padding:7px 0;font-weight:500">{{ $val }}</td>
            </tr>
            @endforeach
            <tr>
                <td style="padding:7px 0;color:#718096;font-size:12px">{{ __('network.status') }}</td>
                <td style="padding:7px 0"><span class="status-badge status-{{ strtolower($agent->status) }}">{{ $agent->status }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Status change + downlines --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-title"><i class="ti ti-settings" style="color:#0D5A8E"></i> {{ __('agents.change_status_heading') }}</div>
            <div style="background:#FFF8E6;border:1px solid #F6E05E;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#744210">
                {!! __('agents.resigned_deceased_warning', ['resigned' => '<strong>'.__('network.resigned').'</strong>', 'deceased' => '<strong>'.__('network.deceased').'</strong>']) !!}
            </div>
            <form method="POST" action="{{ route('admin.agents.status', $agent->agent_id) }}">
                @csrf @method('PATCH')
                <div style="display:grid;gap:10px">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('agents.new_status_label') }}</label>
                        <select name="status" style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px;background:#fff" required>
                            @foreach(['ACTIVE','INACTIVE','TERMINATED','RISK_DEBT','RESIGNED','DECEASED'] as $s)
                            <option value="{{ $s }}" {{ $agent->status == $s ? 'selected':'' }}>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">{{ __('agents.notes_label') }}</label>
                        <input type="text" name="notes" placeholder="{{ __('agents.optional_reason_placeholder') }}"
                            style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                    </div>
                </div>
                <button type="submit"
                    style="margin-top:12px;background:#0D5A8E;color:#fff;padding:8px 20px;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">
                    {{ __('agents.update_status_button') }}
                </button>
            </form>
        </div>

        {{-- Beneficiaries --}}
        @if($beneficiaries->count() > 0)
        <div class="card" style="margin-bottom:16px">
            <div class="card-title"><i class="ti ti-heart" style="color:#E53E3E"></i> {{ __('profile.beneficiaries_heading') }}</div>
            @foreach($beneficiaries as $b)
            <div style="border:1px solid #E2E8F0;border-radius:8px;padding:10px 12px;margin-bottom:8px;font-size:12px">
                <div style="font-weight:600;color:#1A202C">{{ $b->full_name }}</div>
                <div style="color:#718096;margin-top:2px">{{ $b->relationship }} · {{ $b->phone ?? '—' }}</div>
                @if($b->takeover_triggered)
                <div style="color:#059669;font-weight:600;margin-top:4px;font-size:11px">{{ __('agents.takeover_active_since_template', ['date' => \Carbon\Carbon::parse($b->takeover_at)->format('d M Y')]) }}</div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Downlines --}}
@if($downlines->count() > 0)
<div class="card" style="margin-bottom:16px">
    <div class="card-title"><i class="ti ti-hierarchy-2" style="color:#0D5A8E"></i> {{ __('agents.direct_downlines_template', ['count' => $downlines->count()]) }}</div>
    <div style="display:flex;flex-wrap:wrap;gap:8px">
        @foreach($downlines as $d)
        <a href="{{ route('admin.agents.show', $d->agent_id) }}"
            style="border:1px solid #E2E8F0;border-radius:8px;padding:8px 12px;text-decoration:none;background:#fff;font-size:12px">
            <div style="font-weight:500;color:#1A202C">{{ $d->full_name }}</div>
            <div style="font-size:11px;color:#718096;margin-top:2px">{{ $d->member_code }} · {{ str_replace('_',' ',$d->role) }}</div>
            <span class="status-badge status-{{ strtolower($d->status) }}" style="font-size:10px;margin-top:4px">{{ $d->status }}</span>
        </a>
        @endforeach
    </div>
</div>
@endif

{{-- Recent commissions --}}
@if($recentCommissions->count() > 0)
<div class="card">
    <div class="card-title"><i class="ti ti-coin" style="color:#059669"></i> {{ __('agents.recent_commission_transactions_heading') }}</div>
    <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead>
            <tr style="background:#F7FAFC">
                @foreach([__('network.col_date'), __('agents.col_policy'), __('network.role_label'), __('agents.col_pool_rm'), __('agents.col_entitlement_pct'), __('agents.col_commission_rm'), __('growth.reward_type_points'), __('network.status')] as $h)
                <th style="padding:8px;text-align:left;border-bottom:1px solid #E2E8F0;font-size:11px;color:#4A5568;font-weight:600">{{ $h }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($recentCommissions as $c)
            <tr style="border-bottom:1px solid #F7FAFC">
                <td style="padding:8px;color:#718096">{{ \Carbon\Carbon::parse($c->created_at)->format('d/m/Y') }}</td>
                <td style="padding:8px;font-family:monospace;font-size:11px">{{ $c->policy_number }}</td>
                <td style="padding:8px"><span style="background:#E9D8FD;color:#44337A;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:600">{{ $c->role_at_transaction }}</span></td>
                <td style="padding:8px">{{ number_format($c->total_pool_amount,2) }}</td>
                <td style="padding:8px">{{ $c->entitlement_pct }}%</td>
                <td style="padding:8px;font-weight:700;color:#059669">{{ number_format($c->commission_amount,2) }}</td>
                <td style="padding:8px;color:#7C3AED">{{ number_format($c->reward_points_earned,2) }}</td>
                <td style="padding:8px"><span class="status-badge status-{{ strtolower($c->status) }}" style="font-size:10px">{{ $c->status }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div style="margin-top:16px">
    <a href="{{ route('admin.agents.index') }}"
        style="color:#0D5A8E;font-size:13px;text-decoration:none"><i class="ti ti-arrow-left"></i> {{ __('agents.back_to_agents_link') }}</a>
</div>

@endsection
