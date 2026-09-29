@extends('layouts.dashboard')
@section('title', __('tl.name_dash_sub_team_title', ['name' => $promotedTL->full_name]))
@section('page-title', __('tl.name_dash_sub_team_title', ['name' => $promotedTL->full_name]))

@push('styles')
<style>
.net-wrap{padding:6px 8px;display:flex;flex-direction:column;gap:5px;height:calc(100vh - 66px);box-sizing:border-box;overflow:hidden;}
.net-card{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:8px 12px;}
.net-table{width:100%;border-collapse:collapse;font-size:11px;}
.net-table th{padding:5px 8px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;background:#F7FAFC;}
.net-table td{padding:5px 8px;line-height:1.3;border-bottom:1px solid #F7FAFC;}
.net-badge{padding:1px 8px;border-radius:20px;font-size:10px;font-weight:600;}
</style>
@endpush

@section('content')
<div class="net-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;">
        <a href="{{ route('tl.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ __('tl.promoted_role_title', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ __('tl.name_sub_team_possessive', ['name' => $promotedTL->full_name]) }}</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;margin-left:8px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;background:#E8F5E9;border-radius:8px;padding:3px 10px;font-size:10px;color:#1B5E20;">
            {{ __('tl.total_sales_mtd_colon') }} <strong>RM {{ number_format($grandTotal, 2) }}</strong>
        </span>
    </div>

    <div class="net-card" style="flex:1;overflow:hidden;padding:8px 12px;display:flex;flex-direction:column;">
        <table class="net-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('tl.col_member') }}</th>
                    <th>{{ __('network.col_code') }}</th>
                    <th>{{ __('gl.col_role') }}</th>
                    <th style="text-align:center;">{{ __('gl.col_transactions') }}</th>
                    <th style="text-align:right;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="text-align:right;">{{ __('tl.col_earning_income_mtd_rm') }}</th>
                    <th style="text-align:center;">{{ __('network.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subMembers as $i => $m)
                <tr style="cursor:pointer;" onclick="window.location='{{ route('tl.promoted-tls.member.transactions', [$promotedTL->agent_id, $m->agent_id]) }}?month={{ $month }}&year={{ $year }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="color:#718096;">{{ $i+1 }}</td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $m->full_name }}</td>
                    <td style="color:#9ca3af;">{{ $m->agent_code }}</td>
                    <td>
                        @php
                            $roleLabel = $m->role === 'TEAM_LEADER' ? __('tl.promoted_role_title', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) : \App\Services\RoleLabelService::label('INTRODUCER');
                            $roleBg = $m->role === 'TEAM_LEADER' ? ['#F3E5F5','#4A148C'] : ['#E8F5E9','#1B5E20'];
                        @endphp
                        <span class="net-badge" style="background:{{ $roleBg[0] }};color:{{ $roleBg[1] }};">{{ $roleLabel }}</span>
                    </td>
                    <td style="text-align:center;font-weight:600;color:#1565C0;">{{ $m->txn_count }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($m->sales_mtd, 2) }}</td>
                    <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format($m->earn_mtd, 2) }}</td>
                    <td style="text-align:center;">
                        <span class="net-badge" style="background:{{ $m->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $m->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ $m->status==='ACTIVE' ? __('network.active') : __('network.inactive') }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <a href="{{ $backUrl }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:700;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">{{ __('network.prev') }}</a>

</div>
@endsection
