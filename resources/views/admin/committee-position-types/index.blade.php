@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_committee_types.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:4px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    @if(session('cbe_committee_type_saved'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:8px;">{{ __('admin_committee_types.saved') }}</div>
    @endif

    {{-- NEW 24 Sep 2026 -- per Chris: "different cbe may have different
         position... follow the method how we set up rank in org group."
         This picker decides which CBE group's positions the rest of the
         screen shows -- same GET-submitted picker pattern as the Role
         Ranks master file (there scoped to ORG groups; here to CBE). --}}
    <form method="GET" action="{{ route('admin.committee-position-types.index') }}" style="flex-shrink:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; margin-bottom:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        @if($hasAnyFilter)<input type="hidden" name="search" value="{{ request('search') }}"><input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        <label style="font-size:10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('admin_committee_types.group_picker_label') }}</label>
        <select name="group_label_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:11.5px; background:#fff; min-width:220px;">
            @foreach($cbeGroups as $g)
            <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>
        <span style="font-size:9px; color:#94A3B8;">{{ __('admin_committee_types.group_helper') }}</span>
    </form>

    @if(!$hasAnyFilter)

    <div style="margin-bottom:6px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('admin_committee_types.page_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('admin_committee_types.catalog_helper') }}</div>
    </div>

    <div style="display:flex; gap:10px;">
        <a href="{{ route('admin.committee-position-types.create', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('admin_committee_types.add_new_position') }}</a>
        <a href="{{ route('admin.committee-position-types.search', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
    </div>

    @else

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; align-items:center; gap:14px;">
        <a href="{{ route('admin.committee-position-types.search', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('masterfile.modify_search') }}</a>
        <a href="{{ route('admin.committee-position-types.create', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="color:#1565C0; text-decoration:none; font-size:11px; font-weight:600;">{{ __('admin_committee_types.add_new_position') }}</a>

    </div>

    <div id="cpt-table-wrap" style="flex:1; min-height:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; overflow:hidden;">
        <table id="cpt-table" style="width:100%; border-collapse:collapse; font-size:12px;">
            <thead>
                <tr style="background:#f0f9ff; border-bottom:1px solid #d1d5db;">
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151; width:30px;">#</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('admin_committee_types.col_position_label') }}</th>
                    <th style="text-align:left; padding:5px 8px; font-size:10.5px; color:#374151;">{{ __('masterfile.col_action') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($types as $t)
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:5px 8px; color:#9ca3af;">{{ $types->firstItem() + $loop->index }}</td>
                    <td style="padding:5px 8px; font-weight:600; color:#1565C0;">{{ $t->position_label }}</td>
                    <td style="padding:5px 8px; white-space:nowrap;">
                        <a href="{{ route('admin.committee-position-types.edit', $t->id) }}" style="color:#1B9AE4; text-decoration:none; font-weight:600;">{{ __('masterfile.view') }}</a>
                        <form method="POST" action="{{ route('admin.committee-position-types.move-up', $t->id) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="group_label_id" value="{{ request('group_label_id') }}">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            <button type="submit" title="{{ __('masterfile.move_up_title') }}" style="background:none; border:none; color:#546E7A; font-size:12px; cursor:pointer; padding:0 3px;">&#9650;</button>
                        </form>
                        <form method="POST" action="{{ route('admin.committee-position-types.move-down', $t->id) }}" style="display:inline;">
                            @csrf @method('PATCH')
                            <input type="hidden" name="group_label_id" value="{{ request('group_label_id') }}">
                            <input type="hidden" name="search" value="{{ request('search') }}">
                            <input type="hidden" name="per_page" value="{{ request('per_page') }}">
                            <input type="hidden" name="page" value="{{ request('page') }}">
                            <button type="submit" title="{{ __('masterfile.move_down_title') }}" style="background:none; border:none; color:#546E7A; font-size:12px; cursor:pointer; padding:0 3px;">&#9660;</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="padding:20px; text-align:center; color:#9ca3af; font-size:11.5px;">{{ __('admin_committee_types.no_positions_match') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($types instanceof \Illuminate\Pagination\LengthAwarePaginator && $types->total() > 0)
    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; margin-top:6px; padding:6px 12px; background:#fff; border:1px solid #d1d5db; border-radius:8px;">
        @if($types->onFirstPage())
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</span>
        @else
            <a href="{{ $types->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.prev') }}</a>
        @endif
        <span style="font-size:10.5px; color:#4b5563;">{{ __('masterfile.showing_records', ['first' => $types->firstItem(), 'last' => $types->lastItem(), 'total' => $types->total()]) }}</span>
        @if($types->hasMorePages())
            <a href="{{ $types->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</a>
        @else
            <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:11px; font-weight:700;">{{ __('masterfile.next') }}</span>
        @endif
    </div>
    @endif

    @endif

</div>

@if($hasAnyFilter && $types instanceof \Illuminate\Pagination\LengthAwarePaginator && $types->total() > 0)
{{-- NEW 26 Sep 2026 — per Chris: "NO SCROLL". Measures how many rows
     really fit in the space left on this screen and reloads with that
     many per page, so the list never scrolls; the rest go to Prev/Next. --}}
<script>
(function(){
    var wrap = document.getElementById('cpt-table-wrap');
    var table = document.getElementById('cpt-table');
    if (!wrap || !table) return;
    var perPage = {{ $types->perPage() }};
    var first = {{ $types->firstItem() ?? 1 }};
    var total = {{ $types->total() }};
    function fitCount(){
        var row = table.querySelector('tbody tr');
        var head = table.querySelector('thead');
        if (!row) return perPage;
        return Math.max(3, Math.min(50, Math.floor((wrap.clientHeight - (head ? head.offsetHeight : 0) - 2) / row.offsetHeight)));
    }
    function apply(){
        var fit = fitCount();
        var overflow = table.offsetHeight > wrap.clientHeight;
        if (fit === perPage) return;
        if (!overflow && total <= perPage) return;
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
