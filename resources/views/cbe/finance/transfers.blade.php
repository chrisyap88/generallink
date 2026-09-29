@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.transfers_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.transfers_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.finance.transfers.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.add_transfer_button') }}</a>
            <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_bank_accounts') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    {{-- REBUILT 22 Sep 2026 -- per Chris: no list screen may dump all
         records by default. Nothing is queried or shown until a search
         criterion is entered and submitted. --}}
    <form method="GET" action="{{ route('cbe.finance.transfers') }}" style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
        <div style="flex:1; min-width:160px; position:relative;">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.col_from_account') }} / {{ __('cbe_records.col_to_account') }}</label>
            <input type="text" name="account_q" id="qInput" value="{{ $accountQ }}" autocomplete="off" placeholder="{{ __('masterfile.start_typing') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            <div id="qDropdown" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.12); z-index:20; max-height:180px; overflow-y:auto; margin-top:2px;"></div>
        </div>
        <div>
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.col_from') }}</label>
            <input type="date" name="from_date" value="{{ $fromDate }}" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
        </div>
        <div>
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.col_to') }}</label>
            <input type="date" name="to_date" value="{{ $toDate }}" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
        </div>
        <div style="width:150px;">
            <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.col_status') }}</label>
            <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                <option value="">{{ __('cbe_accounting.coa_all_types') }}</option>
                @foreach(['PENDING','APPROVED','REJECTED'] as $st)
                <option value="{{ $st }}" {{ $status === $st ? 'selected' : '' }}>{{ __('cbe_records.status_'.strtolower($st)) }}</option>
                @endforeach
            </select>
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
                            d.textContent = item.bank_name + (item.account_name ? ' \u2014 ' + item.account_name : '');
                            d.onmousedown = function (e) {
                                e.preventDefault();
                                input.value = item.bank_name;
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
        @if(is_null($transfers))
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="font-size:10.5px; color:#9ca3af; text-align:center;">{{ __('cbe_accounting.coa_search_start_hint') }}</div>
        </div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_from_account') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_to_account') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_purpose') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $trStatusColors = ['PENDING' => '#D97706', 'APPROVED' => '#2e7d32', 'REJECTED' => '#c62828']; @endphp
                    @forelse($transfers as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->transfer_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $t->from_bank }}@if($t->from_name) — {{ $t->from_name }}@endif</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $t->to_bank }}@if($t->to_name) — {{ $t->to_name }}@endif</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($t->amount, 2) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $t->purpose ?: '—' }}</td>
                        <td style="padding:5px 8px;"><span style="color:{{ $trStatusColors[$t->status] ?? '#6b7280' }}; font-weight:600; white-space:nowrap;">{{ __('cbe_records.status_'.strtolower($t->status)) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_transfers_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($transfers->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $transfers->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $transfers->currentPage(), 'last' => $transfers->lastPage(), 'total' => $transfers->total()]) }}</span>
            @if($transfers->hasMorePages())
                <a href="{{ $transfers->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
