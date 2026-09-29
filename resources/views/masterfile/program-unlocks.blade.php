@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_program_library.unlocks_page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px;">
        <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('admin_program_library.unlocks_page_title') }}</div>
        <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('admin_program_library.unlocks_intro') }}</div>
    </div>

    @if(!$group)
    {{-- No community picked yet — a short search form, same pattern as
    the typeahead-style search already used for Group Name Maintenance
    elsewhere in the app. --}}
    <form method="GET" style="display:flex; gap:6px; margin-bottom:8px;">
        <input type="text" name="group_search" value="{{ $search }}" placeholder="{{ __('admin_program_library.search_community') }}" style="flex:1; max-width:360px; border:1px solid #d1d5db; border-radius:5px; padding:6px 10px; font-size:11px; box-sizing:border-box;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('admin_program_library.go') }}</button>
    </form>

    @if($groupResults->count())
    <div style="border:1px solid #d1d5db; border-radius:8px; background:#fff; max-width:480px;">
        @foreach($groupResults as $g)
        <a href="{{ route('admin.masterfile.program-unlocks', ['group' => $g->group_label_id]) }}" style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-bottom:1px solid #f3f4f6; text-decoration:none; color:#374151; font-size:11px;">
            <span>{{ $g->group_name }}</span>
            <span style="color:#9ca3af; font-size:9.5px;">{{ $g->group_type }}</span>
        </a>
        @endforeach
    </div>
    @elseif($search !== '')
    <div style="color:#9ca3af; font-size:10.5px;">{{ __('admin_program_library.no_programs') }}</div>
    @else
    <div style="color:#9ca3af; font-size:10.5px;">{{ __('admin_program_library.select_community') }}</div>
    @endif

    @else
    {{-- Community picked — show only Paid programs with an Unlock/Lock
    toggle per row. Fixed-height Prev/Next paging, same as every other
    list in the app; only paid programs are ever shown here since
    Free ones need no unlock. --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; flex-shrink:0;">
        <div style="font-size:11px; font-weight:700; color:#1565C0;">{{ $group->group_name }}</div>
        <a href="{{ route('admin.masterfile.program-unlocks') }}" style="font-size:9.5px; color:#1565C0; text-decoration:none; font-weight:600;">{{ __('admin_program_library.change_community') }}</a>
    </div>

    @php $pageSize = 8; $total = $paidPrograms->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
    <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
        <div style="display:grid; grid-template-columns:2.4fr 1.4fr 1fr 1fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">
            <div>{{ __('admin_program_library.col_program') }}</div>
            <div>{{ __('admin_program_library.col_section') }}</div>
            <div>{{ __('admin_program_library.col_paid') }}</div>
            <div></div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @forelse($paidPrograms as $p)
            @php $isUnlocked = $unlockedIds->contains($p->id); @endphp
            <div class="puRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:2.4fr 1.4fr 1fr 1fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->label }}">👑 {{ $p->label }}</div>
                <div style="color:#9ca3af; font-size:9.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $p->section }}</div>
                <div style="color:{{ $isUnlocked ? '#2e7d32' : '#b45309' }}; font-weight:600; font-size:9.5px;">{{ $isUnlocked ? __('admin_program_library.status_unlocked') : __('admin_program_library.status_locked') }}</div>
                <div>
                    @if($isUnlocked)
                    <form method="POST" action="{{ route('admin.masterfile.program-unlocks.lock', [$group->group_label_id, $p->id]) }}">
                        @csrf
                        <button type="submit" style="background:#f3f4f6; color:#374151; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('admin_program_library.lock_button') }}</button>
                    </form>
                    @else
                    <form method="POST" action="{{ route('admin.masterfile.program-unlocks.unlock', [$group->group_label_id, $p->id]) }}">
                        @csrf
                        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:4px; padding:3px 10px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('admin_program_library.unlock_button') }}</button>
                    </form>
                    @endif
                </div>
            </div>
            @empty
            <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('admin_program_library.no_paid_programs') }}</div>
            @endforelse
        </div>
    </div>

    @if($total > $pageSize)
    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
        <span onclick="puPageNav(-1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
        <span id="puPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
        <span onclick="puPageNav(1)" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
    </div>
    <script>
    (function () {
        var puCurrentPage = 1;
        var puTotalPages = {{ $totalPages }};
        window.puPageNav = function (dir) {
            var next = puCurrentPage + dir;
            if (next < 1 || next > puTotalPages) return;
            puCurrentPage = next;
            document.querySelectorAll('.puRow').forEach(function (row) {
                row.style.display = (parseInt(row.getAttribute('data-page'), 10) === puCurrentPage) ? 'grid' : 'none';
            });
            document.getElementById('puPageLabel').textContent = puCurrentPage + ' / ' + puTotalPages + ' ({{ $total }})';
        };
    })();
    </script>
    @endif
    @endif
</div>
@endsection
