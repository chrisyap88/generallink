@extends('layouts.dashboard')
@section('title', 'My Group Network')
@section('page-title', 'My Group Network')

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:11px;}
.nw-table th{padding:4px 8px;text-align:left;border-bottom:2px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:11px;white-space:nowrap;background:#F7FAFC;}
.nw-table td{padding:4px 8px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:11px;}
.nw-table tr:hover td{background:#EBF5FB;cursor:pointer;}
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
    $isPage1   = $tls->currentPage() === 1;
    $prevUrl   = $isPage1 ? $dashUrl : $tls->previousPageUrl();
    $rankStart = ($tls->currentPage() - 1) * 15 + 1;
    $top3      = $sortBy === 'earn' ? $top3Earn : $top3Sales;
@endphp

<div class="nw-wrap">

    {{-- Breadcrumb + Sort --}}
    <div style="display:flex;align-items:center;gap:8px;font-size:11px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ $dashUrl }}" style="color:#1B9AE4;text-decoration:none;">← Dashboard</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">My Group Network</span>
        <span style="color:#718096;font-size:10px;">({{ $tls->total() }} Team Leaders)</span>
        <span style="background:#E0F7FA;color:#0D5A8E;font-size:9px;font-weight:600;padding:2px 8px;border-radius:10px;">📅 {{ $monthName }}</span>
        <span style="margin-left:auto;display:flex;align-items:center;gap:8px;">
            <form method="GET" style="display:inline;">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="from" value="{{ $from }}">
                <select name="sort" class="sort-sel" onchange="this.form.submit()">
                    <option value="sales" {{ $sortBy==='sales'?'selected':'' }}>By Sales Amount</option>
                    <option value="earn" {{ $sortBy==='earn'?'selected':'' }}>By Earning Income</option>
                </select>
            </form>
            <span style="background:#E8F5E9;border-radius:8px;padding:4px 12px;font-size:10px;color:#1B5E20;">
                Group Sales: <strong>RM {{ number_format($tls->sum('total_sales') + $ownSales, 2) }}</strong>
            </span>
        </span>
    </div>

    {{-- Table --}}
    <div class="tbl-wrap">
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:50px;text-align:center;">#</th>
                    <th>Team Leader</th>
                    <th>Code</th>
                    <th style="text-align:center;">Introducers</th>
                    <th style="text-align:right;">Sales MTD (RM)</th>
                    <th style="text-align:right;">Earning Income (RM)</th>
                    <th style="text-align:right;">Transactions</th>
                    <th>Status</th>
                    <th>Joined</th>
                </tr>
            </thead>
            <tbody>
                {{-- GL own row --}}
                @if($isPage1)
                <tr style="background:#F0FFF4;">
                    <td style="text-align:center;color:#718096;">—</td>
                    <td style="font-weight:700;color:#0D5A8E;">{{ $gl->full_name }} <span style="font-size:9px;background:#E0F7FA;color:#0D5A8E;padding:1px 6px;border-radius:10px;margin-left:4px;">GL</span></td>
                    <td style="color:#718096;">{{ $gl->agent_code }}</td>
                    <td style="text-align:center;">—</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($ownSales, 2) }}</td>
                    <td style="text-align:right;">—</td>
                    <td style="text-align:right;">—</td>
                    <td><span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:600;background:#C8E6C9;color:#1B5E20;">ACTIVE</span></td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($gl->created_at)->format('d M Y') }}</td>
                </tr>
                @endif

                @foreach($tls as $tl)
                @php
                    $rank = $rankStart + $loop->index;
                    $medalIdx = array_search($tl->agent_id, $top3);
                    $medals = ['🥇','🥈','🥉'];
                    $medal = ($medalIdx !== false && ($sortBy==='earn' ? $tl->total_earn : $tl->total_sales) > 0) ? $medals[$medalIdx] : '';
                @endphp
                <tr onclick="window.location='{{ route('gl.network.tl', $tl->agent_id) }}?month={{ $month }}&year={{ $year }}&from={{ $from }}'">
                    <td style="width:60px;">
                        <div style="display:flex;align-items:center;gap:3px;">
                            <span style="font-size:12px;width:20px;display:inline-block;">@if($medal){{ $medal }}@endif</span>
                            <span style="font-size:11px;color:#718096;">{{ $rank }}</span>
                        </div>
                    </td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $tl->full_name }}</td>
                    <td style="color:#718096;">{{ $tl->agent_code }}</td>
                    <td style="text-align:center;">{{ $introCount[$tl->agent_id] ?? 0 }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($tl->total_sales, 2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($tl->total_earn, 2) }}</td>
                    <td style="text-align:right;">{{ $tl->total_transactions }}</td>
                    <td>
                        @php $sc = $tl->status==='ACTIVE' ? ['#C8E6C9','#1B5E20'] : ['#FFCDD2','#B71C1C']; @endphp
                        <span style="padding:2px 8px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $sc[0] }};color:{{ $sc[1] }};">{{ $tl->status }}</span>
                    </td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($tl->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Navigation --}}
    <div class="nav-bar">
        <a href="{{ $prevUrl }}" class="nav-btn">← Prev</a>
        <span style="font-size:11px;color:#374151;font-weight:600;">
            @if($tls->hasPages()) Page {{ $tls->currentPage() }} / {{ $tls->lastPage() }} @endif
        </span>
        @if($tls->hasMorePages())
            <a href="{{ $tls->nextPageUrl() }}" class="nav-btn">Next →</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>

</div>
@endsection
