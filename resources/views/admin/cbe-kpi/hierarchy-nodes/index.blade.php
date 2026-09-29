@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.node_title'))

@section('content')
{{-- REBUILT 26 Sep 2026 — per Chris: Entity Maintenance Search / View /
     Edit of ONE entity at a time. Separate search boxes (City, Postcode
     From-To, Entity Code From-To, Entity Name) + GO; nothing listed until
     GO (filter-first). Linking entities under a parent is a separate
     program: Entity Hierarchy Link. --}}
<div style="height:calc(100vh - 46px); overflow:hidden; padding:8px 16px; box-sizing:border-box; display:flex; flex-direction:column; gap:7px;">

    <div style="flex-shrink:0;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_masterfile.node_title') }} — {{ __('masterfile.search_view_edit') }}</div>
    </div>

    @if(session('cbe_node_saved'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.node_saved') }} ({{ session('cbe_node_saved') }})</div>
    @endif

    <form method="GET" action="{{ route('admin.cbe-kpi.hierarchy-nodes.index') }}" style="flex-shrink:0; margin:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 10px;">
        @include('admin.cbe-kpi.hierarchy-nodes._search-boxes', ['tierBoxes' => true, 'hideCodeBox' => true])
    </form>

    <div id="hn-table-wrap" style="flex:1; min-height:0; overflow:hidden;">
        @if(! $searched)
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:24px; text-align:center; color:#94A3B8; font-size:10px;">{{ $group ? __('cbe_masterfile.search_first_go') : __('cbe_masterfile.pick_group_first') }}</div>
        @else
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
            <table id="hn-table" style="width:100%; border-collapse:collapse; font-size:12px;">
                {{-- CHANGED 27 Sep 2026 — per Chris: # | Entity Name | Contact Person | Contact Number | City | Postcode | View / Edit (postcode order, last level only). --}}
                <thead>
                    <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap; width:30px;">#</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;">{{ __('cbe_masterfile.cov_col_entity') }}</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;">{{ __('cbe_masterfile.col_contact_person') }}</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;">{{ __('cbe_masterfile.col_contact_number') }}</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;">{{ __('cbe_masterfile.form_city') }}</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;">{{ __('cbe_masterfile.form_postcode') }}</th>
                        <th style="text-align:left; padding:0.4em 0.6em; font-size:0.9em; color:#374151; white-space:nowrap;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nodes as $n)
                    <tr style="border-bottom:1px solid #f3f4f6; white-space:nowrap;">
                        <td style="padding:0.4em 0.6em; color:#9ca3af;">{{ $nodes->firstItem() + $loop->index }}</td>
                        <td style="padding:0.4em 0.6em; font-weight:600; color:#1565C0;">{{ $n->node_name }}@if($n->node_name_zh) <span style="color:#6b7280; font-weight:400;">{{ $n->node_name_zh }}</span>@endif</td>
                        <td style="padding:0.4em 0.6em; color:#4b5563;">{{ $n->contact_person_1 ?: ($n->contact_person ?: '—') }}</td>
                        <td style="padding:0.4em 0.6em; color:#4b5563;">{{ $n->first_phone ?: ($n->contact_phone ?: '—') }}</td>
                        <td style="padding:0.4em 0.6em; color:#4b5563;">{{ $n->city ?: '—' }}</td>
                        <td style="padding:0.4em 0.6em; color:#4b5563;">{{ $n->postcode ?: '—' }}</td>
                        <td style="padding:0.4em 0.6em;"><a href="{{ route('admin.cbe-kpi.hierarchy-nodes.edit', ['node' => $n->node_id, 'return' => request()->fullUrl()]) }}" style="color:#1565C0; text-decoration:none; font-weight:700;">{{ __('cbe_masterfile.btn_view_edit') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('admin_cbe_directory.no_results') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Prev on page 1: back to choosing the CBE group (officer: dashboard). --}}
    {{-- CHANGED 27 Sep 2026 — Prev: search results page 1 -> search boxes; search boxes -> Add | Search landing. --}}
    @php($landingUrl = $searched ? route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $group->group_label_id]) : route('admin.cbe-kpi.hierarchy-nodes.create'))
    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        <a href="{{ ($nodes && ! $nodes->onFirstPage()) ? $nodes->previousPageUrl() : $landingUrl }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        <span style="font-size:10.5px; color:#4b5563;">@if($nodes && $nodes->total() > 0){{ __('masterfile.showing_records', ['first' => $nodes->firstItem(), 'last' => $nodes->lastItem(), 'total' => $nodes->total()]) }}@endif</span>
        @if($nodes && $nodes->hasMorePages())
            <a href="{{ $nodes->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700; cursor:default;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
</div>

@if($searched && $nodes && $nodes->total() > 0)
<script>
(function(){
    // Fit rows to the screen (no scroll): reload with as many rows as fit.
    var wrap = document.getElementById('hn-table-wrap'), table = document.getElementById('hn-table');
    var perPage = {{ $nodes->perPage() }}, first = {{ $nodes->firstItem() ?? 1 }}, total = {{ $nodes->total() }};
    // one line per row: shrink the table font together if it is wider than the box
    (function(){ var fs = parseFloat(getComputedStyle(table).fontSize); while (table.scrollWidth > wrap.clientWidth + 1 && fs > 6.5) { fs -= 0.25; table.style.fontSize = fs + 'px'; } })();
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
    var r; window.addEventListener('resize', function(){ clearTimeout(r); r = setTimeout(apply, 400); });
})();
</script>
@endif
@endsection
