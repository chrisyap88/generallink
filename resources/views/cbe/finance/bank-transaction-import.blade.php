@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.import_transactions_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.import_transactions_button') }} — {{ $account->bank_name }}</div>
        <a href="{{ route('cbe.finance.bank-transactions', $account->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.finance.bank-transaction-import.store', $account->bank_account_id) }}" enctype="multipart/form-data" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:10px; max-width:560px;">
                <div style="font-size:10px; color:#546E7A; line-height:1.6;">{{ __('cbe_records.import_instructions_note') }}</div>
                <div style="background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:6px; padding:8px 10px; font-size:9.5px; color:#455A64; line-height:1.6;">
                    date, description, reference, cheque_no, debit, credit, bank_reference
                </div>
                <a href="{{ route('cbe.finance.bank-transaction-import.template') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10px; font-weight:600;">{{ __('cbe_records.download_template_button') }}</a>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_import_file') }}</label>
                    <input type="file" name="import_file" accept=".csv,.txt,.xlsx,.xls" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; background:#fff;">
                    <div style="font-size:8.5px; color:#9ca3af; margin-top:3px;">{{ __('cbe_records.import_file_hint') }}</div>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.finance.bank-transactions', $account->bank_account_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.upload_and_preview_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
