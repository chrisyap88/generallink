@extends('layouts.dashboard')
@section('title', __('tl.promoted_role_title', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]))
@section('page-title')
{{ __('tl.name_promoted_role_possessive', ['name' => $agent->full_name, 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }} <span style="font-size:12px;color:#9ca3af;font-weight:400;margin-left:10px;">{{ $agent->agent_code }}</span>
@endsection

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
    <div class="net-card" style="flex:1;overflow:hidden;padding:8px 12px 50px 12px;display:flex;flex-direction:column;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
            <div style="font-size:11px;font-weight:700;color:#374151;">
                🏆 {{ __('tl.promoted_role_title', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}
                <span style="font-size:10px;color:#38A169;font-weight:700;margin-left:10px;">{{ __('tl.total_sales_mtd_colon') }} RM {{ number_format($totalSales, 2) }}</span>
            </div>
            <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ $monthName }}</span>
        </div>

        @if($promotedTLs->isEmpty())
            <div style="text-align:center;color:#A0AEC0;padding:40px;font-size:12px;">{{ __('tl.no_promoted_role_yet_note', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</div>
        @else
        <table class="net-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('tl.promoted_role_title', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}</th>
                    <th>{{ __('network.col_code') }}</th>
                    <th style="text-align:center;">{{ __('tl.sub_role_label', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER')]) }}</th>
                    <th style="text-align:right;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="text-align:right;">{{ __('tl.col_earning_income_mtd_rm') }}</th>
                    <th style="text-align:center;">{{ __('network.col_status') }}</th>
                    <th>{{ __('network.col_joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($promotedTLs as $i => $ptl)
                <tr style="cursor:pointer;" onclick="window.location='{{ route('tl.promoted-tls.transactions', $ptl->agent_id) }}?month={{ $month }}&year={{ $year }}'" onmouseover="this.style.background='#EBF5FB'" onmouseout="this.style.background=''">
                    <td style="color:#718096;">{{ $i+1 }}</td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $ptl->full_name }} →</td>
                    <td style="color:#9ca3af;">{{ $ptl->agent_code }}</td>
                    <td style="text-align:center;font-weight:600;color:#7C3AED;">{{ $ptl->sub_count }}</td>
                    <td style="text-align:right;font-weight:700;">{{ number_format($ptl->sales_mtd, 2) }}</td>
                    <td style="text-align:right;font-weight:700;color:#38A169;">{{ number_format($ptl->earn_mtd, 2) }}</td>
                    <td style="text-align:center;">
                        <span class="net-badge" style="background:{{ $ptl->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $ptl->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ $ptl->status==='ACTIVE' ? __('network.active') : __('network.inactive') }}</span>
                    </td>
                    <td style="color:#9ca3af;font-size:10px;">{{ \Carbon\Carbon::parse($ptl->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <a href="{{ route('tl.dashboard') }}" style="position:fixed;bottom:16px;left:276px;background:#1565C0;color:#fff;border:none;border-radius:5px;padding:6px 18px;font-size:11px;font-weight:600;cursor:pointer;z-index:999;box-shadow:0 2px 8px rgba(0,0,0,.2);line-height:1.4;text-decoration:none;display:inline-block;">{{ __('network.back_dashboard') }}</a>
</div>
@endsection
