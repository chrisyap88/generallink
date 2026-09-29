@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.bank_accounts_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.bank_accounts_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.finance.cash-position') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.tile_cash_position') }}</a>
            <a href="{{ route('cbe.finance.transfers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.tile_bank_transfers') }}</a>
            <a href="{{ route('cbe.finance.bank-transaction-types') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.bank_transaction_types_page_title') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliation-rules') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.bank_reconciliation_rules_page_title') }}</a>
            <a href="{{ route('cbe.finance.bank-accounts.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.add_bank_account_button') }}</a>
            <a href="{{ route('cbe.finance.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_finance') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#8d6e00; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('warning') }}</div>
    @endif

    {{-- REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
         records by default. Nothing is queried or shown until a search
         term is entered and submitted. --}}
    <form method="GET" action="{{ route('cbe.finance.bank-accounts') }}" style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:8px; align-items:flex-end;">
        <div style="flex:1; position:relative;">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.bank_account_search_label') }}</label>
            <input type="text" name="q" id="qInput" value="{{ $q }}" autocomplete="off" autofocus placeholder="{{ __('cbe_records.bank_account_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
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
                fetch('{{ route('cbe.finance.bank-accounts.typeahead') }}?q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.length) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.forEach(function (item) {
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:7px 10px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                            d.textContent = item.bank_name + (item.account_name ? ' \u2014 ' + item.account_name : '') + (item.account_number ? ' (' + item.account_number + ')' : '');
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                input.value = item.account_number || item.bank_name;
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
        @if(is_null($accounts))
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="font-size:10.5px; color:#9ca3af; text-align:center;">{{ __('cbe_accounting.coa_search_start_hint') }}</div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;" title="{{ __('cbe_records.col_account_code_helper') }}">{{ __('cbe_records.col_account_code') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_bank') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_account_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_account_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_current_balance') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $a)
                    <tr style="border-bottom:1px solid #f3f4f6; {{ !$a->is_active ? 'opacity:.5;' : '' }}">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $a->account_code ?: '—' }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $a->bank_name }}@if($a->account_name)<span style="color:#9ca3af;"> — {{ $a->account_name }}</span>@endif</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->account_number }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.acct_type_'.strtolower($a->account_type ?: 'bank_current')) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($a->current_balance, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            <a href="{{ route('cbe.finance.bank-transactions', $a->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; margin-right:8px;">{{ __('cbe_records.transactions_link_short') }}</a>
                            <a href="{{ route('cbe.finance.bank-accounts.edit', $a->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; margin-right:8px;">{{ __('cbe_records.edit_button') }}</a>
                            @if($a->is_active)
                            <form method="POST" action="{{ route('cbe.finance.bank-accounts.deactivate', $a->bank_account_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.deactivate_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.deactivate_button') }}</button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('cbe.finance.bank-accounts.reactivate', $a->bank_account_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#2e7d32; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.reactivate_button') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_bank_accounts_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
