@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.tile_coa_structure_tree'))

@section('content')

{{-- REBUILT 23 Sep 2026 -- per Chris: "By Account Type" and "By GL
     Code Range" return a flat, Prev/Next-paginated table so every
     single account -- e.g. the new Entertainment expense accounts,
     which sit directly under the Type with no parent -- is always
     reachable. A proper Prev button also sits at the bottom (was only a
     small link at the top before).
     UPDATED 23 Sep 2026 -- per Chris ("ALL Means ALL, ALL Type Account,
     glcode range. IT Means ALL"): "All" mode's folder tree no longer
     caps/hides anything either -- every level (the top-level list under
     a Type, and every folder's own children) is Prev/Next-paginated
     instead, so ALL really does mean every single account, reachable
     without ever leaving "All" mode. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; display:flex; align-items:baseline; gap:10px; margin-bottom:2px;">
        <div style="font-size:12px; font-weight:700; color:#263238;">{{ __('cbe_accounting.tile_coa_structure_tree') }}</div>
        <div style="font-size:8.5px; color:#9ca3af;">{{ __('cbe_accounting.coa_tree_intro') }}</div>
    </div>

    <form method="GET" action="{{ route('cbe.accounting.chart-of-accounts-structure-tree') }}" style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:4px 10px; margin-bottom:4px; display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap;">
        <div>
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.coa_search_label') }}</label>
            <select name="mode" id="coaTreeMode" onchange="coaTreeModeChange()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 9px; font-size:10.5px;">
                <option value="all" {{ $mode === 'all' ? 'selected' : '' }}>{{ __('cbe_accounting.coa_tree_mode_all') }}</option>
                <option value="category" {{ $mode === 'category' ? 'selected' : '' }}>{{ __('cbe_accounting.coa_tree_mode_category') }}</option>
                <option value="range" {{ $mode === 'range' ? 'selected' : '' }}>{{ __('cbe_accounting.coa_tree_mode_range') }}</option>
                <option value="description" {{ $mode === 'description' ? 'selected' : '' }}>{{ __('cbe_accounting.coa_tree_mode_description') }}</option>
            </select>
        </div>
        {{-- ADDED 23 Sep 2026 -- per Chris: "create another selection
             search by description both chinese and english with type
             ahead features". Type English or Chinese and it live-
             suggests matching accounts (same AJAX endpoint the
             Search/Edit screen's type-ahead uses) before he even
             submits. --}}
        <div id="coaTreeKeywordField" style="position:relative; {{ $mode === 'description' ? '' : 'display:none;' }}">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.coa_tree_keyword_label') }}</label>
            <input type="text" name="keyword" id="coaTreeKeywordInput" value="{{ $keyword }}" autocomplete="off" placeholder="{{ __('cbe_accounting.coa_tree_keyword_placeholder') }}" style="width:460px; max-width:60vw; border:1px solid #d1d5db; border-radius:6px; padding:5px 9px; font-size:10.5px; box-sizing:border-box;">
            <div id="coaTreeKeywordDropdown" style="display:none; position:absolute; top:100%; left:0; width:620px; max-width:80vw; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
        </div>
        <div id="coaTreeCategoryField" style="{{ $mode === 'category' ? '' : 'display:none;' }}">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_type') }}</label>
            <select name="account_type" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 9px; font-size:10.5px;">
                @foreach($accountTypes as $t)
                <option value="{{ $t }}" {{ $accountType === $t ? 'selected' : '' }}>{{ __('cbe_accounting.type_'.strtolower($t)) }}</option>
                @endforeach
            </select>
        </div>
        <div id="coaTreeFromField" style="{{ $mode === 'range' ? '' : 'display:none;' }}">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.coa_tree_from') }}</label>
            <input type="text" name="from" value="{{ $from }}" inputmode="numeric" style="width:90px; border:1px solid #d1d5db; border-radius:6px; padding:5px 9px; font-size:10.5px; box-sizing:border-box;">
        </div>
        <div id="coaTreeToField" style="{{ $mode === 'range' ? '' : 'display:none;' }}">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.coa_tree_to') }}</label>
            <input type="text" name="to" value="{{ $to }}" inputmode="numeric" style="width:90px; border:1px solid #d1d5db; border-radius:6px; padding:5px 9px; font-size:10.5px; box-sizing:border-box;">
        </div>
        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.coa_search_button') }}</button>
    </form>

    <div style="flex:1; min-height:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:5px 10px; overflow:hidden; display:flex; flex-direction:column;">
        @if(!$searched)
            <div style="flex:1; display:flex; align-items:center; justify-content:center;">
                <div style="font-size:10.5px; color:#9ca3af; text-align:center;">{{ __('cbe_accounting.coa_tree_empty_prompt') }}</div>
            </div>
        @elseif($mode === 'all')
            <div style="flex:1; min-height:0; overflow:hidden;">
                <ul id="coaTreeAllList" style="list-style:none; margin:0; padding:0;">
                    @foreach($accountTypes as $type)
                        @php($typeData = $tree[$type] ?? ['nodes' => [], 'gap' => null])
                        <li style="margin-bottom:1px;">
                            <div class="coa-tree-row" data-type="{{ $type }}" onclick="toggleCoaNode(this)" style="display:flex; align-items:center; gap:6px; padding:3px 6px; cursor:pointer; border-radius:5px; background:var(--gl-light);">
                                <span class="coa-tree-toggle" style="width:14px; text-align:center; font-weight:700; color:var(--gl-blue); font-size:11px;">{{ count($typeData['nodes']) ? '+' : '' }}</span>
                                <span style="font-size:11px; font-weight:700; color:#263238;">{{ __('cbe_accounting.type_'.strtolower($type)) }}</span>
                                <span style="font-size:9px; color:#9ca3af;">({{ $typeData['total'] ?? count($typeData['nodes']) }})</span>
                            </div>
                            @if(count($typeData['nodes']))
                            <ul class="coa-tree-children" style="display:none; list-style:none; margin:0; padding-left:20px; border-left:1px dashed #d1d5db;">
                                @if($typeData['gap'])
                                <li style="padding:2px 6px; font-size:9px; color:#9a6b00; background:#fffbea;">
                                    @if(count($typeData['gap']['missing']))
                                    {{ __('cbe_accounting.coa_tree_gap_label') }} {{ implode(', ', $typeData['gap']['missing']) }} &nbsp;|&nbsp;
                                    @endif
                                    @if($typeData['gap']['next'])
                                    {{ __('cbe_accounting.coa_tree_next_label') }} <strong>{{ $typeData['gap']['next'] }}</strong>
                                    @endif
                                </li>
                                @endif
                                @foreach($typeData['nodes'] as $node)
                                    @include('cbe.accounting._coa-tree-node', ['node' => $node])
                                @endforeach
                                @if(($typeData['hasPrev'] ?? false) || ($typeData['hasNext'] ?? false))
                                <li style="padding:4px 6px; display:flex; justify-content:space-between; align-items:center; gap:6px;">
                                    @if($typeData['hasPrev'])
                                    <a href="{{ $typeData['prevUrl'] }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:600;">{{ __('network.prev') }}</a>
                                    @else
                                    <span style="background:#1565C0; color:#fff; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:700;">{{ __('network.prev') }}</span>
                                    @endif
                                    <span style="font-size:8.5px; color:#9ca3af;">{{ $typeData['page'] }} / {{ $typeData['lastPage'] }}</span>
                                    @if($typeData['hasNext'])
                                    <a href="{{ $typeData['nextUrl'] }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:600;">{{ __('network.next') }}</a>
                                    @else
                                    <span style="background:#1565C0; color:#fff; border-radius:14px; padding:2px 10px; font-size:9px; font-weight:700;">{{ __('network.next') }}</span>
                                    @endif
                                </li>
                                @endif
                            </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            {{-- Category or Range: flat, one row per GL code, Prev/Next -- every account reachable. --}}
            @if($flatGap)
            <div style="flex-shrink:0; padding:4px 8px; font-size:9.5px; color:#9a6b00; background:#fffbea; border-radius:6px; margin-bottom:4px;">
                @if(count($flatGap['missing']))
                {{ __('cbe_accounting.coa_tree_gap_label') }} {{ implode(', ', $flatGap['missing']) }} &nbsp;|&nbsp;
                @endif
                @if($flatGap['next'])
                {{ __('cbe_accounting.coa_tree_next_label') }} <strong>{{ $flatGap['next'] }}</strong>
                @endif
                @if($flatGap['truncated'] ?? false)
                &nbsp;({{ __('cbe_accounting.coa_tree_gap_truncated') }})
                @endif
            </div>
            @endif
            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:3px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:70px;">{{ __('cbe_accounting.col_code') }}</th>
                            <th style="text-align:left; padding:3px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_account_name') }}</th>
                            <th style="text-align:left; padding:3px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:90px;">{{ __('cbe_accounting.field_account_type') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flatAccounts as $a)
                        <tr style="border-bottom:1px solid #f3f4f6; {{ !$a->is_active ? 'opacity:.55;' : '' }}">
                            <td style="padding:3px 8px; font-family:monospace; color:#4b5563; white-space:nowrap;">{{ $a->account_code }}</td>
                            <td style="padding:3px 8px; color:#263238; {{ !$a->is_posting_account ? 'font-weight:700;' : '' }}">
                                {{ $a->account_name }}@if($a->account_name_zh) <span style="color:#6b7280; font-weight:400;">/ {{ $a->account_name_zh }}</span>@endif
                                @if(!$a->is_posting_account)
                                <span style="font-size:7.5px; font-weight:700; color:#9a6b00; background:#fff3cd; border-radius:10px; padding:1px 6px; margin-left:4px;">{{ __('cbe_accounting.coa_tree_folder_badge') }}</span>
                                @endif
                                @if(!$a->is_active)
                                <span style="font-size:7.5px; font-weight:700; color:#6b7280; background:#f3f4f6; border-radius:10px; padding:1px 6px; margin-left:4px;">{{ __('masterfile.inactive') }}</span>
                                @endif
                            </td>
                            <td style="padding:3px 8px; color:#9ca3af; white-space:nowrap;">{{ __('cbe_accounting.type_'.strtolower($a->account_type)) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.coa_tree_no_results') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                @if($flatAccounts->onFirstPage())
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</span>
                @else
                    <a href="{{ $flatAccounts->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600;">{{ __('network.prev') }}</a>
                @endif
                <span style="font-size:9px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $flatAccounts->currentPage(), 'last' => max($flatAccounts->lastPage(), 1), 'total' => $flatAccounts->total()]) }}</span>
                @if($flatAccounts->hasMorePages())
                    <a href="{{ $flatAccounts->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:600;">{{ __('network.next') }}</a>
                @else
                    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</span>
                @endif
            </div>
        @endif
    </div>

    {{-- ADDED 23 Sep 2026 -- per Chris: a proper Prev button at the
         bottom, left side -- the small top-right text link alone wasn't
         enough to get back out of this screen. FIXED same day -- per
         Chris ("why i click prev it go back to chart of account"): Prev
         must return to wherever Chris actually came from (the sidebar,
         the Financial Master File hub, etc), not always jump sideways
         to the Chart of Accounts screen specifically -- same
         history.back()-with-fallback pattern already used on Document
         Number Control.
         FIXED AGAIN 23 Sep 2026 -- per Chris ("you have to close and
         back correct to main category"): while browsing "All" mode
         with a Type/folder open, Prev used to leave the WHOLE screen
         straight away, which felt wrong -- he expects Prev to step back
         ONE level at a time, same as everywhere else: first close
         whatever is open and land back on the closed main category list
         (Income/Expense/Asset/Equity/Liability), and only leave the
         screen on a second Prev press once nothing is open. --}}
    <div style="flex-shrink:0; padding-top:8px;">
        <a href="{{ route('admin.masterfile.financial-master-file') }}" id="coaTreePrevBtn" onclick="return coaTreePrevClick(this);" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600; display:inline-block;">{{ __('network.prev') }}</a>
    </div>
</div>

<script>
function coaTreeModeChange() {
    var mode = document.getElementById('coaTreeMode').value;
    document.getElementById('coaTreeCategoryField').style.display = (mode === 'category') ? '' : 'none';
    document.getElementById('coaTreeFromField').style.display = (mode === 'range') ? '' : 'none';
    document.getElementById('coaTreeToField').style.display = (mode === 'range') ? '' : 'none';
    document.getElementById('coaTreeKeywordField').style.display = (mode === 'description') ? '' : 'none';
}

// Type-ahead for the "By Description" keyword box -- reuses the same
// AJAX endpoint the Chart of Accounts Search/Edit screen uses, which
// already matches account_name (English) and account_name_zh
// (Chinese). Clicking a suggestion fills the box with that account's
// name and submits the search straight away.
(function () {
    var input = document.getElementById('coaTreeKeywordInput');
    var dropdown = document.getElementById('coaTreeKeywordDropdown');
    if (!input || !dropdown) { return; }
    var timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch('{{ route('cbe.accounting.chart-of-accounts.typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.length) { dropdown.style.display = 'none'; return; }
                    dropdown.innerHTML = '';
                    data.forEach(function (item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'display:flex; align-items:center; gap:8px; padding:6px 9px; font-size:10px; cursor:pointer; border-bottom:1px solid #f3f4f6; white-space:nowrap;';
                        d.innerHTML =
                            '<span style="font-family:monospace; color:#4b5563; flex:0 0 auto;">' + item.account_code + '</span>' +
                            '<span style="color:#263238;">' + item.account_name + (item.account_name_zh ? ' <span style="color:#6b7280;">/ ' + item.account_name_zh + '</span>' : '') + '</span>';
                        d.onmousedown = function (e) {
                            e.preventDefault();
                            input.value = item.account_name;
                            dropdown.style.display = 'none';
                            input.form.submit();
                        };
                        dropdown.appendChild(d);
                    });
                    dropdown.style.display = 'block';
                });
        }, 250);
    });
    document.addEventListener('click', function (e) { if (e.target !== input) { dropdown.style.display = 'none'; } });
})();
function toggleCoaNode(rowEl) {
    var ul = rowEl.nextElementSibling;
    if (!ul || ul.tagName !== 'UL') { return; }
    var isOpen = ul.style.display !== 'none';

    if (!isOpen && ul.parentElement && ul.parentElement.parentElement) {
        var allRows = ul.parentElement.parentElement.querySelectorAll('.coa-tree-row');
        allRows.forEach(function (otherRow) {
            if (otherRow === rowEl) { return; }
            var otherUl = otherRow.nextElementSibling;
            if (otherUl && otherUl.tagName === 'UL' && otherUl.classList.contains('coa-tree-children') && otherUl.style.display !== 'none') {
                otherUl.style.display = 'none';
                var otherToggle = otherRow.querySelector('.coa-tree-toggle');
                if (otherToggle && otherToggle.textContent) { otherToggle.textContent = '+'; }
            }
        });
    }

    ul.style.display = isOpen ? 'none' : 'block';
    var toggle = rowEl.querySelector('.coa-tree-toggle');
    if (toggle && toggle.textContent) {
        toggle.textContent = isOpen ? '+' : '−';
    }
    if (!isOpen) {
        requestAnimationFrame(function () {
            rowEl.scrollIntoView({ block: 'start' });
        });
    }
}

// ADDED 23 Sep 2026 -- per Chris ("i dont understand when click prev
// it come back to this screen"): every Next/Prev link on the "All"
// tree carries ?open=Type,accountId,accountId... (built server-side in
// buildCoaStructureTree()) naming exactly which rows were open to make
// that Next/Prev visible in the first place. On load, re-expand that
// same chain so paging through a folder's own accounts (e.g. Expense's
// 121 GL codes) keeps you exactly where you were, instead of every
// folder snapping shut and looking like the screen reset.
(function () {
    var params = new URLSearchParams(window.location.search);
    var openParam = params.get('open');
    if (!openParam) { return; }
    var chain = openParam.split(',').filter(Boolean);
    if (!chain.length) { return; }
    var typeRow = document.querySelector('.coa-tree-row[data-type="' + chain[0] + '"]');
    if (!typeRow) { return; }
    toggleCoaNode(typeRow);
    var container = typeRow.parentElement;
    for (var i = 1; i < chain.length; i++) {
        if (!container) { break; }
        var ul = container.querySelector(':scope > ul.coa-tree-children');
        if (!ul) { break; }
        var row = ul.querySelector('.coa-tree-row[data-account-id="' + chain[i] + '"]');
        if (!row) { break; }
        toggleCoaNode(row);
        container = row.parentElement;
    }
})();

// ADDED 23 Sep 2026 -- per Chris ("you have to close and back correct
// to main category"): the bottom Prev button now steps back ONE level
// at a time in "All" mode. If anything is open (a Type, or a Type and
// a folder inside it), Prev just closes it all and lands back on the
// closed Income/Expense/Asset/Equity/Liability list -- it does NOT
// leave the screen. Only when nothing is open does Prev do what it did
// before (go back to wherever Chris actually came from).
function coaTreePrevClick(linkEl) {
    var list = document.getElementById('coaTreeAllList');
    if (!list) { return true; } // not in "All" mode -- normal Prev behaviour
    var anyOpen = Array.prototype.some.call(list.querySelectorAll('ul.coa-tree-children'), function (ul) {
        return ul.style.display !== 'none';
    });
    if (!anyOpen) { return true; } // fully closed already -- normal Prev behaviour
    list.querySelectorAll('ul.coa-tree-children').forEach(function (ul) { ul.style.display = 'none'; });
    list.querySelectorAll('.coa-tree-toggle').forEach(function (t) { if (t.textContent) { t.textContent = '+'; } });
    list.scrollIntoView({ block: 'start' });
    return false;
}
</script>
@endsection
