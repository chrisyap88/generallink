@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.statement_add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.statement_add_button') }}</div>
        <a href="{{ route('cbe.finance.index', ['tab' => 'statements']) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if(session('success'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700; margin-bottom:6px;">✓ {{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.finance.statements.store') }}" enctype="multipart/form-data" id="stmt-form" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:10px; max-width:480px;">

                <div>
                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px;">
                        <label style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_bank_account') }}</label>
                        <a href="javascript:void(0)" onclick="stmtToggleNewAccount()" style="font-size:8.5px; color:var(--gl-blue); font-weight:700; text-decoration:none;">{{ __('cbe_records.btn_add_bank_account') }}</a>
                    </div>
                    <select name="bank_account_id" id="stmt-bank-account" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.field_bank_account_none') }}</option>
                        @foreach($bankAccounts as $ba)
                        <option value="{{ $ba->bank_account_id }}" {{ old('bank_account_id') == $ba->bank_account_id ? 'selected' : '' }}>{{ $ba->bank_name }}{{ $ba->account_name ? ' — '.$ba->account_name : '' }} ({{ $ba->account_number }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- NEW 27 Aug 2026 (Task #222) — quick inline "add bank
                account" so an empty dropdown never blocks the upload.
                Plain div + fetch(), NOT a nested <form> — this whole
                block sits inside the main Upload Statement form, and
                HTML does not allow a <form> inside another <form>. --}}
                <div id="stmt-new-account" style="display:none; background:var(--gl-light); border-radius:6px; padding:8px;">
                    <div style="font-size:9px; font-weight:700; color:var(--gl-blue); margin-bottom:6px;">{{ __('cbe_records.new_bank_account_title') }}</div>
                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                        <input type="text" id="stmt-new-bank-name" placeholder="{{ __('cbe_records.field_bank_name') }}" style="flex:1; min-width:110px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10px; box-sizing:border-box;">
                        <input type="text" id="stmt-new-account-name" placeholder="{{ __('cbe_records.field_account_name') }}" style="flex:1; min-width:110px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10px; box-sizing:border-box;">
                        <input type="text" id="stmt-new-account-number" placeholder="{{ __('cbe_records.field_account_number') }}" style="flex:1; min-width:110px; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10px; box-sizing:border-box;">
                        <button type="button" onclick="stmtSaveNewAccount()" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:5px 12px; font-size:9.5px; font-weight:700; cursor:pointer;">{{ __('cbe_records.btn_save_bank_account') }}</button>
                    </div>
                    <div id="stmt-new-account-status" style="font-size:8.5px; margin-top:4px;"></div>
                </div>

                <div style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_month') }}</label>
                        <select name="statement_month" id="stmt-month" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            @foreach(range(1,12) as $m)
                            <option value="{{ $m }}" {{ old('statement_month') == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate(2000, $m, 1)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_year') }}</label>
                        <input type="number" name="statement_year" id="stmt-year" value="{{ old('statement_year', now()->year) }}" min="2000" max="2100" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_attachment') }}</label>
                    <input type="file" name="attachment" id="stmt-attachment" accept=".jpg,.jpeg,.png,.pdf" required style="width:100%; font-size:10.5px;">
                    <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('cbe_records.attachment_hint') }}</div>
                </div>

                {{-- NEW 27 Aug 2026 (Task #222) — per Chris: wire the
                statement upload to the same AI document-extraction
                service used for insurance documents. Suggests the
                closing balance + period; the officer reviews/edits
                before Save — never auto-posted blind. --}}
                <div style="display:flex; align-items:center; gap:8px;">
                    <button type="button" id="stmt-extract-btn" onclick="stmtExtract()" style="background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:20px; padding:6px 16px; font-size:10px; font-weight:700; cursor:pointer;">🤖 {{ __('cbe_records.btn_read_document') }}</button>
                    <span id="stmt-extract-status" style="font-size:9px; color:#94A3B8;"></span>
                </div>

                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_closing_balance') }}</label>
                    <input type="number" step="0.01" name="closing_balance" id="stmt-closing-balance" value="{{ old('closing_balance') }}" placeholder="{{ __('cbe_records.field_closing_balance_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    <div style="font-size:9px; color:#9ca3af; margin-top:2px;">{{ __('cbe_records.closing_balance_hint') }}</div>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.finance.index', ['tab' => 'statements']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>

<script>
function stmtToggleNewAccount(){
    var box = document.getElementById('stmt-new-account');
    box.style.display = box.style.display === 'none' ? 'block' : 'none';
}

function stmtSaveNewAccount(){
    var statusEl = document.getElementById('stmt-new-account-status');
    var bankName = document.getElementById('stmt-new-bank-name').value.trim();
    var accountName = document.getElementById('stmt-new-account-name').value.trim();
    var accountNumber = document.getElementById('stmt-new-account-number').value.trim();

    if (! bankName || ! accountNumber) {
        statusEl.textContent = '{{ __('cbe_records.err_bank_account_required') }}';
        statusEl.style.color = '#c62828';
        return;
    }

    var fd = new FormData();
    fd.append('bank_name', bankName);
    fd.append('account_name', accountName);
    fd.append('account_number', accountNumber);
    fd.append('_token', document.querySelector('input[name="_token"]').value);

    fetch('{{ route('cbe.finance.bank-accounts.store') }}', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then(function(r){ return r.json(); })
        .then(function(result){
            if (result.status !== 'OK') {
                statusEl.textContent = '{{ __('cbe_records.extract_failed') }}';
                statusEl.style.color = '#c62828';
                return;
            }
            var select = document.getElementById('stmt-bank-account');
            var opt = document.createElement('option');
            opt.value = result.bank_account_id;
            opt.textContent = result.label;
            opt.selected = true;
            select.appendChild(opt);
            statusEl.textContent = '{{ __('cbe_records.bank_account_saved') }}';
            statusEl.style.color = '#2e7d32';
            document.getElementById('stmt-new-bank-name').value = '';
            document.getElementById('stmt-new-account-name').value = '';
            document.getElementById('stmt-new-account-number').value = '';
        })
        .catch(function(){
            statusEl.textContent = '{{ __('cbe_records.extract_failed') }}';
            statusEl.style.color = '#c62828';
        });
}

function stmtExtract(){
    var fileInput = document.getElementById('stmt-attachment');
    var statusEl = document.getElementById('stmt-extract-status');
    if (! fileInput.files.length) {
        statusEl.textContent = '{{ __('cbe_records.err_choose_file_first') }}';
        statusEl.style.color = '#c62828';
        return;
    }
    var fd = new FormData();
    fd.append('attachment', fileInput.files[0]);
    fd.append('_token', document.querySelector('input[name="_token"]').value);

    statusEl.textContent = '{{ __('cbe_records.extract_reading') }}';
    statusEl.style.color = '#94A3B8';

    fetch('{{ route('cbe.finance.statements.extract') }}', { method: 'POST', body: fd })
        .then(function(r){ return r.json(); })
        .then(function(result){
            if (result.status !== 'OK') {
                statusEl.textContent = result.message || '{{ __('cbe_records.extract_failed') }}';
                statusEl.style.color = '#c62828';
                return;
            }
            var v = result.values || {};
            if (v.closing_balance) {
                var num = parseFloat(String(v.closing_balance).replace(/[^0-9.\-]/g, ''));
                if (! isNaN(num)) document.getElementById('stmt-closing-balance').value = num.toFixed(2);
            }
            if (v.statement_month) {
                var m = parseInt(v.statement_month, 10);
                if (m >= 1 && m <= 12) document.getElementById('stmt-month').value = m;
            }
            if (v.statement_year) {
                var y = parseInt(v.statement_year, 10);
                if (y >= 2000 && y <= 2100) document.getElementById('stmt-year').value = y;
            }
            statusEl.textContent = '{{ __('cbe_records.extract_done') }}';
            statusEl.style.color = '#2e7d32';
        })
        .catch(function(){
            statusEl.textContent = '{{ __('cbe_records.extract_failed') }}';
            statusEl.style.color = '#c62828';
        });
}
</script>
@endsection
