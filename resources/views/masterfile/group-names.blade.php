@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@php
    // NEW 17 Aug 2026 — per Chris: reached from the sidebar's Group Set
    // Up > Direct Selling Group / Organization Rewards Group / Community
    // & Business Enterprise Group links, this screen now knows which
    // type it's scoped to (filtered server-side in GroupLabelController@
    // index — DSG only ever shows DSG groups, ORG only ORG, etc.).
    //
    // FIXED 17 Aug 2026 — per Chris: Group Set Up has no separate page of
    // its own anymore (it's a pure sidebar sub-menu), so Prev no longer
    // points at the old abandoned hub-page routes. It now goes back to
    // this same screen's plain, unfiltered landing (Add / Search) —
    // i.e. back to "Group Name Set Up", not the main dashboard.
    $typeLabels = ['DSG' => __('sidebar.direct_selling_group_item'), 'ORG' => __('sidebar.organization_rewards_group_item'), 'CBE' => __('sidebar.cbe_group_item')];
    $typeBackRoute = isset($type) && $type ? 'admin.masterfile.group-names' : null;
@endphp

@section('page-title', isset($type) ? __('masterfile.group_name_dash', ['type' => $typeLabels[$type] ?? $type]) : __('masterfile.group_name_maintenance'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:4px 16px 8px; box-sizing:border-box; display:flex; flex-direction:column;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ session('success') }}</div>
    @endif

    @if(!$hasAnyFilter)

    {{-- CHANGED 24 Sep 2026 -- per Chris: arriving here scoped to a
         specific group type (DSG/ORG/CBE, via the sidebar's Membership
         Master File links) should never offer a jump straight out to
         the main admin dashboard -- that abandons the Membership
         Master File context the sidebar is already showing, and
         breaks the no-jump-screens rule. The plain, unscoped landing
         (reached some other way, with no $type) still shows it since
         there is nothing else to return to from there. --}}
    @if(!(isset($type) && $type))
    <div style="margin-bottom:6px;">
        <a href="{{ route('admin.dashboard') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('masterfile.dashboard_link') }}</a>
    </div>
    @endif

    {{-- CLEAN LANDING — just the two options, no table --}}
    <div style="display:flex; gap:10px;">
        <a href="{{ route('admin.masterfile.group-names.create') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.add_new_group_name') }}</a>
        <a href="{{ route('admin.masterfile.group-names.search', isset($type) && $type ? ['type' => $type] : []) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

    @else

    {{-- RESULTS VIEW — appears only after an actual search, or
         immediately when arriving with ?type= from Group Set Up. --}}
    <div style="margin-bottom:6px; display:flex; align-items:center; gap:14px;">
        <a href="{{ $typeBackRoute ? route($typeBackRoute) : route('admin.masterfile.group-names.search', request('type') ? ['type' => request('type')] : []) }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">&larr; {{ $typeBackRoute ? __('masterfile.prev_group_name_setup') : __('masterfile.modify_search_plain') }}</a>
        <a href="{{ route('admin.masterfile.group-names.create', isset($type) && $type ? ['type' => $type] : []) }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('masterfile.add_new_group_name') }}</a>
    </div>

    <div id="gnl-table-wrap" style="flex:1; min-height:0; overflow:hidden;">
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table id="gnl-table" style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_group_name') }}</th>
                    {{-- CHANGED 26 Sep 2026 -- per Chris: no Type column when the list is already scoped to one type (e.g. CBE). --}}
                    @if(empty($type))
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:60px;">{{ __('masterfile.col_type') }}</th>
                    @endif
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groupLabels as $g)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; color:#9ca3af;">{{ $groupLabels->firstItem() + $loop->index }}</td>
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0; white-space:nowrap; width:100%;">{{ $g->group_name }}</td>
                    @if(empty($type))
                    <td style="padding:5px 8px; color:#4b5563;">{{ $g->group_type ?? 'DSG' }}</td>
                    @endif
                    <td style="padding:5px 8px;">
                        <a href="{{ route('admin.masterfile.group-names.edit', $g->group_label_id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('masterfile.view') }}</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ empty($type) ? 4 : 3 }}" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('masterfile.no_group_names_match') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>

    @if($groupLabels instanceof \Illuminate\Pagination\LengthAwarePaginator && $groupLabels->total() > 0)
    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($groupLabels->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $groupLabels->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $groupLabels->firstItem(), 'last' => $groupLabels->lastItem(), 'total' => $groupLabels->total()]) }} &nbsp;|&nbsp; {{ __('masterfile.page_of', ['current' => $groupLabels->currentPage(), 'last' => $groupLabels->lastPage()]) }}</span>
        @if($groupLabels->hasMorePages())
            <a href="{{ $groupLabels->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>
@if($hasAnyFilter && $groupLabels instanceof \Illuminate\Pagination\LengthAwarePaginator && $groupLabels->total() > 0)
{{-- NEW 26 Sep 2026 — per Chris: Prev/Next always at the bottom, no
     scroll. Measures how many rows fit and reloads with that many. --}}
<script>
(function(){
    var wrap = document.getElementById('gnl-table-wrap'), table = document.getElementById('gnl-table');
    if (!wrap || !table) return;
    var perPage = {{ $groupLabels->perPage() }}, first = {{ $groupLabels->firstItem() ?? 1 }}, total = {{ $groupLabels->total() }};
    function apply(){
        var rows = table.querySelectorAll('tbody tr'); if (!rows.length) return;
        var head = table.querySelector('thead'), maxH = 0;
        rows.forEach(function(r){ maxH = Math.max(maxH, r.offsetHeight); });
        var fit = Math.max(3, Math.min(50, Math.floor((wrap.clientHeight - (head ? head.offsetHeight : 0) - 4) / maxH)));
        if (fit === perPage) return;
        if (table.offsetHeight <= wrap.clientHeight && total <= perPage) return;
        var url = new URL(window.location.href);
        url.searchParams.set('per_page', fit);
        url.searchParams.set('page', Math.floor((first - 1) / fit) + 1);
        window.location.replace(url.toString());
    }
    apply();
    var t; window.addEventListener('resize', function(){ clearTimeout(t); t = setTimeout(apply, 400); });
})();
</script>
@endif
@endsection
