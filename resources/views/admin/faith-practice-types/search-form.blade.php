@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('admin_cbe_faith_types.search_by_position_name'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        <a href="{{ route('admin.faith-practice-types.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('admin_cbe_faith_types.back_to_position_types') }}</a>
    </div>

    <form method="GET" action="{{ route('admin.faith-practice-types.index') }}">
    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; position:relative;">
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_cbe_faith_types.search_type_label') }}</label>
        <select name="field" id="positionSearchField" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff; margin-bottom:8px;">
            <option value="">{{ __('admin_cbe_faith_types.search_all_option') }}</option>
            <option value="option_label">{{ __('admin_cbe_faith_types.field_option_label') }}</option>
            <option value="tab_label">{{ __('admin_cbe_faith_types.field_tab_label') }}</option>
            <option value="practitioner_label">{{ __('admin_cbe_faith_types.field_practitioner_label') }}</option>
            <option value="duty_label">{{ __('admin_cbe_faith_types.field_duty_label') }}</option>
            <option value="log_form_title">{{ __('admin_cbe_faith_types.field_log_form_title') }}</option>
            <option value="reason_options">{{ __('admin_cbe_faith_types.field_reason_options') }}</option>
        </select>
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('admin_cbe_faith_types.search_all_criteria_label') }}</label>
        <div style="display:flex; gap:8px;">
            <input type="text" name="search" id="positionSearchInput" placeholder="{{ __('admin_cbe_faith_types.search_placeholder') }}" autocomplete="off" style="flex:1; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
            <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 22px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('masterfile.search') }}</button>
        </div>
        <div id="positionDropdown" style="display:none; position:absolute; top:100%; left:14px; right:14px; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
    </div>
    </form>

    <script>var i18n = { noMatch: @json(__('admin_cbe_faith_types.no_positions_found')) };</script>

    <script>
    (function() {
        var input = document.getElementById('positionSearchInput');
        var dropdown = document.getElementById('positionDropdown');
        var timer;
        var editUrlTemplate = '{{ route('admin.faith-practice-types.edit', ['id' => 'PLACEHOLDER']) }}';

        document.getElementById('positionSearchField').addEventListener('change', function() { if (input.value.trim().length >= 1) input.dispatchEvent(new Event('input')); });

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var fieldSelect = document.getElementById('positionSearchField');
                var fieldParam = (fieldSelect && fieldSelect.value) ? ('&field=' + encodeURIComponent(fieldSelect.value)) : '';
                fetch('{{ route('admin.faith-practice-types.typeahead') }}?q=' + encodeURIComponent(q) + fieldParam)
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
                            d.textContent = item.option_label;
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
