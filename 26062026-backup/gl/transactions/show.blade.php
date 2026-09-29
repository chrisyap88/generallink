@extends('layouts.dashboard')

@section('title', 'Transaction Detail')

@section('page-title')
Transaction Detail <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $txn->policy_number }}</span>
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

    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
        <div class="txd-card">
            <div class="txd-label">Sales Amount (RM)</div>
            <div class="txd-val">{{ number_format($txn->premium_amount,2) }}</div>
            <div class="txd-label">Sum Insured (RM)</div>
            <div class="txd-val">{{ number_format($txn->sum_insured ?? 0,2) }}</div>
        </div>
        <div class="txd-card">
            <div class="txd-label">Product</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->product_name }}</div>
            <div class="txd-label">Vendor</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->vendor_name }}</div>
        </div>
        <div class="txd-card">
            <div class="txd-label">Customer</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->customer_name }}</div>
            <div class="txd-label">Coverage</div>
            <div class="txd-val" style="font-size:11px;">{{ \Carbon\Carbon::parse($txn->coverage_start)->format('d M Y') }} &ndash; {{ \Carbon\Carbon::parse($txn->coverage_end)->format('d M Y') }}</div>
        </div>
        <div class="txd-card">
            <div class="txd-label">Agent</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->agent_name }} ({{ $txn->agent_code }})</div>
            <div class="txd-label">Status</div>
            <div class="txd-val" style="font-size:12px;">{{ $txn->status }}</div>
        </div>
    </div>

    <div class="txd-card">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:2px;">&#128176; Commission Breakdown <span style="font-weight:400;color:#9ca3af;font-size:10px;">(full breakdown for all roles on this transaction)</span></div>
        @if($commissions->pluck('agent_id')->unique()->count() < $commissions->count())
        <table class="txd-table">
            <thead><tr><th>Agent</th><th>Role</th><th style="text-align:right;">Entitlement %</th><th style="text-align:right;">Commission (RM)</th><th style="text-align:center;">Status</th></tr></thead>
            <tbody>
                @forelse($commissions as $c)
                <tr>
                    <td>{{ $c->agent_name }} ({{ $c->agent_code }})
                        @if($c->agent_id === $agent->agent_id)
                        <span style="font-size:9px;color:#9ca3af;">&mdash; you</span>
                        @endif
                    </td>
                    <td>Earned as <strong>{{ ucwords(strtolower(str_replace('_',' ',$c->role_at_transaction))) }}</strong></td>
                    <td style="text-align:right;">{{ number_format($c->entitlement_pct,2) }}%</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($c->commission_amount,2) }}</td>
                    <td style="text-align:center;">{{ $c->status }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:#A0AEC0;padding:16px;">No commission records</td></tr>
                @endforelse
            </tbody>
            @if($commissions->isNotEmpty())
            <tfoot>
                <tr style="border-top:2px solid #E2E8F0;background:#F7FAFC;">
                    <td colspan="3" style="text-align:right;font-weight:700;color:#374151;">Total Commission Paid:</td>
                    <td style="text-align:right;font-weight:800;color:#0D5A8E;">{{ number_format($commissionSubtotal,2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Prev --}}
    @php
        $prevUrl = route('gl.transactions');
        if (request('from') === 'network' && request('tl')) {
            $prevUrl = route('gl.network.tl', request('tl'));
        }
    @endphp
    <a href="{{ $prevUrl }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">&#8592; Prev</a>

</div>
@endsection
