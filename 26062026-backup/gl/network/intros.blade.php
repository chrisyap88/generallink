@extends('layouts.dashboard')
@section('title', 'All Introducers')
@section('page-title', 'All Introducers')

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:11px;}
.nw-table th{padding:4px 8px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:11px;white-space:nowrap;background:#F7FAFC;}
.nw-table td{padding:4px 8px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:11px;}
.nw-table tr:hover td{background:#EBF5FB;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:auto;flex:1;min-height:0;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:6px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:6px 20px;font-size:11px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:80px;display:inline-block;}
.sort-sel{font-size:11px;padding:4px 10px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;color:#0D5A8E;font-weight:600;cursor:pointer;}
</style>
@endpush

@section('content')
@php
    $dashUrl   = route('gl.dashboard');
    $isPage1   = $intros->currentPage() === 1;
    $prevUrl   = $isPage1 ? $dashUrl : $intros->previousPageUrl();
    $rankStart = ($intros->currentPage() - 1) * 15 + 1;
    $top3      = $sortBy === 'earn' ? $top3Earn : $top3Sales;
    $prevTlCode = null;
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb + Sort --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:11px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ $dashUrl }}" style="color:#1B9AE4;text-decoration:none;">← Dashboard</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">All Introducers</span>
        <span style="color:#718096;font-size:10px;">({{ $intros->total() }} Introducers)</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <form method="GET" style="display:inline;">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="filter" value="intro">
                <select name="sort" class="sort-sel" onchange="this.form.submit()">
                    <option value="sales" {{ $sortBy==='sales'?'selected':'' }}>By Sales Amount</option>
                    <option value="earn" {{ $sortBy==='earn'?'selected':'' }}>By Earning Income</option>
                </select>
            </form>
            <span style="background:#E8F5E9;border-radius:8px;padding:4px 12px;font-size:10px;color:#1B5E20;">
                Total: <strong>RM {{ number_format($totalSales, 2) }}</strong>
            </span>
        </span>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:55px;">#</th>
                    <th>Introducer</th>
                    <th>Code</th>
                    <th>Under GL/TL</th>
                    <th>Code</th>
                    <th style="text-align:right;">Sales MTD (RM)</th>
                    <th style="text-align:right;">Earning Income (RM)</th>
                    <th style="text-align:right;">Txns</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                @foreach($intros as $intro)
                @php
                    $rank     = $rankStart + $loop->index;
                    $medalIdx = array_search($intro->agent_id, $top3);
                    $medals   = ['🥇','🥈','🥉'];
                    $medal    = ($medalIdx !== false && ($sortBy==='earn' ? $intro->total_earn : $intro->total_sales) > 0) ? $medals[$medalIdx] : '';

                    // Show Under GL/TL only when TL changes
                    $currentTlCode = $intro->tl_code ?? 'GL';
                    $showTl = ($currentTlCode !== $prevTlCode);
                    $prevTlCode = $currentTlCode;

                    // Label
                    if ($showTl) {
                        if ($intro->tl_role === 'TEAM_LEADER') {
                            $glTlName = $intro->tl_name . ' (TL)';
                            $glTlCode = $intro->tl_code;
                        } else {
                            $glTlName = $gl->full_name . ' (GL)';
                            $glTlCode = $gl->agent_code;
                        }
                    }
                @endphp
                <tr>
                    <td style="width:55px;">
                        <div style="display:flex;align-items:center;gap:3px;">
                            <span style="font-size:12px;width:20px;display:inline-block;">@if($medal){{ $medal }}@endif</span>
                            <span style="font-size:11px;color:#718096;">{{ $rank }}</span>
                        </div>
                    </td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $intro->full_name }}</td>
                    <td style="color:#718096;">{{ $intro->agent_code }}</td>
                    <td style="color:#0D5A8E;font-weight:600;">@if($showTl){{ $glTlName }}@endif</td>
                    <td style="color:#718096;">@if($showTl){{ $glTlCode }}@endif</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($intro->total_sales, 2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($intro->total_earn, 2) }}</td>
                    <td style="text-align:right;">
                        @if($intro->total_transactions > 0)
                            <a href="{{ route('gl.network.intro.transactions', $intro->agent_id) }}?month={{ $month }}&year={{ $year }}" style="color:#1565C0;font-weight:600;text-decoration:none;">{{ $intro->total_transactions }}</a>
                        @else
                            0
                        @endif
                    </td>
                    <td>
                        @php $sc = $intro->status==='ACTIVE' ? ['#C8E6C9','#1B5E20'] : ['#FFCDD2','#B71C1C']; @endphp
                        <span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $intro->status }}</span>
                    </td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($intro->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Navigation --}}
    <div class="nav-bar">
        <a href="{{ $prevUrl }}" class="nav-btn">← Prev</a>
        <span style="font-size:11px;color:#374151;font-weight:600;">
            @if($intros->hasPages()) Page {{ $intros->currentPage() }} / {{ $intros->lastPage() }} @endif
        </span>
        @if($intros->hasMorePages())
            <a href="{{ $intros->nextPageUrl() }}" class="nav-btn">Next →</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>

</div>
@endsection
