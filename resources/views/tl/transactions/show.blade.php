@extends('layouts.dashboard')

@section('title', __('gl.transaction_detail_title'))

@section('page-title')
{{ __('gl.transaction_detail_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $txn->policy_number }}</span>
@endsection

@push('styles')
<style>
.txd-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:6px;height:calc(100vh - 66px);box-sizing:border-box;overflow:auto;padding-bottom:60px;}
.txd-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:10px 14px;font-size:11px;}
.txd-label{font-size:10px;color:#9ca3af;text-transform:uppercase;font-weight:700;margin-bottom:2px;}
.txd-val{font-size:13px;font-weight:700;color:#1565C0;margin-bottom:8px;}
.txd-table{width:100%;border-collapse:collapse;font-size:11px;}
.txd-table th{padding:4px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;}
.txd-table td{padding:4px 8px;border-bottom:1px solid #F7FAFC;}
</style>
@endpush

@section('content')
<div class="txd-wrap">

    @php
        $statusLabels = [
            'DRAFT'=>__('gl.status_draft'),'SUBMITTED'=>__('network.submitted'),'ACTIVE'=>__('network.active'),
            'PENDING_RENEWAL'=>__('network.pending_renewal'),'RENEWED'=>__('gl.status_renewed'),
            'LAPSED'=>__('network.lapsed'),'CANCELLED'=>__('gl.status_cancelled'),
        ];
        $roleLabels = [
            'GROUP_LEADER'=>__('gl.role_group_leader'),'TEAM_LEADER'=>__('gl.role_team_leader'),'INTRODUCER'=>__('gl.role_introducer'),
        ];
    @endphp

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
        <div class="txd-card">
            <div class="txd-label">{{ __('network.col_sales_amount_rm') }}</div>
            <div class="txd-val">{{ number_format($txn->premium_amount,2) }}</div>
            <div class="txd-label">{{ __('gl.sum_insured_rm_label') }}</div>
            <div class="txd-val">{{ number_format($txn->sum_insured ?? 0,2) }}</div>
        </div>
        <div class="txd-card">
            <div class="txd-label">{{ __('gl.col_product') }}</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->product_name }}</div>
            <div class="txd-label">{{ __('gl.col_vendor') }}</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->vendor_name }}</div>
        </div>
        <div class="txd-card">
            <div class="txd-label">{{ __('gl.col_customer') }}</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->customer_name }}</div>
            <div class="txd-label">{{ __('gl.col_coverage') }}</div>
            <div class="txd-val" style="font-size:11px;">
                @if($txn->coverage_start && $txn->coverage_end)
                    {{ \Carbon\Carbon::parse($txn->coverage_start)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($txn->coverage_end)->format('d M Y') }}
                @else
                    <span style="color:#9ca3af;">—</span>
                @endif
            </div>
        </div>
        <div class="txd-card">
            <div class="txd-label">{{ __('gl.col_agent') }}</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->agent_name }} ({{ $txn->agent_code }})</div>
            <div class="txd-label">{{ __('gl.col_status') }}</div>
            <div class="txd-val" style="font-size:12px;">{{ $statusLabels[$txn->status] ?? $txn->status }}</div>
        </div>
    </div>

    <div class="txd-card">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:2px;">{{ __('gl.earning_income_breakdown_heading') }} <span style="font-weight:400;color:#9ca3af;font-size:10px;">{{ __('tl.team_share_note') }}</span></div>
        @if($commissions->pluck('agent_id')->unique()->count() < $commissions->count())
        <div style="font-size:10px;color:#9ca3af;margin-bottom:6px;">{{ __('tl.multi_role_earning_note', ['role1' => \App\Services\RoleLabelService::label('INTRODUCER'), 'role2' => \App\Services\RoleLabelService::label('TEAM_LEADER')]) }}</div>
        @endif
        <table class="txd-table">
            <thead><tr><th>{{ __('gl.col_agent') }}</th><th>{{ __('gl.col_role') }}</th><th style="text-align:right;">{{ __('gl.col_entitlement_pct') }}</th><th style="text-align:right;">{{ __('gl.col_earning_income_rm') }}</th><th style="text-align:center;">{{ __('gl.col_status') }}</th></tr></thead>
            <tbody>
                @forelse($commissions as $c)
                <tr>
                    <td>{{ $c->agent_name }} ({{ $c->agent_code }})
                        @if($c->agent_id === $agent->agent_id)
                        <span style="font-size:9px;color:#9ca3af;">{{ __('gl.you_suffix') }}</span>
                        @endif
                    </td>
                    <td>{{ __('gl.earned_as_prefix') }} <strong>{{ $roleLabels[$c->role_at_transaction] ?? ucwords(strtolower(str_replace('_',' ',$c->role_at_transaction))) }}</strong>{{ __('gl.earned_as_suffix') }}</td>
                    <td style="text-align:right;">{{ number_format($c->entitlement_pct,2) }}%</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($c->commission_amount,2) }}</td>
                    <td style="text-align:center;">{{ $statusLabels[$c->status] ?? $c->status }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#A0AEC0;padding:16px;">{{ __('gl.no_earning_income_records') }}</td></tr>
                @endforelse
            </tbody>
            @if($commissions->isNotEmpty())
            <tfoot>
                <tr style="border-top:2px solid #E2E8F0;background:#F7FAFC;">
                    <td colspan="3" style="text-align:right;font-weight:700;color:#374151;">{{ __('tl.team_subtotal_label') }}</td>
                    <td style="text-align:right;font-weight:800;color:#0D5A8E;">{{ number_format($commissionSubtotal,2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Prev --}}
    @php
        $prevUrl = route('tl.transactions');
        if (request('from') === 'intro' && request('id')) {
            $prevUrl = route('tl.introducers.transactions', request('id'));
        }
    @endphp
    <a href="{{ $prevUrl }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">{{ __('network.prev') }}</a>

</div>
@endsection
