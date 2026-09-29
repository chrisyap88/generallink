@extends('layouts.dashboard')

@section('title', $periodLabel)
@section('page-title', $periodLabel)

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:3px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:8px;table-layout:fixed;}
.nw-table th{padding:2px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:8px;white-space:nowrap;background:#F7FAFC;overflow:hidden;text-overflow:ellipsis;}
.nw-table td{padding:2px 6px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:8px;overflow:hidden;text-overflow:ellipsis;}
.nw-table tr:hover td{background:#EBF5FB;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:3px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:3px 14px;font-size:9px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:70px;display:inline-block;}
.rank-badge{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px;border-radius:50%;font-size:8px;font-weight:700;}
</style>
@endpush

@section('content')
@php
    $isGL   = auth('agent')->check() && auth('agent')->user()->role === 'GROUP_LEADER';
    $isTL   = auth('agent')->check() && auth('agent')->user()->role === 'TEAM_LEADER';
    $isIntro= auth('agent')->check() && auth('agent')->user()->role === 'INTRODUCER';
    if (!isset($backUrl)) {
        $backUrl = $isGL ? route('gl.dashboard') : ($isTL ? route('tl.dashboard') : ($isIntro ? route('introducer.dashboard') : route('admin.dashboard')));
    }
    $drillBase = $isGL ? route('gl.dashboard.drilldown') : ($isTL ? route('tl.dashboard.drilldown') : ($isIntro ? route('introducer.dashboard.drilldown') : route('admin.dashboard.drilldown')));
    $isPage1 = $records->currentPage() === 1;
    $prevUrl = $isPage1 ? $backUrl : $records->previousPageUrl();
    $rankStart = 1;
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:9px;flex-shrink:0;">
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ __('drilldown.back_to_dashboard_link') }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#718096;font-size:8px;">{{ __('drilldown.records_count_paren', ['count' => number_format($records->total())]) }}</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:8px;font-weight:600;padding:1px 6px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;background:#E8F5E9;border-radius:8px;padding:2px 10px;font-size:8px;color:#1B5E20;">
            {{ __('drilldown.total_label') }} <strong>RM {{ number_format($total, 2) }}</strong>
        </span>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        @if($records->count() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:11px;">{{ __('drilldown.no_records_found') }}</div>
        @else
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="text-align:center;width:30px;">{{ __('drilldown.col_hash') }}</th>
                    @foreach($columns as $col)
                    <th @if(in_array($col, [__('drilldown.col_sales_amount_rm'), __('drilldown.col_earning_rm')])) style="text-align:right;" @endif
                        @if($col === __('gl.col_transactions')) style="text-align:right;" @endif>{{ $col }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($records as $rec)
                @php $rank = $rankStart + $loop->index; @endphp
                <tr>
                    {{-- Rank number --}}
                    <td style="text-align:center;">
                        <span style="font-size:8px;color:#718096;">{{ $rank }}</span>
                    </td>

                    @if($viewType === 'ranking_performers')
                    <td style="font-weight:600;color:#0D5A8E;">{{ $rec->full_name }}</td>
                    <td style="color:#718096;">{{ $rec->agent_code }}</td>
                    <td>
                        @php
                            $roleColor = match($rec->role) {
                                'GROUP_LEADER' => ['#E0F7FA','#0D5A8E'],
                                'TEAM_LEADER'  => ['#F3E5F5','#4A148C'],
                                'INTRODUCER'   => ['#E8F5E9','#1B5E20'],
                                default        => ['#F3F4F6','#374151'],
                            };
                            $roleShort = match($rec->role) {
                                'GROUP_LEADER' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'),
                                'TEAM_LEADER'  => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER'),
                                'INTRODUCER'   => \App\Services\RoleLabelService::shortLabel('INTRODUCER'),
                                default        => $rec->role,
                            };
                        @endphp
                        <span style="padding:2px 8px;border-radius:10px;font-size:8px;font-weight:600;background:{{ $roleColor[0] }};color:{{ $roleColor[1] }};">{{ $roleShort }}</span>
                    </td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.network') : route('admin.network.gl', $rec->agent_id) }}?from=dashboard&month={{ $month }}&year={{ $year }}" style="color:#0D5A8E;text-decoration:none;">RM {{ number_format($rec->total_sales, 2) }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif($viewType === 'ranking_vendors')
                    <td style="font-weight:600;color:#0D5A8E;">{{ $rec->vendor_name }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.dashboard.drilldown') : route('admin.dashboard.drilldown') }}?type=vendor_gl&vendor_id={{ $rec->vendor_id }}&month={{ $month }}&year={{ $year }}" style="color:#0D5A8E;text-decoration:none;">RM {{ number_format($rec->total_sales, 2) }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif($viewType === 'ranking_vendor_gl')
                    <td style="font-weight:600;color:#0D5A8E;">{{ $rec->full_name }}</td>
                    @php
                        $rc = match($rec->role ?? '') {
                            'GROUP_LEADER' => ['#E0F7FA','#0D5A8E',\App\Services\RoleLabelService::shortLabel('GROUP_LEADER')],
                            'TEAM_LEADER'  => ['#F3E5F5','#4A148C',\App\Services\RoleLabelService::shortLabel('TEAM_LEADER')],
                            'INTRODUCER'   => ['#E8F5E9','#1B5E20',\App\Services\RoleLabelService::shortLabel('INTRODUCER')],
                            default        => ['#F3F4F6','#374151',$rec->role ?? ''],
                        };
                    @endphp
                    <td><span style="padding:1px 6px;border-radius:10px;font-size:8px;font-weight:600;background:{{ $rc[0] }};color:{{ $rc[1] }};">{{ $rc[2] }}</span></td>
                    <td style="color:#718096;">{{ $rec->agent_code }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.network') : route('admin.network.gl', $rec->agent_id) }}?from=dashboard&month={{ $month }}&year={{ $year }}" style="color:#0D5A8E;text-decoration:none;">RM {{ number_format($rec->total_sales, 2) }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif($viewType === 'ranking_products')
                    <td style="font-weight:600;color:#0D5A8E;">{{ $rec->product_name }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.dashboard.drilldown') : route('admin.dashboard.drilldown') }}?type=product_gl&product_id={{ $rec->product_id }}&month={{ $month }}&year={{ $year }}" style="color:#0D5A8E;text-decoration:none;">RM {{ number_format($rec->total_sales, 2) }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif($viewType === 'ranking_product_gl')
                    <td style="font-weight:600;color:#0D5A8E;">{{ $rec->full_name }}</td>
                    @php
                        $rc = match($rec->role ?? '') {
                            'GROUP_LEADER' => ['#E0F7FA','#0D5A8E',\App\Services\RoleLabelService::shortLabel('GROUP_LEADER')],
                            'TEAM_LEADER'  => ['#F3E5F5','#4A148C',\App\Services\RoleLabelService::shortLabel('TEAM_LEADER')],
                            'INTRODUCER'   => ['#E8F5E9','#1B5E20',\App\Services\RoleLabelService::shortLabel('INTRODUCER')],
                            default        => ['#F3F4F6','#374151',$rec->role ?? ''],
                        };
                    @endphp
                    <td><span style="padding:1px 6px;border-radius:10px;font-size:8px;font-weight:600;background:{{ $rc[0] }};color:{{ $rc[1] }};">{{ $rc[2] }}</span></td>
                    <td style="color:#718096;">{{ $rec->agent_code }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.network') : route('admin.network.gl', $rec->agent_id) }}?from=dashboard&month={{ $month }}&year={{ $year }}" style="color:#0D5A8E;text-decoration:none;">RM {{ number_format($rec->total_sales, 2) }}</a>
                    </td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif($viewType === 'ranking_trend')
                    <td style="font-weight:600;color:#0D5A8E;">
                        <a href="{{ $isGL ? route('gl.dashboard.drilldown') : route('admin.dashboard.drilldown') }}?type=sales&period=mtd&month={{ $rec->month_num }}&year={{ $rec->year_num }}" style="color:#0D5A8E;text-decoration:none;">{{ $rec->month_name }}</a>
                    </td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($rec->total_sales, 2) }}</td>
                    <td style="text-align:right;">{{ number_format($rec->total_transactions) }}</td>

                    @elseif(in_array($type, ['sales','renewal_lte30','renewal_gt30']))
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($rec->created_at)->format('d M Y') }}</td>
                    <td>{{ $rec->vendor_name }}</td>
                    <td>{{ $rec->product_name }}</td>
                    @php
                        $rc = match($rec->role ?? '') {
                            'GROUP_LEADER' => ['#E0F7FA','#0D5A8E',\App\Services\RoleLabelService::shortLabel('GROUP_LEADER')],
                            'TEAM_LEADER'  => ['#F3E5F5','#4A148C',\App\Services\RoleLabelService::shortLabel('TEAM_LEADER')],
                            'INTRODUCER'   => ['#E8F5E9','#1B5E20',\App\Services\RoleLabelService::shortLabel('INTRODUCER')],
                            default        => ['#F3F4F6','#374151',$rec->role ?? ''],
                        };
                    @endphp
                    <td><span style="padding:1px 6px;border-radius:10px;font-size:8px;font-weight:600;background:{{ $rc[0] }};color:{{ $rc[1] }};">{{ $rc[2] }}</span></td>
                    <td style="color:#718096;">{{ $rec->agent_code }}</td>
                    <td>{{ $rec->agent_name }}</td>
                    <td>{{ $rec->customer_name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($rec->premium_amount, 2) }}</td>
                    @if(isset($rec->renewal_date))
                    <td style="color:{{ $rec->renewal_date && \Carbon\Carbon::parse($rec->renewal_date)->diffInDays(now())<=30?'#E53E3E':'#718096' }};">
                        {{ $rec->renewal_date ? \Carbon\Carbon::parse($rec->renewal_date)->format('d M Y') : '—' }}
                    </td>
                    @endif
                    <td>
                        @php
                            $sc = match($rec->status) {
                                'ACTIVE'          => ['#C8E6C9','#1B5E20'],
                                'PENDING_RENEWAL' => ['#FFF9C4','#F57F17'],
                                'LAPSED'          => ['#FFCDD2','#B71C1C'],
                                'SUBMITTED'       => ['#BBDEFB','#1565C0'],
                                default           => ['#F3F4F6','#374151'],
                            };
                            $statusLabels1 = [
                                'ACTIVE' => __('growth.status_active'),
                                'PENDING_RENEWAL' => __('policy.status_pending_renewal'),
                                'LAPSED' => __('policy.status_lapsed'),
                                'SUBMITTED' => __('growth.status_submitted'),
                            ];
                        @endphp
                        <span style="padding:2px 6px;border-radius:20px;font-size:8px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $statusLabels1[$rec->status] ?? $rec->status }}</span>
                    </td>

                    @elseif(in_array($type, ['earnings','unclaimed']))
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($rec->created_at)->format('d M Y') }}</td>
                    <td>{{ $rec->vendor_name }}</td>
                    <td>{{ $rec->product_name }}</td>
                    @php
                        $rc = match($rec->role ?? '') {
                            'GROUP_LEADER' => ['#E0F7FA','#0D5A8E',\App\Services\RoleLabelService::shortLabel('GROUP_LEADER')],
                            'TEAM_LEADER'  => ['#F3E5F5','#4A148C',\App\Services\RoleLabelService::shortLabel('TEAM_LEADER')],
                            'INTRODUCER'   => ['#E8F5E9','#1B5E20',\App\Services\RoleLabelService::shortLabel('INTRODUCER')],
                            default        => ['#F3F4F6','#374151',$rec->role ?? ''],
                        };
                    @endphp
                    <td><span style="padding:1px 6px;border-radius:10px;font-size:8px;font-weight:600;background:{{ $rc[0] }};color:{{ $rc[1] }};">{{ $rc[2] }}</span></td>
                    <td style="color:#718096;">{{ $rec->agent_code }}</td>
                    <td>{{ $rec->agent_name }}</td>
                    <td>{{ $rec->customer_name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:700;color:#38A169;">RM {{ number_format($rec->commission_amount, 2) }}</td>
                    <td>
                        @php
                            $statusLabels2 = [
                                'PAID' => __('masterfile.status_paid'),
                                'PENDING' => __('drilldown.status_pending'),
                                'REJECTED' => __('masterfile.status_rejected'),
                            ];
                        @endphp
                        <span style="padding:2px 6px;border-radius:20px;font-size:9px;font-weight:600;
                            background:{{ $rec->status==='PAID'?'#C8E6C9':($rec->status==='PENDING'?'#FFF9C4':'#FFCDD2') }};
                            color:{{ $rec->status==='PAID'?'#1B5E20':($rec->status==='PENDING'?'#F57F17':'#B71C1C') }};">{{ $statusLabels2[$rec->status] ?? $rec->status }}</span>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Bottom navigation --}}
    <div class="nav-bar">
        <a href="{{ $prevUrl }}" class="nav-btn">{{ __('network.prev') }}</a>
        <span style="font-size:9px;color:#374151;font-weight:600;">
            @if($records->hasPages()) {{ __('drilldown.page_x_of_y_slash', ['current' => $records->currentPage(), 'last' => $records->lastPage()]) }} @endif
        </span>
        @if($records->hasMorePages())
            <a href="{{ $records->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>

</div>
@endsection
