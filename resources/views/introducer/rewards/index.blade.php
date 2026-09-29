@extends('layouts.dashboard')

@section('title', __('gl.reward_points_title'))

@section('page-title')
{{ __('gl.my_reward_points_title') }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->full_name }} &middot; {{ $agent->agent_code }}</span>
@endsection

@push('styles')
<style>
.rwd-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.rwd-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.rwd-table-card{flex:1;overflow:auto;padding:8px 12px 60px 12px;min-height:0;display:flex;flex-direction:column;}
.rwd-table{width:100%;border-collapse:collapse;font-size:11px;}
.rwd-table th{padding:5px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;}
.rwd-table td{padding:5px 8px;line-height:1.3;border-bottom:1px solid #F7FAFC;}
.rwd-badge{padding:1px 8px;border-radius:20px;font-size:10px;font-weight:600;}
</style>
@endpush

@section('content')
<div class="rwd-wrap">

    {{-- Summary --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;flex-shrink:0;">
        <div class="rwd-card" style="border-left:3px solid #D97706;display:flex;flex-direction:column;justify-content:center;">
            <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('gl.current_balance_label') }}</div>
            <div style="font-size:22px;font-weight:800;color:#D97706;">{{ number_format($currentBalance,2) }} pts</div>
        </div>
        <div class="rwd-card" style="border-left:3px solid #38A169;display:flex;flex-direction:column;justify-content:center;">
            <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('gl.total_earned_alltime_label') }}</div>
            <div style="font-size:22px;font-weight:800;color:#38A169;">{{ number_format($summary->total_earned ?? 0,2) }} pts</div>
        </div>
        <div class="rwd-card" style="border-left:3px solid #E53E3E;display:flex;flex-direction:column;justify-content:center;">
            <div style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;margin-bottom:4px;">{{ __('gl.total_redeemed_alltime_label') }}</div>
            <div style="font-size:22px;font-weight:800;color:#E53E3E;">{{ number_format($summary->total_redeemed ?? 0,2) }} pts</div>
        </div>
    </div>

    {{-- Ledger --}}
    <div class="rwd-card rwd-table-card">
        <div style="font-size:11px;font-weight:700;color:#374151;margin-bottom:8px;">{{ __('gl.points_history_heading') }}</div>
        @if($ledger->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:12px;">{{ __('gl.no_reward_points_activity_note') }}</div>
        @else
        <table class="rwd-table">
            <thead>
                <tr>
                    <th>{{ __('network.col_date') }}</th>
                    <th>{{ __('gl.col_type') }}</th>
                    <th style="text-align:right;">{{ __('gl.col_points_in') }}</th>
                    <th style="text-align:right;">{{ __('gl.col_points_out') }}</th>
                    <th style="text-align:right;">{{ __('gl.col_running_balance') }}</th>
                    <th>{{ __('gl.col_reference') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ledger as $entry)
                @php
                    $typeBadges = [
                        'EARNED'=>['#d1fae5','#065f46'], 'PURCHASED'=>['#dbeafe','#1e40af'],
                        'REDEEMED'=>['#fef3c7','#92400e'], 'CASHED_OUT'=>['#fef3c7','#92400e'],
                        'EXPIRED'=>['#fee2e2','#991b1b'], 'REVERSED'=>['#fee2e2','#991b1b'],
                        'ADJUSTMENT'=>['#f3f4f6','#374151'], 'TRANSFERRED'=>['#e0f2fe','#075985'],
                    ];
                    $tb = $typeBadges[$entry->txn_type] ?? ['#f3f4f6','#374151'];
                @endphp
                <tr @if($entry->source_txn_id) style="cursor:pointer;" onclick="window.location='{{ route('introducer.dashboard') }}?txpage=last#intd-pg3'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''" @endif>
                    <td style="white-space:nowrap;color:#718096;">{{ \Carbon\Carbon::parse($entry->created_at)->format('d M Y, H:i') }}</td>
                    <td><span class="rwd-badge" style="background:{{ $tb[0] }};color:{{ $tb[1] }};">{{ $entry->txn_type }}</span></td>
                    <td style="text-align:right;color:#38A169;font-weight:700;">{{ $entry->points_in > 0 ? '+'.number_format($entry->points_in,2) : '-' }}</td>
                    <td style="text-align:right;color:#E53E3E;font-weight:700;">{{ $entry->points_out > 0 ? '-'.number_format($entry->points_out,2) : '-' }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($entry->running_balance,2) }}</td>
                    <td style="color:#9ca3af;font-size:10px;">
                        @if($entry->source_txn_id)
                            {{ __('gl.view_transaction_link') }}
                        @else
                            {{ $entry->reference_no ?? '&mdash;' }}
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($ledger->hasPages())
        <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;font-size:10px;">
            <span style="color:#9ca3af;">{{ __('gl.showing_x_to_y_of_z_records', ['first' => $ledger->firstItem(), 'last' => $ledger->lastItem(), 'total' => $ledger->total()]) }}</span>
            <div style="font-size:10px;">{{ $ledger->links() }}</div>
        </div>
        @endif
        @endif
    </div>

    {{-- Prev --}}
    <a href="{{ route('introducer.dashboard') }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">{{ __('network.prev') }}</a>

</div>
@endsection
