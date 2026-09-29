@extends('layouts.dashboard')
@section('title','Policy Detail')
@section('page-title','Policy: ' . $policy->policy_number)

@section('content')

@if(session('success'))
<div style="background:#C6F6D5;border:1px solid #9AE6B4;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#22543D;font-size:13px">✓ {{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px">

    {{-- Policy details --}}
    <div>
        <div class="card" style="margin-bottom:16px">
            <div class="card-title"><i class="ti ti-file-invoice" style="color:#0D5A8E"></i> Policy Information</div>
            <table style="width:100%;font-size:13px;border-collapse:collapse">
                @foreach([
                    ['Policy number', $policy->policy_number],
                    ['Customer', $policy->customer_name],
                    ['Vendor', $policy->vendor_name],
                    ['Product', $policy->product_name],
                    ['Premium', 'RM ' . number_format($policy->premium_amount, 2)],
                    ['Sum insured', $policy->sum_insured ? 'RM ' . number_format($policy->sum_insured, 2) : '—'],
                    ['Coverage start', \Carbon\Carbon::parse($policy->coverage_start)->format('d M Y')],
                    ['Coverage end', \Carbon\Carbon::parse($policy->coverage_end)->format('d M Y')],
                    ['Renewal date', \Carbon\Carbon::parse($policy->renewal_date)->format('d M Y')],
                    ['Submitted by', $policy->agent_name],
                    ['Submitted on', \Carbon\Carbon::parse($policy->created_at)->format('d M Y H:i')],
                ] as [$label,$value])
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:8px 0;color:#718096;width:140px;font-size:12px">{{ $label }}</td>
                    <td style="padding:8px 0;font-weight:500">{{ $value }}</td>
                </tr>
                @endforeach
                <tr>
                    <td style="padding:8px 0;color:#718096;font-size:12px">Status</td>
                    <td style="padding:8px 0">
                        @php $sc = match($policy->status) { 'ACTIVE'=>'status-active','CANCELLED'=>'status-inactive',default=>'status-risk_debt' }; @endphp
                        <span class="status-badge {{ $sc }}">{{ $policy->status }}</span>
                    </td>
                </tr>
            </table>

            @if($policy->status === 'ACTIVE')
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid #F7FAFC">
                <form method="POST" action="{{ route('policies.cancel', $policy->policy_id) }}"
                      onsubmit="return confirm('Cancel this policy? All commissions will be reversed.')">
                    @csrf @method('PATCH')
                    <div style="display:flex;gap:10px;align-items:flex-end">
                        <div style="flex:1">
                            <label style="font-size:12px;font-weight:600;color:#4A5568;display:block;margin-bottom:4px">Cancellation reason *</label>
                            <input type="text" name="reason" placeholder="Reason for cancellation" required
                                style="width:100%;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px;font-size:13px">
                        </div>
                        <button type="submit"
                            style="background:#FFF5F5;border:1px solid #FC8181;color:#C53030;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap">
                            <i class="ti ti-x"></i> Cancel Policy
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>

        {{-- Product-specific attributes --}}
        @if($attributes->count() > 0)
        <div class="card" style="margin-bottom:16px">
            <div class="card-title"><i class="ti ti-list-details" style="color:#0D5A8E"></i> Product Details</div>
            <table style="width:100%;font-size:13px;border-collapse:collapse">
                @foreach($attributes as $attr)
                <tr style="border-bottom:1px solid #F7FAFC">
                    <td style="padding:7px 0;color:#718096;width:180px;font-size:12px">{{ str_replace('_',' ',ucfirst($attr->attribute_name)) }}</td>
                    <td style="padding:7px 0;font-weight:500;font-family:monospace">{{ $attr->attribute_value }}</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endif
    </div>

    {{-- Commission breakdown --}}
    <div>
        <div class="card">
            <div class="card-title"><i class="ti ti-coin" style="color:#059669"></i> Commission Breakdown</div>

            @if($commissions->count() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:20px;font-size:13px">
                <i class="ti ti-calculator" style="font-size:32px;display:block;margin-bottom:8px"></i>
                No commission records
            </div>
            @else
            @php $totalComm = $commissions->sum('commission_amount'); $totalPts = $commissions->sum('reward_points_earned'); @endphp

            <div style="background:#F0FFF4;border-radius:8px;padding:12px;margin-bottom:14px">
                <div style="font-size:11px;color:#276749">Total distributed</div>
                <div style="font-size:20px;font-weight:700;color:#22543D">RM {{ number_format($totalComm, 2) }}</div>
                <div style="font-size:11px;color:#276749;margin-top:2px">{{ number_format($totalPts, 2) }} reward pts awarded</div>
            </div>

            @foreach($commissions as $c)
            <div style="border:1px solid #E2E8F0;border-radius:8px;padding:12px;margin-bottom:10px;{{ $c->is_breakage ? 'background:#FFF5F5' : 'background:#fff' }}">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                    <span style="font-weight:600;font-size:13px">{{ $c->agent_name }}</span>
                    <span style="font-weight:700;font-size:14px;color:{{ $c->is_breakage ? '#C53030' : '#059669' }}">
                        RM {{ number_format($c->commission_amount, 2) }}
                    </span>
                </div>
                <div style="font-size:11px;color:#718096">
                    <span style="background:#E9D8FD;color:#44337A;padding:1px 6px;border-radius:4px;font-weight:600">{{ $c->role_at_transaction }}</span>
                    &nbsp;{{ $c->member_code }}
                    &nbsp;·&nbsp; {{ $c->entitlement_pct }}% of pool
                    @if($c->is_breakage) &nbsp;·&nbsp; <span style="color:#C53030">Breakage</span> @endif
                </div>
                @if($c->reward_points_earned > 0)
                <div style="font-size:11px;color:#7C3AED;margin-top:4px">
                    <i class="ti ti-star" style="font-size:12px"></i> +{{ number_format($c->reward_points_earned, 2) }} pts
                </div>
                @endif
                @if($c->redistribution_reason)
                <div style="font-size:11px;color:#C53030;margin-top:4px">{{ $c->redistribution_reason }}</div>
                @endif
            </div>
            @endforeach
            @endif

            <a href="{{ route('policies.index') }}"
                style="display:block;text-align:center;margin-top:12px;font-size:12px;color:#0D5A8E;text-decoration:none">
                <i class="ti ti-arrow-left"></i> Back to policies
            </a>
        </div>
    </div>
</div>

@endsection
