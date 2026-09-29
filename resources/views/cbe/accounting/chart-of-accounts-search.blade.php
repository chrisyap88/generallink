@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.coa_search_page_title'))

@section('content')

{{-- REBUILT 23 Sep 2026 per Chris: "search results is open a new
     screen display on top" -- was a single AJAX call rendered by
     client-side JS (the only screen in the app built that way). Now a
     real GET submit that reloads this page with results server-
     rendered at the top, Prev/Next paginated -- same pattern as every
     other list screen (GL Customers, Rebate Offers, Support Tickets,
     etc). Description was dropped as a search field per Chris
     ("redundant") -- Type/Category/Group/Code/Name/Name (Chinese)
     remain. --}}
<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="display:flex; align-items:baseline; gap:10px;">
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.coa_search_page_title') }}</div>
            <div id="fTotalCount" style="font-size:9.5px; color:#78909C;"></div>
        </div>
        <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('cbe_accounting.tile_chart_of_accounts') }}</a>
    </div>

    <form method="GET" action="{{ route('cbe.accounting.chart-of-accounts.search') }}" style="flex-shrink:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px;" autocomplete="off">
        <div style="font-size:9.5px; color:#78909C; margin-bottom:6px;">{{ __('cbe_accounting.coa_advanced_search_hint') }}</div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px 14px;">
            <div>
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_type') }}</label>
                <select name="type" id="fType" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_accounting.coa_all_types') }}</option>
                    @foreach(['ASSET','LIABILITY','EQUITY','INCOME','EXPENSE'] as $t)
                    <option value="{{ $t }}" {{ $type === $t ? 'selected' : '' }}>{{ __('cbe_accounting.type_'.strtolower($t)) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="position:relative;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_category') }}</label>
                <input type="text" name="category_text" id="fCategoryInput" value="{{ $categoryText }}" placeholder="{{ __('cbe_accounting.coa_category_search_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <input type="hidden" name="category_id" id="fCategoryHidden" value="{{ $categoryId }}">
                <div id="fCategoryDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
            </div>
            <div style="position:relative;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_group') }}</label>
                <input type="text" name="group_text" id="fGroupInput" value="{{ $groupText }}" placeholder="{{ __('cbe_accounting.coa_group_search_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <input type="hidden" name="group_id" id="fGroupHidden" value="{{ $groupId }}">
                <div id="fGroupDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
            </div>
            <div style="position:relative;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_code') }}</label>
                <input type="text" name="code" id="fCode" value="{{ $code }}" maxlength="20" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <div id="fCodeDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
            </div>
            <div style="position:relative;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_name') }}</label>
                <input type="text" name="name" id="fName" value="{{ $name }}" maxlength="150" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <div id="fNameDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
            </div>
            <div style="position:relative;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_name_zh') }}</label>
                <input type="text" name="name_zh" id="fNameZh" value="{{ $nameZh }}" maxlength="150" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <div id="fNameZhDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
            </div>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
            <a href="{{ route('cbe.accounting.chart-of-accounts.search') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; display:inline-flex; align-items:center;">{{ __('cbe_accounting.coa_clear_button') }}</a>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.coa_search_button') }}</button>
        </div>
    </form>

    {{-- Results now render at the TOP of a real reloaded screen, server
         side, immediately below the search box -- not buried in a small
         AJAX panel. --}}
    <div style="flex:1; min-height:0; margin-top:8px; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 14px; display:flex; flex-direction:column;">
        @if(! $hasQuery)
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10.5px; text-align:center;">{{ __('cbe_accounting.coa_search_start_hint') }}</div>
        @elseif($results->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10.5px; text-align:center;">{{ __('cbe_accounting.coa_search_no_results_msg') }}</div>
        @else
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
            <div style="font-size:10.5px; font-weight:700; color:#263238;">{{ __('cbe_accounting.coa_search_results_title') }}</div>
            <div style="font-size:9.5px; color:#78909C;">{{ __('cbe_accounting.coa_results_count', ['count' => $results->total()]) }}</div>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden; display:flex; flex-direction:column;">
            @foreach($results as $r)
            <div style="display:flex; align-items:center; gap:10px; padding:6px 4px; border-bottom:1px solid #f3f4f6; font-size:10.5px;">
                <span style="flex:0 0 70px; font-weight:700; color:var(--gl-blue);">{{ $r->account_code }}</span>
                <span style="flex:0 0 70px; color:#78909C;">{{ $coaTypeLabels[$r->account_type] ?? $r->account_type }}</span>
                <span style="flex:1 1 auto; min-width:0; color:#263238; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $r->account_name }}{{ $r->account_name_zh ? ' ('.$r->account_name_zh.')' : '' }}</span>
                <span style="flex:0 0 110px; color:#78909C; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $r->category_name ?: $r->group_name }}</span>
                <span style="flex:0 0 auto; display:flex; gap:10px;">
                    <a href="{{ route('cbe.accounting.general-ledger-enquiry') }}?account_id={{ $r->account_id }}&back={{ urlencode(request()->fullUrl()) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('gl.view_link') }}</a>
                    <a href="{{ route('cbe.accounting.chart-of-accounts.edit', ['account' => $r->account_id]) }}" style="color:#38A169; text-decoration:none; font-weight:600;">{{ __('gl.edit_link') }}</a>
                </span>
            </div>
            @endforeach
        </div>
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
            @if($results->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $results->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('network.page_of', ['current' => $results->currentPage(), 'last' => $results->lastPage()]) }}</span>
            @if($results->hasMorePages())
                <a href="{{ $results->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>

</div>

<script>
(function () {
    // Same shared type-ahead helper used across the app: fills the
    // field, then submits the form for a real page reload -- no more
    // client-side AJAX rendering on this screen.
    function initTypeahead(opts) {
        var input = document.getElementById(opts.inputId);
        var hidden = opts.hiddenId ? document.getElementById(opts.hiddenId) : null;
        var dropdown = document.getElementById(opts.dropdownId);
        var timer;
        input.addEventListener('input', function () {
            if (hidden) { hidden.value = ''; }
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch(opts.url() + '?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:7px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            var label = opts.renderLabel(item);
                            d.textContent = label;
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                if (opts.onSelect) {
                                    opts.onSelect(item);
                                } else {
                                    input.value = label;
                                    if (hidden) { hidden.value = opts.idField(item); }
                                    input.form.submit();
                                }
                                dropdown.style.display = 'none';
                            };
                            dropdown.appendChild(d);
                        });
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });
        document.addEventListener('click', function (e) { if (e.target !== input) dropdown.style.display = 'none'; });
    }

    initTypeahead({
        inputId: 'fCategoryInput', hiddenId: 'fCategoryHidden', dropdownId: 'fCategoryDropdown',
        url: function () { return '{{ route('cbe.accounting.chart-of-accounts.category-typeahead') }}'; },
        renderLabel: function (item) { return item.category_name + (item.category_name_zh ? ' (' + item.category_name_zh + ')' : ''); },
        idField: function (item) { return item.category_id; }
    });
    initTypeahead({
        inputId: 'fGroupInput', hiddenId: 'fGroupHidden', dropdownId: 'fGroupDropdown',
        url: function () {
            var base = '{{ route('cbe.accounting.chart-of-accounts.group-typeahead') }}';
            var t = document.getElementById('fType').value;
            return base + (t ? '?type=' + encodeURIComponent(t) : '');
        },
        renderLabel: function (item) { return item.group_name + (item.group_name_zh ? ' (' + item.group_name_zh + ')' : ''); },
        idField: function (item) { return item.group_id; }
    });

    var coaTypeaheadUrl = '{{ route('cbe.accounting.chart-of-accounts.typeahead') }}';
    initTypeahead({
        inputId: 'fCode', dropdownId: 'fCodeDropdown',
        url: function () { return coaTypeaheadUrl; },
        renderLabel: function (item) { return item.account_code + ' — ' + item.account_name + (item.account_name_zh ? ' / ' + item.account_name_zh : ''); },
        onSelect: function (item) {
            document.getElementById('fCode').value = item.account_code;
            document.getElementById('fCode').form.submit();
        }
    });
    initTypeahead({
        inputId: 'fName', dropdownId: 'fNameDropdown',
        url: function () { return coaTypeaheadUrl; },
        renderLabel: function (item) { return item.account_code + ' — ' + item.account_name + (item.account_name_zh ? ' / ' + item.account_name_zh : ''); },
        onSelect: function (item) {
            document.getElementById('fName').value = item.account_name;
            document.getElementById('fName').form.submit();
        }
    });
    initTypeahead({
        inputId: 'fNameZh', dropdownId: 'fNameZhDropdown',
        url: function () { return coaTypeaheadUrl; },
        renderLabel: function (item) { return item.account_code + ' — ' + item.account_name + (item.account_name_zh ? ' / ' + item.account_name_zh : ''); },
        onSelect: function (item) {
            document.getElementById('fNameZh').value = item.account_name_zh || '';
            document.getElementById('fNameZh').form.submit();
        }
    });

    // Total count badge -- independent of the search itself, always
    // shows the true un-filtered total.
    var totalCountLabel = @json(__('cbe_accounting.coa_total_count_label'));
    fetch('{{ route('cbe.accounting.chart-of-accounts.total-count') }}')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('fTotalCount').textContent = totalCountLabel.replace(':total', data.total);
        })
        .catch(function () {});
})();
</script>
@endsection
