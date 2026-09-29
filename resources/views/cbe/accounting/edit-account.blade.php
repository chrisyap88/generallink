@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.edit_account_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.edit_account_title') }} — {{ $account->account_code }} <span style="font-size:9px; font-weight:600; color:var(--gl-blue);">({{ is_null($account->cbe_node_id) ? __('cbe_accounting.coa_scope_shared') : __('cbe_accounting.coa_scope_local') }})</span></div>
            <div style="font-size:8.5px; color:#9ca3af; margin-top:2px;">{{ __('cbe_accounting.coa_created_by_note', ['name' => $account->created_by_name ?: __('cbe_accounting.coa_created_by_unknown'), 'date' => \Carbon\Carbon::parse($account->created_at)->format('d M Y')]) }}</div>
        </div>
        <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if($account->is_system)
    <div style="flex-shrink:0; background:var(--gl-light); border-left:3px solid var(--gl-blue); color:#37474F; border-radius:6px; padding:5px 10px; font-size:9.5px; margin-bottom:6px;">{{ __('cbe_accounting.system_account_edit_note') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.chart-of-accounts.update', $account->account_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            @method('PUT')
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:7px 14px; align-content:start;">
                {{-- REORDERED 19 Sep 2026 per Chris, same as the Add
                     screen: Type -> Category -> Group -> Code -> Name ->
                     Name(zh) -> Description -> Parent -> flags. Normal
                     Balance dropdown REMOVED — always auto-derived from
                     Account Type server-side (self::defaultNormalBalance())
                     when not sent, so removing the box changes nothing
                     except no longer asking a non-accountant to answer a
                     question they can't. --}}
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_type') }}</label>
                    <select name="account_type" {{ $account->is_system ? 'disabled' : '' }} style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box; {{ $account->is_system ? 'background:#f3f4f6;' : '' }}">
                        @foreach(['ASSET','LIABILITY','EQUITY','INCOME','EXPENSE'] as $t)
                        <option value="{{ $t }}" {{ old('account_type', $account->account_type) === $t ? 'selected' : '' }}>{{ __('cbe_accounting.type_'.strtolower($t)) }}</option>
                        @endforeach
                    </select>
                    @if($account->is_system)<input type="hidden" name="account_type" value="{{ $account->account_type }}">@endif
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_category') }}</label>
                    <input type="text" id="categoryInput" placeholder="{{ __('cbe_accounting.coa_category_search_placeholder') }}" autocomplete="off" value="{{ old('account_category_label', $selectedCategoryLabel) }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <input type="hidden" name="account_category_id" id="categoryHidden" value="{{ old('account_category_id', $account->account_category_id) }}">
                    <div id="categoryDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_group') }}</label>
                    <select name="account_group_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.coa_no_group') }}</option>
                        @foreach($groups as $g)
                        <option value="{{ $g->group_id }}" {{ old('account_group_id', $account->account_group_id) == $g->group_id ? 'selected' : '' }}>{{ $g->group_name_zh ? $g->group_name.' ('.$g->group_name_zh.')' : $g->group_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_code') }}</label>
                    <input type="text" name="account_code" value="{{ old('account_code', $account->account_code) }}" maxlength="20" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_name') }}</label>
                    <input type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_name_zh') }}</label>
                    <input type="text" name="account_name_zh" value="{{ old('account_name_zh', $account->account_name_zh) }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_description') }}</label>
                    <input type="text" name="description" value="{{ old('description', $account->description) }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_parent_account') }}</label>
                    @if($account->is_system)
                    <input type="text" value="{{ $selectedParentLabel ?: __('cbe_accounting.coa_no_parent') }}" disabled style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box; background:#f3f4f6;">
                    <input type="hidden" name="parent_account_id" value="{{ $account->parent_account_id }}">
                    @else
                    <input type="text" id="parentInput" placeholder="{{ __('cbe_accounting.coa_parent_search_placeholder') }}" autocomplete="off" value="{{ old('parent_account_label', $selectedParentLabel) }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <input type="hidden" name="parent_account_id" id="parentHidden" value="{{ old('parent_account_id', $account->parent_account_id) }}">
                    <div id="parentDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:200px; overflow-y:auto; margin-top:2px;"></div>
                    @endif
                </div>
                <div style="grid-column:1 / -1; display:flex; align-items:center; gap:18px; margin-top:2px;">
                    <label style="display:flex; align-items:center; gap:6px; font-size:10px; font-weight:600; color:#263238;">
                        <input type="checkbox" name="is_posting_account" value="1" {{ old('is_posting_account', $account->is_posting_account) ? 'checked' : '' }} style="width:14px; height:14px;"> {{ __('cbe_accounting.field_posting_account') }}
                    </label>
                    <label style="display:flex; align-items:center; gap:6px; font-size:10px; font-weight:600; color:#263238;">
                        <input type="checkbox" name="is_control_account" value="1" {{ old('is_control_account', $account->is_control_account) ? 'checked' : '' }} style="width:14px; height:14px;"> {{ __('cbe_accounting.field_control_account') }}
                    </label>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                {{-- NEW 19 Sep 2026 -- per Chris: "after search i can have
                     edit or delete" -- Deactivate/Reactivate now lives
                     right on this Edit screen, so Search -> pick a match
                     -> Edit-or-Deactivate all happens without another
                     screen. System accounts can't be deactivated (same
                     rule CbeAccountingController::deactivateAccount()
                     already enforces server-side). --}}
                <div>
                    @if(! $account->is_system)
                        @if($account->is_active)
                        <form method="POST" action="{{ route('cbe.accounting.chart-of-accounts.deactivate', $account->account_id) }}" onsubmit="return confirm({{ json_encode(__('cbe_accounting.coa_deactivate_confirm_js')) }});">
                            @csrf
                            <button type="submit" style="background:none; border:1px solid #e53935; color:#e53935; border-radius:20px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.deactivate_button') }}</button>
                        </form>
                        @else
                        <form method="POST" action="{{ route('cbe.accounting.chart-of-accounts.reactivate', $account->account_id) }}">
                            @csrf
                            <button type="submit" style="background:none; border:1px solid #38A169; color:#38A169; border-radius:20px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.reactivate_button') }}</button>
                        </form>
                        @endif
                    @endif
                </div>
                <div style="display:flex; gap:8px;">
                    <a href="{{ route('cbe.accounting.chart-of-accounts') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                    <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function coaInitTypeahead(opts) {
    var input = document.getElementById(opts.inputId);
    var hidden = document.getElementById(opts.hiddenId);
    var dropdown = document.getElementById(opts.dropdownId);
    var timer;
    input.addEventListener('input', function () {
        hidden.value = '';
        clearTimeout(timer);
        var q = this.value.trim();
        if (q.length < 1) { dropdown.style.display = 'none'; return; }
        timer = setTimeout(function () {
            fetch(opts.url + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.length) {
                        dropdown.innerHTML = '<div style="padding:7px 10px; font-size:10.5px; color:#9ca3af;">' + opts.noMatch + '</div>';
                        dropdown.style.display = 'block';
                        return;
                    }
                    dropdown.innerHTML = '';
                    data.forEach(function (item) {
                        var d = document.createElement('div');
                        d.style.cssText = 'padding:7px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        var label = opts.renderLabel(item);
                        d.textContent = label;
                        d.onmousedown = function (e) {
                            e.preventDefault();
                            input.value = label;
                            hidden.value = opts.idField(item);
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

coaInitTypeahead({
    inputId: 'categoryInput', hiddenId: 'categoryHidden', dropdownId: 'categoryDropdown',
    url: '{{ route('cbe.accounting.chart-of-accounts.category-typeahead') }}',
    noMatch: @json(__('cbe_accounting.coa_no_matches_found')),
    renderLabel: function (item) { return item.category_name + (item.category_name_zh ? ' (' + item.category_name_zh + ')' : ''); },
    idField: function (item) { return item.category_id; }
});
@if(! $account->is_system)
coaInitTypeahead({
    inputId: 'parentInput', hiddenId: 'parentHidden', dropdownId: 'parentDropdown',
    url: '{{ route('cbe.accounting.chart-of-accounts.parent-typeahead') }}?exclude={{ $account->account_id }}',
    noMatch: @json(__('cbe_accounting.coa_no_matches_found')),
    renderLabel: function (item) { return item.account_code + ' — ' + item.account_name; },
    idField: function (item) { return item.account_id; }
});
@endif
</script>
@endsection
