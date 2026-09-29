@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.bank_transaction_types_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     spec sections 1.3 (Bank Transaction Type) + 13 (GL Account
     Mapping). Bank Charge / Bank Interest carry a default GL account
     so a Bank Adjustment of that type pre-fills the correct account;
     every other type is classification only and needs no GL mapping. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.bank_transaction_types_page_title') }}</div>
        <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px;">
        <form method="POST" action="{{ route('cbe.finance.bank-transaction-types.store') }}" style="display:flex; gap:8px; align-items:flex-end;">
            @csrf
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_type_name') }}</label>
                <input type="text" name="type_name" maxlength="60" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_default_gl_account') }}</label>
                <select name="default_gl_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.field_no_default_gl_account') }}</option>
                    @foreach($accounts as $a)
                    <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_records.add_bank_transaction_type_button') }}</button>
        </form>
    </div>

    {{-- REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
         records by default. Nothing is queried or shown until a search
         term is entered and submitted. --}}
    <form method="GET" action="{{ route('cbe.finance.bank-transaction-types') }}" style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:8px; align-items:flex-end;">
        <div style="flex:1; position:relative;">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_type_name') }}</label>
            <input type="text" name="q" id="qInput" value="{{ request('q') }}" autocomplete="off" autofocus placeholder="{{ __('masterfile.start_typing') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
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
                fetch('{{ route('cbe.finance.bank-transaction-types.typeahead') }}?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:7px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            d.textContent = item.type_name;
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                input.value = item.type_name;
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
        @if(is_null($types))
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="font-size:10.5px; color:#9ca3af; text-align:center;">{{ __('cbe_accounting.coa_search_start_hint') }}</div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_type_name') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_default_gl_account') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($types as $t)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ !$t->is_active ? 'opacity:.5;' : '' }}">
                        <td style="padding:5px 6px; font-weight:600; color:#263238;">{{ $t->type_name }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $t->default_gl_account_name ?: __('cbe_records.field_no_default_gl_account') }}</td>
                        <td style="padding:5px 6px; text-align:right;">
                            @if($t->is_active && ! $t->is_system)
                            <form method="POST" action="{{ route('cbe.finance.bank-transaction-types.deactivate', $t->type_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.deactivate_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.deactivate_button') }}</button>
                            </form>
                            @elseif(! $t->is_active)
                            <span style="color:#9ca3af;">{{ __('cbe_records.inactive_label') }}</span>
                            @else
                            <span style="color:#9ca3af;">{{ __('cbe_records.system_type_label') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_bank_transaction_types_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($types->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $types->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $types->currentPage(), 'last' => $types->lastPage(), 'total' => $types->total()]) }}</span>
            @if($types->hasMorePages())
                <a href="{{ $types->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
