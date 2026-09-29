@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_committee_types.search_by_position_name'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        <a href="{{ route('admin.committee-position-types.index', $groupLabelId ? ['group_label_id' => $groupLabelId] : []) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('admin_committee_types.back_to_position_types') }}</a>
    </div>

    {{-- NEW 24 Sep 2026 -- per Chris: per-CBE-group scoping (same
         picker pattern as the landing page / Role Ranks). Changing the
         group here reloads this same search screen already scoped to
         it -- the search box itself stays empty (a fresh search). --}}
    <form method="GET" action="{{ route('admin.committee-position-types.search') }}" style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px 12px; margin-bottom:8px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <label style="font-size:10px; font-weight:600; color:#374151; white-space:nowrap;">{{ __('admin_committee_types.group_picker_label') }}</label>
        <select name="group_label_id" id="cposGroupSelect" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:11.5px; background:#fff; min-width:220px;">
            @foreach($cbeGroups as $g)
            <option value="{{ $g->group_label_id }}" {{ $groupLabelId === $g->group_label_id ? 'selected' : '' }}>{{ $g->group_name }}</option>
            @endforeach
        </select>
        <span style="font-size:9px; color:#94A3B8;">{{ __('admin_committee_types.group_helper') }}</span>
    </form>

    <form method="GET" action="{{ route('admin.committee-position-types.index') }}">
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; position:relative;">
        <input type="hidden" id="cposGroupLabelId" name="group_label_id" value="{{ $groupLabelId }}">
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_committee_types.search_type_label') }}</label>
        <select name="field" id="cposSearchField" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff; margin-bottom:8px;">
            <option value="">{{ __('admin_committee_types.search_all_option') }}</option>
            <option value="position_label">{{ __('admin_committee_types.field_position_label') }}</option>
            <option value="code">{{ __('admin_committee_types.field_code') }}</option>
        </select>
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_committee_types.search_all_criteria_label') }}</label>
        <div style="display:flex; gap:8px;">
            <input type="text" name="search" id="cposSearchInput" placeholder="{{ __('admin_committee_types.search_placeholder') }}" autocomplete="off" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.search') }}</button>
        </div>
        <div id="cposDropdown" style="display:none; position:absolute; top:100%; left:14px; right:14px; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
    </div>
    </form>

    <script>var i18n = { noMatch: @json(__('admin_committee_types.no_positions_found')) };</script>

    <script>
    (function() {
        var input = document.getElementById('cposSearchInput');
        var dropdown = document.getElementById('cposDropdown');
        var timer;
        var editUrlTemplate = '{{ route('admin.committee-position-types.edit', ['id' => 'PLACEHOLDER']) }}';

        var fieldSelectEl = document.getElementById('cposSearchField');
        fieldSelectEl.addEventListener('change', function() { if (input.value.trim().length >= 1) input.dispatchEvent(new Event('input')); });

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function() {
                // Reads the group straight from the hidden field actually
                // sitting in the page at request time -- same fix already
                // applied for Group Name & Hierarchy Levels' type-ahead,
                // so it can never fall out of sync with what the page
                // itself is showing.
                var groupField = document.getElementById('cposGroupLabelId');
                var groupParam = (groupField && groupField.value) ? ('&group_label_id=' + encodeURIComponent(groupField.value)) : '';
                var fieldSelect = document.getElementById('cposSearchField');
                var fieldParam = (fieldSelect && fieldSelect.value) ? ('&field=' + encodeURIComponent(fieldSelect.value)) : '';
                fetch('{{ route('admin.committee-position-types.typeahead') }}?q=' + encodeURIComponent(q) + groupParam + fieldParam, { cache: 'no-store' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.length) {
                            dropdown.innerHTML = '<div style="padding:8px 10px; font-size:11px; color:#9ca3af;">' + i18n.noMatch + '</div>';
                            dropdown.style.display = 'block';
                            return;
                        }
                        dropdown.innerHTML = '';
                        data.forEach(function(item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:8px 10px; font-size:12px; cursor:pointer; border-bottom:1px solid #f3f4f6; font-weight:600; color:#1565C0;';
                            d.textContent = item.position_label;
                            d.onmousedown = function(e) {
                                e.preventDefault();
                                window.location.href = editUrlTemplate.replace('PLACEHOLDER', item.id);
                            };
                            dropdown.appendChild(d);
                        });
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function(e) { if (e.target !== input) dropdown.style.display = 'none'; });
    })();
    </script>

</div>
@endsection
