@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', \App\Services\RoleLabelService::label('INTRODUCER') . ' Maintenance')

@section('content')
@php
    // NEW 15 Jul 2026 — this view is shared by Admin/GL/TL/Introducer
    // (each reached via their own route prefix). Every internal link
    // used to hardcode 'admin.masterfile...', which 403'd for anyone
    // who wasn't Admin the moment they clicked past the landing page.
    $rolePrefix = match(auth('agent')->user()->role) {
        'ADMIN' => 'admin', 'GROUP_LEADER' => 'gl', 'TEAM_LEADER' => 'tl', default => 'introducer',
    };
    $hasAnyFilter = request('search') || request('status') || request('name') || request('agent_code')
        || request('phone') || request('email') || request('address') || request('postcode')
        || request('city') || request('state') || request('bank_name') || request('sponsor_id')
        || request('joined_from') || request('joined_to') || request('recruitment_blocked') !== null;

    $sponsorName = null;
    if (request('sponsor_id')) {
        $sponsorAgent = \App\Models\Agent::find(request('sponsor_id'));
        $sponsorName = $sponsorAgent->full_name ?? null;
    }

    $criteria = [];
    if (request('name')) $criteria[] = 'Name = "' . request('name') . '"';
    if (request('agent_code')) $criteria[] = 'Agent Code = "' . request('agent_code') . '"';
    if (request('phone')) $criteria[] = 'Phone = "' . request('phone') . '"';
    if (request('email')) $criteria[] = 'Email = "' . request('email') . '"';
    if (request('address')) $criteria[] = 'Address = "' . request('address') . '"';
    if (request('postcode')) $criteria[] = 'Postcode = "' . request('postcode') . '"';
    if (request('city')) $criteria[] = 'City = "' . request('city') . '"';
    if (request('state')) $criteria[] = 'State = "' . request('state') . '"';
    if (request('bank_name')) $criteria[] = 'Bank = "' . request('bank_name') . '"';
    if ($sponsorName) $criteria[] = 'Sponsor = "' . $sponsorName . '"';
    if (request('status')) $criteria[] = 'Status = ' . request('status');
    if (request('joined_from')) $criteria[] = 'Joined From ' . request('joined_from');
    if (request('joined_to')) $criteria[] = 'Joined To ' . request('joined_to');
    if (request('recruitment_blocked') !== null && request('recruitment_blocked') !== '') $criteria[] = 'Recruitment Blocked = ' . (request('recruitment_blocked') == '1' ? 'Yes' : 'No');
    if (request('search')) $criteria[] = 'Quick Search = "' . request('search') . '"';
@endphp
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:4px 16px; box-sizing:border-box;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    @if(!$hasAnyFilter)

    <div style="margin-bottom:6px;">
        <a href="{{ route($rolePrefix . '.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.dashboard_link') }}</a>
    </div>

    {{-- CLEAN LANDING — just the two options, no table --}}
    <div style="display:flex; gap:10px;">
        @if($rolePrefix === 'admin')
        <a href="{{ route('admin.masterfile.introducers.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.add_new', ['role' => \App\Services\RoleLabelService::label('INTRODUCER')]) }}</a>
        @endif
        <a href="{{ route($rolePrefix . '.masterfile.introducers.search') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

    @else

    {{-- RESULTS VIEW — appears only after an actual search --}}
    <div style="margin-bottom:4px;">
        <a href="{{ route($rolePrefix . '.masterfile.introducers.search') }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('masterfile.modify_search') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table style="width:100%; table-layout:fixed; border-collapse:collapse; font-size:12px;">
            <colgroup>
                <col style="width:4%;">
                <col style="width:10%;">
                <col style="width:22%;">
                <col style="width:30%;">
                <col style="width:16%;">
                <col style="width:8%;">
            </colgroup>
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">#</th>
                    <th style="text-align:left; padding:4px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.agent_code') }}</th>
                    <th style="text-align:left; padding:4px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.full_name') }}</th>
                    <th style="text-align:left; padding:4px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.email') }}</th>
                    <th style="text-align:left; padding:4px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.phone') }}</th>
                    <th style="text-align:left; padding:4px 10px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($introducers as $intro)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:4px 8px; color:#9ca3af; font-size:11px;">{{ $introducers->firstItem() + $loop->index }}</td>
                    <td style="padding:4px 10px; font-weight:600; color:#1565C0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $intro->agent_code ?? '—' }}</td>
                    <td style="padding:4px 10px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $intro->full_name }}</td>
                    <td style="padding:4px 10px; color:#4b5563; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $intro->email }}</td>
                    <td style="padding:4px 10px; color:#4b5563; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $intro->phone }}</td>
                    <td style="padding:4px 10px; overflow:hidden; white-space:nowrap;">
                        <a href="{{ route($rolePrefix . '.masterfile.introducers.edit', $intro->agent_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('masterfile.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('masterfile.no_match', ['role' => \App\Services\RoleLabelService::plural('INTRODUCER')]) }}</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($introducers instanceof \Illuminate\Pagination\LengthAwarePaginator && $introducers->total() > 0)
    <div style="display:flex; align-items:center; justify-content:space-between; margin-top:4px; padding:5px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($introducers->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:12px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $introducers->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 16px; font-size:12px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif

        <span style="font-size:11.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $introducers->firstItem(), 'last' => $introducers->lastItem(), 'total' => $introducers->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $introducers->currentPage(), 'last' => $introducers->lastPage()]) }}</span>

        @if($introducers->hasMorePages())
            <a href="{{ $introducers->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 16px; font-size:12px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 16px; font-size:12px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>
@endsection
