@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_invoice_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_invoice_button') }}</div>
        <a href="{{ route('cbe.accounting.invoices') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    @if($customers->isEmpty())
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#7a5c00; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:8px;">
        {{ __('cbe_accounting.no_customers_yet_note') }} <a href="{{ route('cbe.accounting.customers.create') }}" style="color:var(--gl-blue); font-weight:600;">{{ __('cbe_accounting.add_customer_button') }}</a>
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.invoices.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px;">
                <div style="flex:1.4;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_customer') }}</label>
                    <select name="customer_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($customers as $c)
                        <option value="{{ $c->customer_id }}" {{ old('customer_id') == $c->customer_id ? 'selected' : '' }}>{{ $c->customer_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width:120px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_date') }}</label>
                    <input type="date" name="invoice_date" value="{{ old('invoice_date') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:120px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_due_date') }}</label>
                    <input type="date" name="due_date" value="{{ old('due_date') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:150px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_no') }}</label>
                    <input type="text" name="invoice_no" value="{{ old('invoice_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
            </div>

            <div style="flex-shrink:0; display:flex; justify-content:space-between; margin-bottom:4px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.bill_lines_helper_note') }}</div>
                <a href="{{ route('cbe.accounting.tax-rates') }}" style="font-size:8px; color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('cbe_accounting.manage_tax_rates_link') }}</a>
            </div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                            <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_category') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:60px;">{{ __('cbe_accounting.col_line_qty') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:90px;">{{ __('cbe_accounting.col_line_unit_price') }}</th>
                            <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase; width:80px;">{{ __('cbe_accounting.col_line_tax') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 0; $i < 5; $i++)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 6px;">
                                <input type="text" name="line_description[]" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                            </td>
                            <td style="padding:4px 6px;">
                                <select name="line_category[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_records.none_option') }}</option>
                                    @foreach($categories as $c)
                                    <option value="{{ $c->category_id }}" data-zh="{{ $c->category_name_zh }}">{{ $c->category_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_qty[]" value="1" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <input type="number" step="0.01" min="0" name="line_unit_price[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 6px;">
                                <select name="line_tax_rate[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="0">{{ __('cbe_accounting.tax_no_tax') }}</option>
                                    @foreach($taxRates as $t)
                                    <option value="{{ $t->rate_percent }}">{{ $t->rate_name }} {{ rtrim(rtrim(number_format($t->rate_percent, 2), '0'), '.') }}%</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.invoices') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest. Same
    // GL_CATEGORY_HINTS list already used on the AI Accounting screen,
    // matched here client-side since these lines are typed live, not
    // pre-extracted. Only fills a row's Category if that row's dropdown
    // is still on "None" -- never overrides a category the treasurer
    // already picked.
    var HINTS = {!! json_encode($glHints) !!};
    function suggest(descInput) {
        var row = descInput.closest('tr');
        var sel = row ? row.querySelector('select[name="line_category[]"]') : null;
        if (!sel || sel.value !== '') { return; }
        var text = descInput.value.toLowerCase();
        if (!text) { return; }
        for (var i = 0; i < HINTS.length; i++) {
            var rule = HINTS[i];
            var kwHit = false;
            for (var k = 0; k < rule.keywords.length; k++) {
                if (text.indexOf(rule.keywords[k]) !== -1) { kwHit = true; break; }
            }
            if (!kwHit) { continue; }
            var opts = sel.options;
            for (var j = 0; j < opts.length; j++) {
                var optText = (opts[j].textContent + ' ' + (opts[j].getAttribute('data-zh') || '')).toLowerCase();
                var hintHit = false;
                for (var h = 0; h < rule.hints.length; h++) {
                    if (optText.indexOf(rule.hints[h]) !== -1) { hintHit = true; break; }
                }
                if (hintHit) {
                    sel.value = opts[j].value;
                    sel.style.background = '#eef7ee';
                    return;
                }
            }
        }
    }
    document.querySelectorAll('input[name="line_description[]"]').forEach(function (inp) {
        inp.addEventListener('blur', function () { suggest(inp); });
    });
})();
</script>
@endsection
