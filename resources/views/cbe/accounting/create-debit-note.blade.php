@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_debit_note_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_debit_note_button') }}</div>
        <a href="{{ route('cbe.accounting.debit-notes') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.debit-notes.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            @if($fromReturn)
            <input type="hidden" name="return_id" value="{{ $fromReturn->return_id }}">
            <div style="flex-shrink:0; font-size:9px; color:#9ca3af; margin-bottom:6px;">{{ __('cbe_accounting.dn_from_return_note', ['ref' => $fromReturn->doc_ref_no]) }}</div>
            @endif
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:9px; max-width:480px;">
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_bill_supplier') }}</label>
                    <select name="supplier_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($suppliers as $s)
                        <option value="{{ $s->supplier_id }}" {{ (old('supplier_id') ?: ($fromReturn->supplier_id ?? null)) == $s->supplier_id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_dn_bill') }}</label>
                    <select name="bill_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.field_dn_bill_none') }}</option>
                        @foreach($bills as $b)
                        <option value="{{ $b->bill_id }}" {{ old('bill_id') == $b->bill_id ? 'selected' : '' }}>{{ $b->doc_ref_no ?: $b->bill_no }} — RM {{ number_format($b->amount - $b->paid_amount, 2) }} {{ __('cbe_accounting.field_dn_bill_outstanding_suffix') }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:8px; color:#9ca3af; margin-top:3px;">{{ __('cbe_accounting.dn_bill_helper_note') }}</div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_dn_date') }}</label>
                        <input type="date" name="note_date" value="{{ old('note_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_dn_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $returnAmount ?? '') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_dn_reason') }}</label>
                    <input type="text" name="reason" value="{{ old('reason', $fromReturn ? __('cbe_accounting.dn_reason_from_return', ['ref' => $fromReturn->doc_ref_no]) : '') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.debit-notes') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
