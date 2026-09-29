@extends('layouts.dashboard')
@section('title', __('network.inactive_role', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]))
@section('page-title', __('network.inactive_role', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]))

@push('styles')
<style>
.nw-wrap{padding:4px 6px;display:flex;flex-direction:column;gap:4px;height:100%;box-sizing:border-box;overflow:hidden;background:#fff;}
.nw-table{width:100%;border-collapse:collapse;font-size:10px;table-layout:fixed;}
.nw-table th{padding:2px 6px;text-align:left;border-bottom:1px solid #E2E8F0;color:#0D5A8E;font-weight:700;font-size:10px;white-space:nowrap;background:#F7FAFC;}
.nw-table td{padding:2px 6px;border-bottom:1px solid #F7FAFC;white-space:nowrap;font-size:10px;overflow:hidden;text-overflow:ellipsis;}
.nw-table tr:hover td{background:#EBF5FB;}
.tbl-wrap{background:#fff;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;flex:1;min-height:0;}
.nav-bar{display:flex;align-items:center;justify-content:space-between;padding:3px 0;flex-shrink:0;}
.nav-btn{background:#1565C0;color:#fff;border-radius:5px;padding:4px 14px;font-size:10px;font-weight:600;text-decoration:none;display:inline-block;}
.nav-btn-ghost{width:70px;display:inline-block;}
.fsearch{font-size:10px;padding:3px 8px;border-radius:6px;border:1px solid #B2EBF2;background:#fff;height:26px;width:200px;outline:none;}
</style>
@endpush

@section('content')
<div class="nw-wrap">
    <div style="display:flex;align-items:center;gap:8px;font-size:10px;flex-shrink:0;">
        <a href="{{ route('gl.dashboard') }}" style="color:#1B9AE4;text-decoration:none;">{{ __('network.back_dashboard') }}</a>
        <span style="color:#718096;">›</span>
        <span style="color:#0D5A8E;font-weight:700;">{{ __('network.inactive_role', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</span>
        <span style="color:#718096;">{{ __('gl.count_role_paren', ['count' => $tls->total(), 'role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</span>
        <form method="GET" style="display:flex;align-items:center;gap:6px;margin-left:8px;">
            <input type="text" name="search" class="fsearch" placeholder="{{ __('gl.search_role_name_or_code_placeholder', ['role' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}" value="{{ $search }}">
            <button type="submit" style="background:#1B9AE4;color:#fff;border:none;border-radius:5px;padding:3px 10px;font-size:10px;cursor:pointer;height:26px;">{{ __('network.go_button') }}</button>
            @if($search)<a href="{{ route('gl.network.inactive-tls') }}" style="background:#f3f4f6;border:1px solid #E2E8F0;color:#4A5568;border-radius:5px;padding:3px 8px;font-size:10px;text-decoration:none;">{{ __('network.clear') }}</a>@endif
        </form>
    </div>
    <div class="tbl-wrap">
        @if($tls->count() === 0)
            <div style="text-align:center;color:#A0AEC0;padding:30px;font-size:11px;">{{ __('gl.no_inactive_role_found', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</div>
        @else
        <table class="nw-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th style="width:30%;">{{ \App\Services\RoleLabelService::label('TEAM_LEADER') }}</th>
                    <th style="width:12%;">{{ __('network.col_code') }}</th>
                    <th style="text-align:center;width:10%;">{{ __('network.col_status') }}</th>
                    <th style="width:15%;">{{ __('network.col_joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($tls as $tl)
                @php $rank = $loop->iteration; @endphp
                <tr>
                    <td style="color:#718096;">{{ $rank }}</td>
                    <td style="font-weight:600;color:#0D5A8E;">{{ $tl->full_name }}</td>
                    <td style="color:#718096;">{{ $tl->agent_code }}</td>
                    <td style="text-align:center;">
                        <span style="padding:2px 6px;border-radius:20px;font-size:9px;font-weight:600;background:#FFCDD2;color:#B71C1C;">{{ $tl->status }}</span>
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
            @if($tls->hasPages()) {{ __('gl.page_label', ['current' => $tls->currentPage(), 'last' => $tls->lastPage()]) }} @endif
        </span>
        @if($tls->hasMorePages())
            <a href="{{ $tls->nextPageUrl() }}" class="nav-btn">{{ __('network.next') }}</a>
        @else
            <span class="nav-btn-ghost"></span>
        @endif
    </div>
</div>
@endsection
