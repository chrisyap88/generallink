@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.pending_verifications_title'))

@section('content')
@php
    $rolePrefix = match(auth('agent')->user()->role) {
        'ADMIN' => 'admin', 'GROUP_LEADER' => 'gl', 'TEAM_LEADER' => 'tl', default => 'introducer',
    };
    $tierRouteMap = [
        'INTRODUCER'   => 'masterfile.introducers.edit',
        'TEAM_LEADER'  => 'masterfile.team-leaders.edit',
        'GROUP_LEADER' => 'masterfile.group-leaders.edit',
    ];
@endphp
{{-- Fixed viewport, no scroll — same rule as every other screen.
     table-layout:fixed with percentage widths guarantees the table
     itself never exceeds 100% width (no horizontal scroll), and
     pagination is capped at 8/page (see controller) so it never
     exceeds one screen height either. --}}
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:4px;">
        <a href="{{ route($rolePrefix . '.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10px; font-weight:600;">{{ __('masterfile.dashboard_link') }}</a>
    </div>

    <div style="font-size:10px; color:#718096; margin-bottom:6px;">{{ __('masterfile.pending_verifications_intro') }}</div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10.5px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    @if(session('error'))
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10.5px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden; flex:1; min-height:0;">
        <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:11px;">
            <colgroup>
                <col style="width:3%;">
                <col style="width:9%;">
                <col style="width:15%;">
                <col style="width:10%;">
                <col style="width:22%;">
                <col style="width:14%;">
                <col style="width:9%;">
                <col style="width:18%;">
            </colgroup>
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">#</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_code') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_full_name') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_role') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.email') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_group') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_registered') }}</th>
                    <th style="text-align:left; padding:5px 6px; font-size:10px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pending as $p)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:4px 6px; color:#9ca3af; overflow:hidden; text-overflow:ellipsis;">{{ $pending->firstItem() + $loop->index }}</td>
                    <td style="padding:4px 6px; font-weight:600; color:#1565C0; overflow:hidden; text-overflow:ellipsis;">{{ $p->agent_code ?? '—' }}</td>
                    <td style="padding:4px 6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->full_name }}</td>
                    <td style="padding:4px 6px; color:#4b5563; overflow:hidden; text-overflow:ellipsis;" title="{{ \App\Services\RoleLabelService::label($p->role) }}">{{ ['INTRODUCER'=>\App\Services\RoleLabelService::label('INTRODUCER'),'TEAM_LEADER'=>\App\Services\RoleLabelService::label('TEAM_LEADER'),'GROUP_LEADER'=>\App\Services\RoleLabelService::label('GROUP_LEADER')][$p->role] ?? $p->role }}</td>
                    <td style="padding:4px 6px; color:#4b5563; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->email }}</td>
                    <td style="padding:4px 6px; color:#4b5563; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->group_label_name ?? 'GeneralLink' }}</td>
                    <td style="padding:4px 6px; color:#4b5563; white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($p->created_at)->format('d/m/y') }}</td>
                    <td style="padding:4px 6px; white-space:nowrap;">
                        @if(isset($tierRouteMap[$p->role]))
                        <a href="{{ route($rolePrefix . '.' . $tierRouteMap[$p->role], $p->agent_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600; margin-right:6px; font-size:10.5px;">{{ __('masterfile.view_link') }}</a>
                        @endif
                        <form method="POST" action="{{ route($rolePrefix . '.masterfile.resend-verification', $p->agent_id) }}" onsubmit="return confirm({{ json_encode(__('masterfile.resend_verification_confirm', ['email' => $p->email])) }});" style="display:inline;">
                            @csrf
                            <button type="submit" style="background:#D97706; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.resend_button') }}</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="padding:20px; text-align:center; color:#9ca3af; font-size:11px;">{{ __('masterfile.no_pending_verifications') }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pending instanceof \Illuminate\Pagination\LengthAwarePaginator && $pending->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:4px; padding:4px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px; flex-shrink:0;">
        @if($pending->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $pending->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif

        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $pending->firstItem(), 'last' => $pending->lastItem(), 'total' => $pending->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $pending->currentPage(), 'last' => $pending->lastPage()]) }}</span>

        @if($pending->hasMorePages())
            <a href="{{ $pending->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

</div>
@endsection
