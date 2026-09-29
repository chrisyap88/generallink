@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_program_library.page_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow:hidden; padding:6px 16px; box-sizing:border-box; display:flex; flex-direction:column;">

    <div style="margin-bottom:5px; display:flex; align-items:center; justify-content:space-between;">
        <div>
            <div style="font-size:14px; font-weight:700; color:#1565C0;">{{ __('admin_program_library.page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280; max-width:640px;">{{ __('admin_program_library.intro') }}</div>
        </div>
        <a href="{{ route('admin.masterfile.program-unlocks') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:5px; padding:6px 14px; font-size:11px; font-weight:600; white-space:nowrap;">👑 {{ __('admin_program_library.unlocks_link') }}</a>
    </div>

    {{-- Filter row — portal dropdown + search, one line, submits GET so
    the result list and page stay bookmarkable/shareable like every
    other filter-first list screen in the app. --}}
    <form method="GET" style="display:flex; gap:6px; margin-bottom:6px; flex-shrink:0;">
        <select name="portal" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; background:#fff;">
            <option value="">{{ __('admin_program_library.all_portals') }}</option>
            @foreach($portals as $p)
                <option value="{{ $p }}" {{ $portal === $p ? 'selected' : '' }}>{{ $p === 'DASHBOARD' ? __('admin_program_library.portal_dashboard') : ($p === 'GLADE' ? __('admin_program_library.portal_glade') : $p) }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('admin_program_library.search_placeholder') }}" style="flex:1; min-width:0; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('admin_program_library.go') }}</button>
        @if($portal !== '' || $search !== '')
        <a href="{{ route('admin.masterfile.program-library') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:5px; padding:5px 14px; font-size:10.5px; font-weight:500;">{{ __('admin_program_library.clear') }}</a>
        @endif
    </form>

    {{-- CHANGED 13 Sep 2026 — per Chris: "you should have select all so
    that i no need to tick one by one and even i select All, i still can
    untick for those not relevant." Was one instant-submit button per
    row; now ALL rows are checkboxes inside ONE form with a Select
    All/Clear All shortcut (same pattern as GLADE Tier's
    glSelectAllTiers) plus a single Save — ticking Select All just
    checks every box, each one individually still untickable afterward,
    nothing is locked. Save only changes programs currently in view
    (respects the portal/search filter above) via the hidden
    all_ids[] list, so a filtered search never silently resets programs
    outside that filter. --}}
    <form method="POST" action="{{ route('admin.masterfile.program-library.bulk-update') }}" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <input type="hidden" name="portal" value="{{ $portal }}">
        <input type="hidden" name="search" value="{{ $search }}">
        @foreach($programs as $p)
        <input type="hidden" name="all_ids[]" value="{{ $p->id }}">
        @endforeach

        @php $pageSize = 10; $total = $programs->count(); $totalPages = $total > 0 ? (int) ceil($total / $pageSize) : 1; @endphp
        <div style="flex:1; min-height:0; border:1px solid #d1d5db; border-radius:8px; background:#fff; display:flex; flex-direction:column; overflow:hidden;">
            <div style="display:grid; grid-template-columns:2.2fr 1.3fr 1.6fr 0.9fr; gap:6px; padding:6px 10px; background:#f0f9ff; border-bottom:1px solid #e0f2fe; font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase; align-items:center;">
                <div>{{ __('admin_program_library.col_program') }}</div>
                <div>{{ __('admin_program_library.col_area') }}</div>
                <div>{{ __('admin_program_library.col_section') }}</div>
                <div style="text-align:center; display:flex; align-items:center; justify-content:center; gap:6px;">
                    <span>{{ __('admin_program_library.col_paid') }}</span>
                    <a href="#" onclick="plSelectAll(event)" style="font-size:8.5px; color:#1565C0; text-decoration:none; font-weight:700; text-transform:none;">{{ __('admin_program_library.select_all') }}</a>
                    <a href="#" onclick="plClearAll(event)" style="font-size:8.5px; color:#6b7280; text-decoration:none; font-weight:700; text-transform:none;">{{ __('admin_program_library.clear_all') }}</a>
                </div>
            </div>
            <div id="plRowsWrap" style="flex:1; min-height:0; overflow:hidden;">
                @forelse($programs as $p)
                <div class="plRow" data-page="{{ intdiv($loop->index, $pageSize) + 1 }}" style="display:{{ $loop->index < $pageSize ? 'grid' : 'none' }}; grid-template-columns:2.2fr 1.3fr 1.6fr 0.9fr; gap:6px; padding:5px 10px; border-bottom:1px solid #f3f4f6; font-size:10.5px; align-items:center;">
                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->label }}">{{ $p->label }}</div>
                    <div style="color:#6b7280; font-size:9.5px;">{{ $p->portal === 'DASHBOARD' ? __('admin_program_library.portal_dashboard') : ($p->portal === 'GLADE' ? __('admin_program_library.portal_glade') : $p->portal) }}</div>
                    <div style="color:#9ca3af; font-size:9.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->section }}">{{ $p->section }}</div>
                    <div style="text-align:center;">
                        <label style="cursor:pointer;">
                            <input type="checkbox" class="plPaidCheck" name="paid_ids[]" value="{{ $p->id }}" {{ $p->is_paid ? 'checked' : '' }} style="display:none;">
                            <span class="plCrown" style="font-size:15px; opacity:{{ $p->is_paid ? '1' : '0.25' }};">👑</span>
                        </label>
                    </div>
                </div>
                @empty
                <div style="padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('admin_program_library.no_programs') }}</div>
                @endforelse
            </div>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:5px; flex-shrink:0;">
            <span onclick="plPageNav(-1)" id="plPrevBtn" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.prev') }}</span>
            <span id="plPageLabel" style="font-size:9.5px; color:#6b7280;">1 / {{ $totalPages }} ({{ $total }})</span>
            <button type="submit" style="background:#1B5E20; color:#fff; border:none; border-radius:5px; padding:5px 20px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('masterfile.save') }}</button>
            <span onclick="plPageNav(1)" id="plNextBtn" style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 16px; font-size:10px; font-weight:700; cursor:pointer;">{{ __('masterfile.next') }}</span>
        </div>
    </form>
