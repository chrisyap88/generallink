@extends('layouts.dashboard')

@section('page-title', __('growth.badge_detail_title'))

@section('content')

{{-- NEW 25 Jul 2026 — per Chris: "must have drill down to know who what
     all the detail and navigation method prev and next and MUST show as
     in one screen." One category's underlying records, paginated. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <h4 style="font-weight:700; margin:0; font-size:13px; color:#1565C0;">{{ $typeLabel }}</h4>
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('growth.total_count', ['count' => $rows->total()]) }}</div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.role') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('growth.when') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#111827;">{{ $r->full_name }} <span style="color:#9ca3af;">({{ $r->agent_code }})</span></td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ \App\Services\RoleLabelService::label($r->role) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($r->when)->format('d M Y') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('growth.nothing_here_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
            @if($rows->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.prev') }}</a>
            @endif
            <span style="font-size:9px; color:#6b7280;">{{ __('growth.page_of', ['current' => $rows->currentPage(), 'last' => $rows->lastPage()]) }}</span>
            @if($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('growth.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
