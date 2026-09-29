@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_glade_tiers.page_title'))

@section('content')

<style>
.gt-box{background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:0 10px 10px 10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.gt-box-title{font-size:11px; font-weight:700; color:#263238; padding:5px 12px; border-bottom:1px solid #eef2f7; flex-shrink:0;}
.gt-box-helper{font-size:8px; color:#94A3B8; padding:0 12px 3px; flex-shrink:0;}
.gt-input{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; box-sizing:border-box; width:100%;}
.gt-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:5px 13px; font-size:9px; font-weight:700; cursor:pointer;}
.gt-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:20px;}
.gt-pager{flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:5px 12px; border-top:1px solid #eef2f7;}
.gt-pager-link{background:var(--gl-blue); color:#fff; text-decoration:none; border:none; border-radius:20px; padding:4px 12px; font-size:8.5px; font-weight:600; cursor:pointer; font-family:'Poppins',sans-serif;}
.gt-pager-link.gt-disabled{background:#1565C0; pointer-events:none; cursor:default;}
.gt-pager-info{font-size:8.5px; color:#94A3B8;}
{{-- NEW 24 Sep 2026 -- per Chris ("align all column in proper
     alignment", "reduce the row height"): one shared CSS Grid column
     template, used by the header row, the add-tier row and every data
     row, so every field lines up under its own labelled column
     instead of repeating a text label next to each checkbox. Row
     padding trimmed down from the original so all 6 catalog tiers sit
     on one screen with room to spare. --}}
.gt-grid-tier{display:grid; grid-template-columns:2.4fr 0.9fr 1.1fr 0.9fr 0.7fr; gap:8px; align-items:center; padding:4px 12px; border-bottom:1px solid #eef2f7;}
.gt-col-head{font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;}
.gt-col-check{display:flex; justify-content:center;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:6px 16px; box-sizing:border-box; gap:5px;">

    {{-- CHANGED 24 Sep 2026 -- per Chris: "your header row is too big"
         -- removed the big in-page heading that used to sit here; the
         top nav bar already shows "GLADE Membership Tiers", so this
         was pure duplication eating into the space the 6 rows need to
         all fit on one screen without paginating. --}}

    {{-- Tier Catalog -- the only panel on this screen now. Per Chris:
         "it the membership fees tier offering master file, it has
         nothing to do with pending approval or active tier ... you no
         need to show [CBE assignment] in Glade membership tier again."
         Assigning a tier to a specific CBE group happens on the Group
         Name edit screen, not here. --}}
    <div class="gt-box" style="flex:1; min-height:0;">
        <div class="gt-box-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span>{{ __('admin_glade_tiers.catalog_title') }}</span>
            {{-- CHANGED 24 Sep 2026 -- per Chris: "why the save button
                 appear in every row?" -- ONE Save button for the whole
                 table now (form="gtTiersForm" submits the outer form
                 below even though this button visually sits in the
                 header, so it's always reachable no matter which
                 client-side page of rows is showing). --}}
            <button type="submit" form="gtTiersForm" class="gt-btn">{{ __('admin_glade_tiers.btn_save_all') }}</button>
        </div>
        <div class="gt-box-helper">{{ __('admin_glade_tiers.catalog_helper') }}</div>

        {{-- NEW 11 Sep 2026 — per Chris: "why only offer this few
        type? what if new type introduce? you should not hardcode."
        Adding a tier is its own small action/form, separate from
        editing the existing ones below. --}}
        <form method="POST" action="{{ route('admin.glade-tiers.store') }}" class="gt-grid-tier" style="flex-shrink:0; background:#f8fafc;">
            @csrf
            <input type="text" name="tier_name" value="{{ old('tier_name') }}" placeholder="{{ __('admin_glade_tiers.new_tier_name_placeholder') }}" class="gt-input">
            <input type="number" name="max_users" value="{{ old('max_users') }}" placeholder="{{ __('admin_glade_tiers.no_cap') }}" class="gt-input">
            <input type="number" step="0.01" name="annual_fee" value="{{ old('annual_fee') }}" placeholder="{{ __('admin_glade_tiers.col_annual_fee') }}" class="gt-input">
            <div class="gt-col-check"><input type="checkbox" name="is_custom_quotation" value="1" @checked(old('is_custom_quotation'))></div>
            <button type="submit" class="gt-btn">{{ __('admin_glade_tiers.btn_add_tier') }}</button>
            @error('new_tier_name')<span style="color:#c62828; grid-column:1/-1;">{{ $message }}</span>@enderror
        </form>

        {{-- Column headers -- once, not repeated per row. --}}
        <div class="gt-grid-tier" style="background:#fff; border-bottom-width:2px;">
            <div class="gt-col-head">{{ __('admin_glade_tiers.col_tier_name') }}</div>
            <div class="gt-col-head">{{ __('admin_glade_tiers.col_max_users') }} <span style="text-transform:none; font-weight:400;">({{ __('admin_glade_tiers.no_cap') }})</span></div>
            <div class="gt-col-head">{{ __('admin_glade_tiers.col_annual_fee') }}</div>
            <div class="gt-col-head" style="text-align:center;">{{ __('admin_glade_tiers.col_custom_quote') }}</div>
            <div class="gt-col-head" style="text-align:center;">{{ __('admin_glade_tiers.col_active') }}</div>
        </div>

        {{-- ONE form for every row -- see gt-box-title button above.
             Fields are named tiers[<tier_id>][field] so the whole
             table saves in a single POST. --}}
        <form method="POST" action="{{ route('admin.glade-tiers.update-all') }}" id="gtTiersForm" style="flex:1; min-height:0; display:flex; flex-direction:column;">
            @csrf
            <div id="gtCatalogRows" style="flex:1; min-height:0;">
                @foreach($tiers as $t)
                <div class="gt-grid-tier gt-fit-row">
                    <input type="text" name="tiers[{{ $t->tier_id }}][tier_name]" value="{{ $t->tier_name }}" class="gt-input" title="{{ $t->tier_name }}">
                    <input type="number" name="tiers[{{ $t->tier_id }}][max_users]" value="{{ $t->max_users }}" placeholder="{{ __('admin_glade_tiers.no_cap') }}" class="gt-input">
                    <input type="number" step="0.01" name="tiers[{{ $t->tier_id }}][annual_fee]" value="{{ $t->annual_fee }}" class="gt-input">
                    <div class="gt-col-check"><input type="checkbox" name="tiers[{{ $t->tier_id }}][is_custom_quotation]" value="1" @checked($t->is_custom_quotation)></div>
                    <div class="gt-col-check"><input type="checkbox" name="tiers[{{ $t->tier_id }}][is_active]" value="1" @checked($t->is_active)></div>
                </div>
                @endforeach
            </div>
        </form>
        @if(session('cbe_tier_saved'))
        <div style="flex-shrink:0; font-size:8.5px; color:#2e7d32; font-weight:700; padding:5px 12px;">{{ __('admin_glade_tiers.saved') }}</div>
        @endif
        <div class="gt-pager" id="gtPagerCatalog">
            <button type="button" class="gt-pager-link gt-disabled" data-role="prev">{{ __('network.prev') }}</button>
            <span class="gt-pager-info" data-role="info"></span>
            <button type="button" class="gt-pager-link gt-disabled" data-role="next">{{ __('network.next') }}</button>
        </div>
    </div>

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.masterfile.group-names') }}" class="gt-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('cbe_accounting.prev_label') }}</a>
    </div>
</div>

<script>
// NEW 24 Sep 2026 -- per Chris: "display all the records in one screen,
// why you truncated and limit the records ... if more than the screen
// display use prev and next." This measures how much vertical space the
// row list ACTUALLY has in the visitor's own browser window, works out
// how many rows really fit, and only then slices the list into pages --
// so nothing is ever hidden or clipped, and Prev/Next only appears when
// there truly are more rows than fit. Runs after the browser has laid
// everything out, and again on resize. Kept even though the catalog is
// only a handful of tiers today, so this screen still never needs to
// scroll if the catalog grows later.
function gtFitRows(containerId, pagerId) {
    var container = document.getElementById(containerId);
    var pager = document.getElementById(pagerId);
    if (!container || !pager) return;
    var rows = Array.prototype.slice.call(container.querySelectorAll('.gt-fit-row'));
    var prevBtn = pager.querySelector('[data-role="prev"]');
    var nextBtn = pager.querySelector('[data-role="next"]');
    var info = pager.querySelector('[data-role="info"]');
    var serverPrev = pager.getAttribute('data-server-prev') || '';
    var serverNext = pager.getAttribute('data-server-next') || '';
    var serverTotal = pager.getAttribute('data-server-total');

    if (rows.length === 0) {
        prevBtn.classList.add('gt-disabled');
        nextBtn.classList.add('gt-disabled');
        if (info) info.textContent = '';
        return;
    }

    // Show every row once to measure real heights, then decide the split.
    rows.forEach(function (r) { r.style.display = ''; });
    var available = container.clientHeight;
    var rowHeight = rows[0].getBoundingClientRect().height || 30;
    var perChunk = Math.max(1, Math.floor(available / rowHeight));
    var chunks = [];
    for (var i = 0; i < rows.length; i += perChunk) {
        chunks.push(rows.slice(i, i + perChunk));
    }
    var current = 0;

    function render() {
        chunks.forEach(function (chunk, idx) {
            chunk.forEach(function (r) { r.style.display = (idx === current) ? '' : 'none'; });
        });
        var atFirst = (current === 0);
        var atLast = (current === chunks.length - 1);
        prevBtn.classList.toggle('gt-disabled', atFirst && !serverPrev);
        nextBtn.classList.toggle('gt-disabled', atLast && !serverNext);
        if (info) {
            var totalLabel = serverTotal !== null ? serverTotal : rows.length;
            info.textContent = 'Page ' + (current + 1) + ' of ' + chunks.length + ' (' + totalLabel + ' total)';
        }
    }

    prevBtn.onclick = function () {
        if (current > 0) { current--; render(); }
        else if (serverPrev) { window.location.href = serverPrev; }
    };
    nextBtn.onclick = function () {
        if (current < chunks.length - 1) { current++; render(); }
        else if (serverNext) { window.location.href = serverNext; }
    };

    render();
}

function gtFitAll() {
    gtFitRows('gtCatalogRows', 'gtPagerCatalog');
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', gtFitAll);
} else {
    gtFitAll();
}
var gtResizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(gtResizeTimer);
    gtResizeTimer = setTimeout(gtFitAll, 150);
});
</script>

@endsection