</div>

<script>
(function () {
    var plCurrentPage = 1;
    var plTotalPages = {{ $totalPages }};
    window.plPageNav = function (dir) {
        var next = plCurrentPage + dir;
        if (next < 1 || next > plTotalPages) return;
        plCurrentPage = next;
        document.querySelectorAll('.plRow').forEach(function (row) {
            row.style.display = (parseInt(row.getAttribute('data-page'), 10) === plCurrentPage) ? 'grid' : 'none';
        });
        document.getElementById('plPageLabel').textContent = plCurrentPage + ' / ' + plTotalPages + ' ({{ $total }})';
    };
})();

// Checkbox is visually hidden — the crown emoji itself reflects checked
// state (dim = unchecked, full opacity = checked), clicking the label
// toggles the underlying checkbox natively.
document.querySelectorAll('.plPaidCheck').forEach(function (cb) {
    cb.addEventListener('change', function () {
        cb.closest('label').querySelector('.plCrown').style.opacity = cb.checked ? '1' : '0.25';
    });
});

// NEW 13 Sep 2026 — per Chris: Select All ticks every program (across
// every page, not just the visible one — all rows exist in the DOM,
// paging only hides/shows them), Clear All unticks every one; either
// way each box stays individually clickable afterward.
function plSelectAll(e) {
    e.preventDefault();
    document.querySelectorAll('.plPaidCheck').forEach(function (cb) {
        cb.checked = true;
        cb.closest('label').querySelector('.plCrown').style.opacity = '1';
    });
}
function plClearAll(e) {
    e.preventDefault();
    document.querySelectorAll('.plPaidCheck').forEach(function (cb) {
        cb.checked = false;
        cb.closest('label').querySelector('.plCrown').style.opacity = '0.25';
    });
}
</script>
@endsection
