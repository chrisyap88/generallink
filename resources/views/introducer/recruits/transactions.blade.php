@extends('layouts.dashboard')
@section('title', __('tl.name_dash_transactions_title', ['name' => $intro->full_name]))
@section('page-title', __('tl.name_dash_transactions_title', ['name' => $intro->full_name]))

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;display:flex;flex-direction:column;}
.tbl-scroll{overflow:hidden;flex:1;min-height:0;}
.nw-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.nw-table th{padding:4px 8px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:10px;white-space:nowrap;background:#F7FAFC;position:sticky;top:0;z-index:1;}
.nw-table td{padding:2px 6px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:10px;overflow:hidden;text-overflow:ellipsis;}
.nw-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.nw-table tr.expanded-row td{background:#EBF5FB;font-size:10px;color:#374151;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:3px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:4px 14px;font-size:10px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:80px;display:inline-block;}
</style>
@endpush

@section('content')
@php
    $isPage1    = $transactions->currentPage() === 1;
    $prevUrl    = $isPage1 ? $backUrl : $transactions->previousPageUrl();
    $totalSales = $transactions->sum('premium_amount');
    $totalEarn  = $transactions->sum('my_commission');
    $statusLabels = [
        'DRAFT'=>__('gl.status_draft'),'SUBMITTED'=>__('network.submitted'),'ACTIVE'=>__('network.active'),
        'PENDING_RENEWAL'=>__('network.pending_renewal'),'RENEWED'=>__('gl.status_renewed'),
        'LAPSED'=>__('network.lapsed'),'CANCELLED'=>__('gl.status_cancelled'),
    ];
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ route('introducer.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">{{ __('tl.my_role_title', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ $intro->full_name }}</span>
        <span style="color:#718096;font-size:10px;">({{ __('network.transactions_count', ['count' => $transactions->total()]) }})</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}</span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <span style="background:#E3F2FD;border-radius:8px;padding:4px 12px;font-size:10px;color:#1565C0;">
                {!! __('network.sales_colon_amount', ['amount' => '<strong>RM '.number_format($grandTotal, 2).'</strong>']) !!}
            </span>
            <span style="background:#E8F5E9;border-radius:8px;padding:4px 12px;font-size:10px;color:#1B5E20;">
                {{ __('gl.earning_income_colon') }} <strong>RM {{ number_format($commissionGrandTotal, 2) }}</strong>
            </span>
        </span>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        <div class="tbl-scroll">
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:40px;text-align:center;">#</th>
                    <th>{{ __('gl.col_policy_no') }}</th>
                    <th>{{ __('network.col_vendor') }}</th>
                    <th>{{ __('network.col_product') }}</th>
                    <th style="text-align:right;">{{ __('network.col_sales_amount_rm') }}</th>
                    <th style="text-align:right;">{{ __('gl.col_earning_income_rm') }}</th>
                    <th>{{ __('network.col_status') }}</th>
                    <th>{{ __('network.col_date') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $txn)
                @php $rowNum = $loop->iteration; @endphp
                <tr onclick="toggleRow('row_{{ $txn->policy_id }}')">
                    <td style="text-align:center;color:#718096;">{{ $rowNum }}</td>
                    <td>
                        <span style="color:#1565C0;font-weight:600;cursor:pointer;">{{ $txn->policy_number }}</span>
                    </td>
                    <td>{{ $txn->vendor_name ?? '—' }}</td>
                    <td>{{ $txn->product_name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($txn->premium_amount, 2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($txn->my_commission, 2) }}</td>
                    <td>
                        @php $sc = $txn->status==='ACTIVE' ? ['#C8E6C9','#1B5E20'] : ($txn->status==='PENDING_RENEWAL' ? ['#FFF9C4','#F57F17'] : ['#FFCDD2','#B71C1C']); @endphp
                        <span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $statusLabels[$txn->status] ?? $txn->status }}</span>
                    </td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($txn->created_at)->format('d M Y') }}</td>
                </tr>
                <tr id="row_{{ $txn->policy_id }}" class="expanded-row" style="display:none;">
                    <td colspan="8" style="padding:6px 16px;background:#EBF8FF;">
                        <div style="display:flex;gap:20px;flex-wrap:wrap;">
                            <span><strong>{{ __('network.coverage_start_label') }}</strong> {{ isset($txn->coverage_start) && $txn->coverage_start ? \Carbon\Carbon::parse($txn->coverage_start)->format('d M Y') : '—' }}</span>
                            <span><strong>{{ __('network.coverage_end_label') }}</strong> {{ isset($txn->coverage_end) && $txn->coverage_end ? \Carbon\Carbon::parse($txn->coverage_end)->format('d M Y') : '—' }}</span>
                            <span><strong>{{ __('gl.earning_rate_colon') }}</strong> {{ $txn->my_pct ?? '—' }}%</span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="nav-bar">
        <a href="{{ $prevUrl }}" class="nav-btn">{{ __('network.prev') }}</a>
        <span style="font-size:10px;color:#374151;font-weight:600;">
            @if($transactions->hasPages()) {{ __('network.page_of', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage()]) }} @endif
        </span>
        @if($transactions->hasMorePages())
            <a href="{{ $transactions->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>

</div>

<script>
function toggleRow(id) {
    var row = document.getElementById(id);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>
@endsection
