@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
{{-- FIXED 1 Aug 2026 — same fix as by-gl.blade.php / by-tl.blade.php. --}}
@php
    $introGroupId = $introducer->group_label_id;
@endphp
@section('title', $introducer->full_name . ' — ' . \App\Services\RoleLabelService::label('INTRODUCER', $introGroupId))
@section('page-title', $introducer->full_name . ' — ' . \App\Services\RoleLabelService::label('INTRODUCER', $introGroupId))

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.nw-table th{padding:2px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:10px;white-space:nowrap;background:#F7FAFC;}
.nw-table td{padding:2px 6px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:10px;overflow:hidden;text-overflow:ellipsis;}
.nw-table tr:hover td{background:#EBF5FB;}
.nw-table tr.exp-row td{background:#EBF8FF;font-size:9px;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:3px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:4px 14px;font-size:10px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:70px;display:inline-block;}
</style>
@endpush

@section('content')
@php
    $from       = request('from','');
    $selMonth   = (int)request('month', now()->month);
    $selYear    = (int)request('year', now()->year);
    $passParams = '?month='.$selMonth.'&year='.$selYear;
    $monthName  = \Carbon\Carbon::createFromDate($selYear, $selMonth, 1)->format('F Y');
    $glUrl      = route('admin.network.gl', $gl->agent_id).$passParams;
    $tlUrl      = route('admin.network.tl', [$gl->agent_id, $tl->agent_id]).$passParams.'&gl_id='.request('gl_id','all').'&sort='.request('sort','sales');
    $glFilter   = request('gl_id','');
    $tlFilter   = request('tl_id','');
    $srchFilter = request('search','');
    $sortFilter = request('sort','sales');
    $backUrl    = $from === 'all-intros'
        ? route('admin.network.all-intros').'?'.http_build_query(array_filter(['gl_id'=>$glFilter,'tl_id'=>$tlFilter,'search'=>$srchFilter,'sort'=>$sortFilter]))
        : $tlUrl;
    $isPage1    = $transactions->currentPage() === 1;
    $prevUrl    = $isPage1 ? $backUrl : $transactions->previousPageUrl();
    // BUG B FIX: $totalSales is now passed in from the controller (correct full-month
    // total), instead of being calculated here from only the current page of 10 rows.
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;flex-wrap:wrap;">
        @if($from === 'all-intros')
        <a href="{{ route('admin.dashboard') }}" style="color:#1B9AE4;text-decoration:none;font-weight:600;background:#E3F2FD;padding:2px 8px;border-radius:5px;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER', $introGroupId)]) }}</a>
        @else
        <a href="{{ route('admin.network') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.all_groups_link') }}</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $glUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ $gl->full_name }}</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $tlUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ $tl->full_name }}</a>
        @endif
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ $introducer->full_name }}</span>
        <span style="color:#718096;">({{ __('network.transactions_count', ['count' => $transactions->total()]) }})</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:1px 6px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;display:flex;gap:6px;">
            <span style="background:#E3F2FD;border-radius:8px;padding:2px 8px;font-size:9px;color:#1565C0;">{!! __('network.sales_colon_amount', ['amount' => '<strong>RM '.number_format($totalSales,2).'</strong>']) !!}</span>
            <span style="background:#FFF8E1;border-radius:8px;padding:2px 8px;font-size:9px;color:#F57F17;">{!! __('network.earning_colon_amount', ['amount' => '<strong>RM '.number_format($totalCommission,2).'</strong>']) !!}</span>
            <span style="background:#F3E5F5;border-radius:8px;padding:2px 8px;font-size:9px;color:#4A148C;">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER', groupLabelId: $introGroupId) }}: <strong>{{ $tl->full_name }}</strong> &nbsp;{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER', groupLabelId: $introGroupId) }}: <strong>{{ $gl->full_name }}</strong></span>
        </span>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:5%;">{{ __('network.col_no') }}</th>
                    <th style="width:14%;">{{ __('network.col_product_code') }}</th>
                    <th style="width:16%;">{{ __('network.col_vendor') }}</th>
                    <th style="width:16%;">{{ __('network.col_product') }}</th>
                    <th style="text-align:right;width:14%;">{{ __('network.col_sales_amount_rm') }}</th>
                    <th style="text-align:center;width:10%;">{{ __('network.col_date') }}</th>
                    <th style="text-align:center;width:10%;">{{ __('network.col_renewal') }}</th>
                    <th style="text-align:center;width:8%;">{{ __('network.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php $rowNum = $loop->iteration; @endphp
                <tr onclick="toggleRow('row_{{ $tx->policy_id }}')">
                    <td style="color:#718096;text-align:center;">{{ $rowNum }}</td>
                    <td>
                        <span style="color:#1565C0;font-weight:600;cursor:pointer;">{{ $tx->policy_number }}</span>
                    </td>
                    <td>{{ $tx->vendor_name }}</td>
                    <td>{{ $tx->product_name }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($tx->premium_amount,2) }}</td>
                    <td style="text-align:center;color:#718096;">{{ \Carbon\Carbon::parse($tx->created_at)->format('d M Y') }}</td>
                    <td style="text-align:center;font-size:9px;color:{{ $tx->renewal_date && \Carbon\Carbon::parse($tx->renewal_date)->diffInDays(now())<=30?'#E53E3E':'#718096' }};">
                        {{ $tx->renewal_date ? \Carbon\Carbon::parse($tx->renewal_date)->format('d M Y') : '—' }}
                    </td>
                    <td style="text-align:center;">
                        @php
                            $sc = match($tx->status) {
                                'ACTIVE'          => ['#C8E6C9','#1B5E20'],
                                'PENDING_RENEWAL' => ['#FFF9C4','#F57F17'],
                                'LAPSED'          => ['#FFCDD2','#B71C1C'],
                                'SUBMITTED'       => ['#BBDEFB','#1565C0'],
                                default           => ['#F3F4F6','#374151'],
                            };
                        @endphp
                        <span style="padding:2px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ __('network.'.strtolower($tx->status)) }}</span>
                    </td>
                </tr>
                <tr id="row_{{ $tx->policy_id }}" class="exp-row" style="display:none;">
                    <td colspan="8" style="padding:4px 16px;">
                        <div style="display:flex;gap:16px;flex-wrap:wrap;">
                            <span><strong>{{ __('network.coverage_start_label') }}</strong> {{ $tx->coverage_start ? \Carbon\Carbon::parse($tx->coverage_start)->format('d M Y') : '—' }}</span>
                            <span><strong>{{ __('network.coverage_end_label') }}</strong> {{ $tx->coverage_end ? \Carbon\Carbon::parse($tx->coverage_end)->format('d M Y') : '—' }}</span>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="padding:30px;text-align:center;color:#A0AEC0;">{{ __('network.no_transactions_found', ['month' => $monthName]) }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Navigation --}}
    <div class="nav-bar">
        @if(!$transactions->onFirstPage())
            <a href="{{ $transactions->previousPageUrl() }}" class="nav-btn">{{ __('network.prev') }}</a>
        @else
            <a href="{{ $tlUrl }}" class="nav-btn">← {{ $tl->full_name }}</a>
        @endif
        <span style="font-size:10px;color:#374151;font-weight:600;">@if($transactions->hasPages()) {{ __('network.page_of', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage()]) }} @endif</span>
        @if($transactions->hasMorePages())<a href="{{ $transactions->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>@else<span class="nav-btn-ghost"></span>@endif
    </div>

</div>

<script>
function toggleRow(id){var r=document.getElementById(id);if(r)r.style.display=r.style.display==='none'?'table-row':'none';}
</script>
@endsection
