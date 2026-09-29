@extends('layouts.dashboard')
@section('title', __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]))
@section('page-title', __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]))

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.nw-table th{padding:2px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:10px;white-space:nowrap;background:#F7FAFC;}
.nw-table td{padding:2px 6px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:10px;overflow:hidden;text-overflow:ellipsis;}
.nw-table tr:hover td{background:#EBF5FB;cursor:pointer;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:3px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:4px 14px;font-size:10px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:70px;display:inline-block;}
.fsel{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;color:#0D5A8E;font-weight:600;cursor:pointer;height:26px;}
.fsearch{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;height:26px;width:200px;outline:none;}
</style>
@endpush

@section('content')
@php $hasSearch = request()->filled('search_name') || request()->filled('search_code') || request()->filled('status') || request()->filled('show_all'); @endphp
<div class="nw-wrap">

    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ route('gl.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</span>
        @if($hasSearch)<span style="color:#718096;">{{ __('gl.count_role_paren', ['count' => $tls->total(), 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</span>@endif
        <form method="GET" style="display:flex;align-items:center;gap:8px;margin-left:8px;flex-wrap:wrap;">
            {{-- Show All button first --}}
            <button type="submit" name="show_all" value="1" style="background:#38A169;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;font-weight:600;">{{ __('network.show_all_button') }}</button>
            <span style="color:#718096;font-size:10px;">{{ __('gl.or_filter_label') }}</span>
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ __('network.name_label', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}</span>
                <input type="text" name="search_name" class="fsearch" placeholder="e.g. Tan Boon Wah" value="{{ request('search_name') }}" style="width:150px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }} {{ __('network.col_code') }}</span>
                <input type="text" name="search_code" class="fsearch" placeholder="e.g. 1-3" value="{{ request('search_code') }}" style="width:100px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ __('network.status') }}</span>
                <select name="status" class="fsel" style="width:120px;">
                    <option value="">{{ __('network.all_status_option') }}</option>
                    <option value="ACTIVE" {{ request('status')==='ACTIVE'?'selected':'' }}>{{ __('network.active') }}</option>
                    <option value="INACTIVE" {{ request('status')==='INACTIVE'?'selected':'' }}>{{ __('network.inactive') }}</option>
                    <option value="TERMINATED" {{ request('status')==='TERMINATED'?'selected':'' }}>{{ __('network.terminated') }}</option>
                </select>
            </div>

            <button type="submit" style="background:#1B9AE4;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;margin-top:10px;">{{ __('network.go_button') }}</button>
            <a href="{{ route('gl.network.all-tls') }}" style="background:#f3f4f6;border:1px solid #E2E8F0;color:#4A5568;border-radius:5px;padding:3px 8px;font-size:10px;text-decoration:none;margin-top:10px;">{{ __('network.clear') }}</a>
        </form>

    </div>

    <div class="tbl-wrap">
        @if(!$hasSearch)
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:11px;display:flex;flex-direction:column;align-items:center;gap:8px;">
                <div style="font-size:28px;">🔍</div>
                <div style="font-weight:600;color:#374151;">{{ __('gl.select_search_method_note') }}</div>
                <div style="font-size:10px;color:#718096;">{{ __('gl.choose_by_name_or_code_note') }}</div>
            </div>
        @elseif($tls->count() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:11px;">{{ __('network.no_role_found', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</div>
        @else
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:22%;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</th>
                    <th style="width:10%;">{{ __('network.col_code') }}</th>
                    <th style="text-align:center;width:8%;">{{ \App\Services\RoleLabelService::shortLabel('INTRODUCER') }}s</th>
                    <th style="text-align:right;width:18%;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="text-align:right;width:16%;">{{ __('gl.col_earning_income_rm') }}</th>
                    <th style="text-align:center;width:8%;">{{ __('network.col_status') }}</th>
                    <th style="width:10%;">{{ __('network.col_joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tls as $tl)
                @php $rank = $loop->iteration; @endphp
                <tr onclick="window.location='{{ route('gl.network.tl', $tl->agent_id) }}?month={{ $month }}&year={{ $year }}&from=all-tls&search_name={{ request('search_name') }}&search_code={{ request('search_code') }}&status={{ request('status') }}&show_all={{ request('show_all') }}'">
                    <td><span style="color:#718096;">{{ $rank }}</span></td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $tl->full_name }}</td>
                    <td style="color:#718096;">{{ $tl->agent_code }}</td>
                    <td style="text-align:center;font-weight:600;color:#7C3AED;">{{ number_format($tl->total_intro) }}</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($tl->total_sales,2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($tl->total_earn,2) }}</td>
                    <td style="text-align:center;">
                        <span style="padding:2px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $tl->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $tl->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ $tl->status }}</span>
                    </td>
                    <td style="color:#718096;">{{ \Carbon\Carbon::parse($tl->created_at)->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="nav-bar">
        <a href="{{ route('gl.dashboard') }}" class="nav-btn">{{ __('network.back_dashboard') }}</a>
        <span style="font-size:10px;color:#374151;font-weight:600;">
            @if($hasSearch && $tls->hasPages()) {{ __('gl.page_label', ['current' => $tls->currentPage(), 'last' => $tls->lastPage()]) }} @endif
        </span>
        @if($hasSearch && $tls->hasMorePages())
            <a href="{{ $tls->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>
</div>
@endsection
