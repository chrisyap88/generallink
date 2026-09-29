@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('special_group.search_groups_title'))

@section('content')
<div style="height:calc(100vh - 46px); overflow-y:auto; padding:6px 16px; box-sizing:border-box;">

    <div style="display:flex; align-items:baseline; gap:12px; margin-bottom:6px;">
        <a href="{{ route('admin.special-group.index') }}" style="color:#1565C0; text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('tl.back_to_dashboard_link') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; position:relative;">
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.search_type_label') }}</label>
        <select id="sgSearchField" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box; background:#fff; margin-bottom:8px;">
            <option value="">{{ __('special_group.search_all_option') }}</option>
            <option value="group_name">{{ __('special_group.field_group_name') }}</option>
            <option value="description">{{ __('special_group.field_description') }}</option>
        </select>
        <label style="font-size:10px; font-weight:600; color:#374151; display:block; margin-bottom:4px;">{{ __('special_group.search_by_group_name_label') }}</label>
        <input type="text" id="sgSearchInput" placeholder="{{ __('special_group.start_typing_placeholder') }}" autocomplete="off" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:12px; box-sizing:border-box;">
        <div id="sgDropdown" style="display:none; position:absolute; top:100%; left:14px; right:14px; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:220px; overflow-y:auto; margin-top:2px;"></div>
    </div>

    <script>
    (function() {
        var input = document.getElementById('sgSearchInput');
        var dropdown = document.getElementById('sgDropdown');
        var timer;
        var viewUrlTemplate = '{{ route('admin.special-group.view', ['groupLabelId' => 'PLACEHOLDER']) }}';

        document.getElementById('sgSearchField').addEventListener('change', function() { if (input.value.trim().length >= 1) input.dispatchEvent(new Event('input')); });

        input.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function() {
                var fieldSelect = document.getElementById('sgSearchField');
                var fieldParam = (fieldSelect && fieldSelect.value) ? ('&field=' + encodeURIComponent(fieldSelect.value)) : '';
                fetch('{{ route('admin.special-group.typeahead') }}?q=' + encodeURIComponent(q) + fieldParam)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.length) {
                            dropdown.innerHTML = '<div style="padding:8px 10px; font-size:11px; color:#9ca3af;">{{ __('special_group.no_matching_groups_note') }}</div>';
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
                                window.location.href = viewUrlTemplate.replace('PLACEHOLDER', item.group_label_id);
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
