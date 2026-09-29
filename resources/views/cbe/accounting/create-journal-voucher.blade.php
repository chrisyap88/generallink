@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_journal_voucher_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_journal_voucher_button') }}</div>
        <a href="{{ route('cbe.accounting.journal-vouchers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.journal-vouchers.store') }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:8px;">
                <div style="width:135px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_jv_date') }}</label>
                    <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_jv_description') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:135px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_journal_type') }}</label>
                    <select name="journal_type_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        @foreach($journalTypes as $t)
                        <option value="{{ $t->type_id }}" {{ old('journal_type_id') == $t->type_id || (!old('journal_type_id') && $t->type_code === 'GENERAL') ? 'selected' : '' }}>{{ $t->type_name_zh ? $t->type_name.' ('.$t->type_name_zh.')' : $t->type_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width:170px;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_jv_attachment') }}</label>
                    <input type="file" name="attachment" accept=".jpg,.jpeg,.png,.pdf" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:4px 6px; font-size:9px; box-sizing:border-box;">
                </div>
            </div>

            <div style="font-size:8px; color:#9ca3af; margin-bottom:4px;">{{ __('cbe_accounting.jv_lines_helper_note') }}</div>

            <div style="flex:1; min-height:0; overflow:hidden;">
                <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                    <thead>
                        <tr style="background:var(--gl-light);">
                            <th style="text-align:left; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_name') }}</th>
                            <th style="text-align:left; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_line_description') }}</th>
                            <th style="text-align:left; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_cost_centre') }}</th>
                            <th style="text-align:left; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_fund') }}</th>
                            <th style="text-align:right; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase; width:95px;">{{ __('cbe_accounting.col_jv_debit') }}</th>
                            <th style="text-align:right; padding:4px 5px; font-size:8px; color:#546E7A; text-transform:uppercase; width:95px;">{{ __('cbe_accounting.col_jv_credit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @for($i = 0; $i < 6; $i++)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:4px 5px;">
                                <select name="line_account[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                                    @foreach($accounts as $a)
                                    <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 5px;">
                                <input type="text" name="line_description[]" maxlength="255" placeholder="{{ __('cbe_accounting.col_jv_line_description') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                            </td>
                            <td style="padding:4px 5px;">
                                <select name="line_cost_centre[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_accounting.coa_no_group') }}</option>
                                    @foreach($costCentres as $c)
                                    <option value="{{ $c->centre_id }}">{{ $c->centre_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 5px;">
                                <select name="line_fund[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box;">
                                    <option value="">{{ __('cbe_accounting.coa_no_group') }}</option>
                                    @foreach($funds as $f)
                                    <option value="{{ $f->fund_id }}">{{ $f->fund_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:4px 5px;">
                                <input type="number" step="0.01" min="0" name="line_debit[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                            <td style="padding:4px 5px;">
                                <input type="number" step="0.01" min="0" name="line_credit[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 6px; font-size:9.5px; box-sizing:border-box; text-align:right;">
                            </td>
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.journal-vouchers') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest for
    // the GL Account picker. This screen picks the Chart of Accounts
    // directly (not a Category), so matching runs against each
    // account's own code/name text instead. Only fills a row's GL
    // Account if that row's dropdown is still on the placeholder --
    // never overrides an account the treasurer already picked.
    var HINTS = {!! json_encode($glHints) !!};
    function suggest(descInput) {
        var row = descInput.closest('tr');
        var sel = row ? row.querySelector('select[name="line_account[]"]') : null;
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
                var optText = opts[j].textContent.toLowerCase();
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
