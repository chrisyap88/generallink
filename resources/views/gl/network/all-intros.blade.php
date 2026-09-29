@extends('layouts.dashboard')
@section('title', __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]))
@section('page-title', __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]))

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
.fsearch{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;height:26px;width:160px;outline:none;}
</style>
@endpush

@section('content')
@php $hasFilter = request()->filled('search_name') || request()->filled('search_code') || request()->filled('status') || request()->filled('show_all'); @endphp
<div class="nw-wrap">

    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ route('gl.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ __('network.all_role_plain', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</span>
        @if($hasFilter)<span style="color:#718096;">{{ __('gl.count_role_paren', ['count' => $intros->total(), 'role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</span>@endif
        <form method="GET" style="display:flex;align-items:center;gap:8px;margin-left:8px;flex-wrap:wrap;">
            <button type="submit" name="show_all" value="1" style="background:#38A169;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;font-weight:600;">{{ __('network.show_all_button') }}</button>
            <span style="color:#718096;font-size:10px;">{{ __('gl.or_filter_label') }}</span>
            {{-- Intro fields first --}}
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ __('network.name_label', ['role' => \App\Services\RoleLabelService::shortLabel('INTRODUCER')]) }}</span>
                <input type="text" name="search_name" class="fsearch" placeholder="e.g. Wendy Koh" value="{{ request('search_name') }}" style="width:130px;">
            </div>
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ \App\Services\RoleLabelService::shortLabel('INTRODUCER') }} {{ __('network.col_code') }}</span>
                <input type="text" name="search_code" class="fsearch" placeholder="e.g. 1-4-1" value="{{ request('search_code') }}" style="width:90px;">
            </div>
            {{-- TL search --}}
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</span>
                <div style="display:flex;gap:4px;">
                    <select name="tl_search_by" class="fsel" style="width:90px;" onchange="toggleTLSearch(this.value)">
                        <option value="all" {{ !request('tl_search_by')||request('tl_search_by')==='all'?'selected':'' }}>{{ __('gl.all_option') }}</option>
                        <option value="name" {{ request('tl_search_by')==='name'?'selected':'' }}>{{ __('gl.by_name_option') }}</option>
                        <option value="code" {{ request('tl_search_by')==='code'?'selected':'' }}>{{ __('gl.by_code_option') }}</option>
                    </select>
                    <input type="text" name="tl_search" id="tlSearchInput" class="fsearch"
                        placeholder="{{ request('tl_search_by')==='code'?'e.g. 1-3':__('gl.role_name_dots_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}"
                        value="{{ request('tl_search') }}"
                        style="width:120px;display:{{ request('tl_search_by')&&request('tl_search_by')!=='all'?'block':'none' }};">
                </div>
            </div>
            {{-- Status --}}
            <div style="display:flex;flex-direction:column;gap:1px;">
                <span style="font-size:9px;color:#718096;">{{ __('network.status') }}</span>
                <select name="status" class="fsel" style="width:110px;">
                    <option value="">{{ __('network.all_status_option') }}</option>
                    <option value="ACTIVE" {{ request('status')==='ACTIVE'?'selected':'' }}>{{ __('network.active') }}</option>
                    <option value="INACTIVE" {{ request('status')==='INACTIVE'?'selected':'' }}>{{ __('network.inactive') }}</option>
                    <option value="TERMINATED" {{ request('status')==='TERMINATED'?'selected':'' }}>{{ __('network.terminated') }}</option>
                </select>
            </div>
            <button type="submit" style="background:#1B9AE4;color:#fff;border:none;border-radius:5px;padding:3px 12px;font-size:10px;cursor:pointer;height:26px;margin-top:10px;">{{ __('network.go_button') }}</button>
            <a href="{{ route('gl.network.all-intros') }}" style="background:#f3f4f6;border:1px solid #E2E8F0;color:#4A5568;border-radius:5px;padding:3px 8px;font-size:10px;text-decoration:none;margin-top:10px;">{{ __('network.clear') }}</a>
        </form>
    </div>

    <div class="tbl-wrap">
        @if($intros->count() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:11px;">{{ __('network.no_role_found', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</div>
        @else
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:35px;">#</th>
                    <th style="width:16%;">{{ \App\Services\RoleLabelService::label('INTRODUCER') }}</th>
                    <th style="width:8%;">{{ __('network.col_code') }}</th>
                    <th style="width:12%;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</th>
                    <th style="width:7%;">{{ \App\Services\RoleLabelService::shortLabel('TEAM_LEADER') }} {{ __('network.col_code') }}</th>
                    <th style="width:12%;">{{ \App\Services\RoleLabelService::label('GROUP_LEADER') }}</th>
                    <th style="width:6%;">{{ \App\Services\RoleLabelService::shortLabel('GROUP_LEADER') }} {{ __('network.col_code') }}</th>
                    <th style="text-align:right;width:12%;">{{ __('network.col_sales_mtd') }}</th>
                    <th style="text-align:right;width:11%;">{{ __('gl.col_earning_rm') }}</th>
                    <th style="text-align:center;width:6%;">{{ __('network.col_txns') }}</th>
                    <th style="text-align:center;width:7%;">{{ __('network.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @php $prevTlId = null; $prevGlCode = null; @endphp
                @foreach($intros as $intro)
                @php
                    $rank = $loop->iteration;
                    $showTl = ($intro->tl_id !== $prevTlId);
                    $showGl = ($intro->gl_code !== $prevGlCode);
                    $prevTlId = $intro->tl_id;
                    $prevGlCode = $intro->gl_code;
                @endphp
                <tr onclick="window.location='{{ route('gl.network.intro.transactions', $intro->agent_id) }}?month={{ $month }}&year={{ $year }}&from=all-intros&search_name={{ request('search_name') }}&search_code={{ request('search_code') }}&tl_search={{ request('tl_search') }}&tl_search_by={{ request('tl_search_by') }}&status={{ request('status') }}&show_all={{ request('show_all') }}'">
                    <td><span style="color:#718096;">{{ $rank }}</span></td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $intro->full_name }}</td>
                    <td style="color:#718096;">{{ $intro->agent_code }}</td>
                    <td style="color:#0D5A8E;">@if($showTl){{ $intro->tl_name ?? '—' }}@endif</td>
                    <td style="color:#718096;">@if($showTl){{ $intro->tl_code ?? '—' }}@endif</td>
                    <td style="color:#1B5E20;">@if($showGl){{ $intro->gl_name ?? '—' }}@endif</td>
                    <td style="color:#718096;">@if($showGl){{ $intro->gl_code ?? '—' }}@endif</td>
                    <td style="text-align:right;font-weight:700;color:#0D5A8E;">RM {{ number_format($intro->total_sales,2) }}</td>
                    <td style="text-align:right;color:#1B5E20;font-weight:600;">RM {{ number_format($intro->total_earn,2) }}</td>
                    <td style="text-align:center;">{{ number_format($intro->total_transactions) }}</td>
                    <td style="text-align:center;">
                        <span style="padding:2px 6px;border-radius:20px;font-size:9px;font-weight:600;background:{{ $intro->status==='ACTIVE'?'#C8E6C9':'#FFCDD2' }};color:{{ $intro->status==='ACTIVE'?'#1B5E20':'#B71C1C' }};">{{ $intro->status }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    <div class="nav-bar">
        @if(!$intros->onFirstPage())
            <a href="{{ $intros->previousPageUrl() }}" class="nav-btn">{{ __('network.prev') }}</a>
        @else
            <a href="{{ route('gl.dashboard') }}" class="nav-btn">{{ __('network.back_dashboard') }}</a>
        @endif
        <span style="font-size:10px;color:#374151;font-weight:600;">@if($hasFilter && $intros->hasPages()) {{ __('gl.page_label', ['current' => $intros->currentPage(), 'last' => $intros->lastPage()]) }} @endif</span>
        @if($hasFilter && $intros->hasMorePages())<a href="{{ $intros->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>@else<span class="nav-btn-ghost"></span>@endif
    </div>
</div>
<script>
function toggleTLSearch(val){
    var inp=document.getElementById('tlSearchInput');
    if(val==='all'||!val){inp.style.display='none';inp.value='';}
    else{inp.style.display='block';inp.placeholder=val==='code'?'e.g. 1-3':@json(__('gl.role_name_dots_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]));}
}
</script>
@endsection
