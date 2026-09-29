@extends('layouts.dashboard')
@section('title', $intro->full_name . ' — Transactions')
@section('page-title', $intro->full_name . ' — Transactions')

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;display:flex;flex-direction:column;}
.tbl-scroll{overflow-y:auto;max-height:calc(100vh - 180px);}
.nw-table{width:100%;border-collapse:collapse;font-size:11px;}
.nw-table th{padding:4px 8px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:11px;white-space:nowrap;background:#F7FAFC;position:sticky;top:0;z-index:1;}
.nw-table td{padding:4px 8px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:11px;}
.nw-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.nw-table tr.expanded-row td{background:#EBF5FB;font-size:10px;color:#374151;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:6px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:6px 20px;font-size:11px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:80px;display:inline-block;}
</style>
@endpush

@section('content')
@php
    $isPage1    = $transactions->currentPage() === 1;
    $prevUrl    = $isPage1 ? $backUrl : $transactions->previousPageUrl();
    $totalSales = $transactions->sum('premium_amount');
    $totalEarn  = $transactions->sum('commission_amount');
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:11px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ route('gl.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">← Dashboard</a>
        <span style="color:#718096;">›</span>
        <a href="{{ $backUrl }}" style="color:#1B9AE4;text-decoration:none;">All Introducers</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ $intro->full_name }}</span>
        <span style="color:#718096;font-size:10px;">({{ $transactions->total() }} Transactions)</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <span style="background:#E3F2FD;border-radius:8px;padding:4px 12px;font-size:10px;color:#1565C0;">
                Sales: <strong>RM {{ number_format($totalSales, 2) }}</strong>
            </span>
            <span style="background:#E8F5E9;border-radius:8px;padding:4px 12px;font-size:10px;color:#1B5E20;">
                Earning Income: <strong>RM {{ number_format($totalEarn, 2) }}</strong>
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
                    <th>Product Code</th>
                    <th>Vendor</th>
                    <th>Product</th>
                    <th style="text-align:right;">Sales Amount (RM)</th>
                    <th style="text-align:right;">Earning Income (RM)</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transactions as $txn)
                @php $rowNum = ($transactions->currentPage()-1)*15 + $loop->iteration; @endphp
                <tr onclick="toggleRow('row_{{ $txn->policy_id }}')">
                    <td style="text-align:center;color:#718096;">{{ $rowNum }}</td>
                    <td>
                        <span style="color:#1565C0;font-weight:600;cursor:pointer;">{{ $txn->product_code ?? $txn->policy_number }}</span>
                        @if($txn->policy_number && $txn->policy_number !== ($txn->product_code ?? ''))
                            <br><span style="color:#718096;font-size:9px;">{{ $txn->policy_number }}</span>
                        @endif
                    </td>
                    <td>{{ $txn->vendor_name ?? '—' }}</td>
                    <td>{{ $txn->product_name ?? '—' }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($txn->premium_amount, 2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($txn->commission_amount, 2) }}</td>
                    <td>
                        @php $sc = $txn->status==='ACTIVE' ? ['#C8E6C9','#1B5E20'] : ['#FFF3E0','#E65100']; @endphp
                        <span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $txn->status }}</span>
                    </td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($txn->created_at)->format('d M Y') }}</td>
                </tr>
                <tr id="row_{{ $txn->policy_id }}" class="expanded-row" style="display:none;">
                    <td colspan="8" style="padding:6px 16px;background:#EBF8FF;">
                        <div style="display:flex;gap:20px;flex-wrap:wrap;">
                            <span><strong>Policy No:</strong> {{ $txn->policy_number }}</span>
                            <span><strong>Coverage Start:</strong> {{ isset($txn->coverage_start) && $txn->coverage_start ? \Carbon\Carbon::parse($txn->coverage_start)->format('d M Y') : '—' }}</span>
                            <span><strong>Coverage End:</strong> {{ isset($txn->coverage_end) && $txn->coverage_end ? \Carbon\Carbon::parse($txn->coverage_end)->format('d M Y') : '—' }}</span>
                            <span><strong>Earning Rate:</strong> {{ $txn->entitlement_pct ?? '—' }}%</span>
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
        <a href="{{ $prevUrl }}" class="nav-btn">← Prev</a>
        <span style="font-size:11px;color:#374151;font-weight:600;">
            @if($transactions->hasPages()) Page {{ $transactions->currentPage() }} / {{ $transactions->lastPage() }} @endif
        </span>
        @if($transactions->hasMorePages())
            <a href="{{ $transactions->nextPageUrl() }}" class="nav-btn">Next →</a>
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
