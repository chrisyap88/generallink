@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.asset_categories_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.asset_categories_page_title') }}</div>
        <a href="{{ route('cbe.accounting.asset-categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <form method="GET" action="{{ route('cbe.accounting.asset-categories.search') }}" style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:8px; align-items:flex-end;">
        <div style="flex:1; position:relative;">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name') }}</label>
            <input type="text" name="q" id="qInput" value="{{ request('q') }}" maxlength="150" autocomplete="off" autofocus placeholder="{{ __('cbe_accounting.category_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            <div id="qDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
        </div>
        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.coa_search_button') }}</button>
    </form>

    {{-- ADDED 23 Sep 2026 -- per Chris ("ALL search must have type
         ahead"). --}}
    <script>
    (function () {
        var input = document.getElementById('qInput');
        var dropdown = document.getElementById('qDropdown');
        var timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length < 1) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('{{ route('cbe.accounting.asset-categories.typeahead') }}?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:7px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            d.textContent = item.category_name + (item.category_name_zh ? ' / ' + item.category_name_zh : '');
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                input.value = item.category_name;
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
    </script>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(is_null($categories))
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="font-size:10.5px; color:#9ca3af; text-align:center;">{{ __('cbe_accounting.coa_search_start_hint') }}</div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_category_name') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_gl_fixed_asset_account') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_gl_accum_depreciation_account') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_gl_depreciation_expense_account') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $c)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ !$c->is_active ? 'opacity:.5;' : '' }}">
                        <td style="padding:5px 6px; font-weight:600; color:#263238;">{{ $c->category_name }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $c->fixed_asset_account_name ?: __('cbe_accounting.field_gl_use_default') }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $c->accum_depreciation_account_name ?: __('cbe_accounting.field_gl_use_default') }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $c->depreciation_expense_account_name ?: __('cbe_accounting.field_gl_use_default') }}</td>
                        <td style="padding:5px 6px; text-align:right;">
                            @if($c->is_active)
                            <form method="POST" action="{{ route('cbe.accounting.asset-categories.deactivate', $c->category_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.deactivate_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.deactivate_button') }}</button>
                            </form>
                            @else
                            <span style="color:#9ca3af;">{{ __('cbe_records.inactive_label') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_asset_categories_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($categories->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $categories->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $categories->currentPage(), 'last' => $categories->lastPage(), 'total' => $categories->total()]) }}</span>
            @if($categories->hasMorePages())
                <a href="{{ $categories->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
