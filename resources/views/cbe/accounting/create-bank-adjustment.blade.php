@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.create_bank_adjustment_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 3, spec section 3.6. Records both sides of a bank charge /
     interest / other adjustment the treasurer only sees on the
     statement itself: a Bank Transaction (bank side) AND a GL journal
     (system side), then matches them to each other immediately. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.create_bank_adjustment_page_title') }}</div>
        <a href="{{ route('cbe.accounting.bank-reconciliations.match', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.adjustment.store', $reconciliation->reconciliation_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px; max-width:520px;">
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_adjustment_type') }}</label>
                        <select name="adjustment_type" id="adjType" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="CHARGE" {{ old('adjustment_type') === 'CHARGE' ? 'selected' : '' }}>{{ __('cbe_accounting.adjustment_type_charge') }}</option>
                            <option value="INTEREST" {{ old('adjustment_type') === 'INTEREST' ? 'selected' : '' }}>{{ __('cbe_accounting.adjustment_type_interest') }}</option>
                            <option value="OTHER" {{ old('adjustment_type') === 'OTHER' ? 'selected' : '' }}>{{ __('cbe_accounting.adjustment_type_other') }}</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_adjustment_date') }}</label>
                        <input type="date" name="adjustment_date" value="{{ old('adjustment_date', $reconciliation->statement_date) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.col_description') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;" id="glAccountWrap">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_adjustment_gl_account') }}</label>
                        <select name="gl_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">{{ __('cbe_records.field_no_default_gl_account') }}</option>
                            @foreach($accounts as $a)
                            <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="font-size:9px; color:#9ca3af; line-height:1.5;">{{ __('cbe_accounting.bank_adjustment_helper_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.bank-reconciliations.match', $reconciliation->reconciliation_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var typeSel = document.getElementById('adjType');
    var wrap = document.getElementById('glAccountWrap');
    function toggle() {
        wrap.style.display = typeSel.value === 'OTHER' ? 'block' : 'none';
    }
    typeSel.addEventListener('change', toggle);
    toggle();

    // NEW 19 Sep 2026 -- per Chris: AI-power keyword auto-suggest for
    // the GL Account picker, matched against Chart of Accounts code/
    // name text. Only fills it if still on the placeholder.
    var HINTS = {!! json_encode($glHints) !!};
    var descInput = document.querySelector('input[name="description"]');
    var sel = document.querySelector('select[name="gl_account_id"]');
    function suggest() {
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
    if (descInput) { descInput.addEventListener('blur', suggest); }
})();
</script>
@endsection
