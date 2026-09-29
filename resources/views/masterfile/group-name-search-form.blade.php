@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('masterfile.search_to_edit_group_name'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    @php
        $type = request('type');
        $typeLabels = ['DSG' => __('sidebar.direct_selling_group_item'), 'ORG' => __('sidebar.organization_rewards_group_item'), 'CBE' => __('sidebar.cbe_group_item')];
    @endphp

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        <a href="{{ route('admin.masterfile.group-names', $type ? ['type' => $type] : []) }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">&larr; {{ __('masterfile.back_to_dashboard') }}</a>
        @if($type)
        <span style="font-size:10.5px; color:#94A3B8; font-weight:600;">{{ $typeLabels[$type] ?? $type }}</span>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.masterfile.group-names') }}">
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; position:relative;">
            @if($type)
            <input type="hidden" name="type" id="groupTypeInput" value="{{ $type }}">
            @endif
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.search_type_label') }}</label>
            <select name="field" id="groupSearchField" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff; margin-bottom:8px;">
                <option value="" {{ request('field') ? '' : 'selected' }}>{{ __('masterfile.search_all_option') }}</option>
                <option value="group_name" {{ request('field')=='group_name' ? 'selected' : '' }}>{{ __('masterfile.field_group_name') }}</option>
                <option value="description" {{ request('field')=='description' ? 'selected' : '' }}>{{ __('masterfile.field_description') }}</option>
            </select>
            <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('masterfile.search_by_group_name') }}</label>
            <div style="display:flex; gap:8px;">
                <input type="text" name="search" id="groupSearchInput" placeholder="{{ __('masterfile.group_name_search_placeholder') }}" autocomplete="off" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.search') }}</button>
            </div>
            <div id="groupDropdown" style="display:none; position:absolute; top:100%; left:14px; right:14px; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
        </div>
    </form>

    {{-- i18n strings needed by the JS below, injected before the raw-JS
         block so Blade still processes the translation calls. --}}
    <script>var i18n = { noMatch: @json(__('masterfile.no_group_names_found')) };</script>

    <script>
    (function() {
        var input = document.getElementById('groupSearchInput');
        var dropdown = document.getElementById('groupDropdown');
        var timer;
        var editUrlTemplate = '{{ route('admin.masterfile.group-names.edit', ['id' => 'PLACEHOLDER']) }}';

        document.getElementById('groupSearchField').addEventListener('change', function() { if (input.value.trim().length >= 1) input.dispatchEvent(new Event('input')); });

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function() {
                // CHANGED 24 Sep 2026 -- reads the type straight from
                // the hidden field actually sitting in the page (rather
                // than a separately server-rendered JS string), so the
                // request can never fall out of sync with what the page
                // itself is showing.
                var typeField = document.getElementById('groupTypeInput');
                var typeParam = (typeField && typeField.value) ? ('&type=' + encodeURIComponent(typeField.value)) : '';
                var fieldSelect = document.getElementById('groupSearchField');
                var fieldParam = (fieldSelect && fieldSelect.value) ? ('&field=' + encodeURIComponent(fieldSelect.value)) : '';
                fetch('{{ route('admin.masterfile.group-names.typeahead') }}?q=' + encodeURIComponent(q) + typeParam + fieldParam, { cache: 'no-store' })
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
                            d.textContent = item.group_name;
                            d.onmousedown = function(e) {
                                e.preventDefault();
                                window.location.href = editUrlTemplate.replace('PLACEHOLDER', item.group_label_id);
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
