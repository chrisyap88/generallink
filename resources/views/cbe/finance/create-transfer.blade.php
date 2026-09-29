@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.add_transfer_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.add_transfer_button') }}</div>
        <a href="{{ route('cbe.finance.transfers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if($accounts->count() < 2)
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#8d6e00; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">{{ __('cbe_records.need_two_accounts_note') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.finance.transfers.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:9px; max-width:480px;">
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_from_account') }}</label>
                        <select name="from_account_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">—</option>
                            @foreach($accounts as $a)
                            <option value="{{ $a->bank_account_id }}" {{ old('from_account_id') == $a->bank_account_id ? 'selected' : '' }}>{{ $a->bank_name }}@if($a->account_name) — {{ $a->account_name }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_to_account') }}</label>
                        <select name="to_account_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">—</option>
                            @foreach($accounts as $a)
                            <option value="{{ $a->bank_account_id }}" {{ old('to_account_id') == $a->bank_account_id ? 'selected' : '' }}>{{ $a->bank_name }}@if($a->account_name) — {{ $a->account_name }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_transfer_date') }}</label>
                        <input type="date" name="transfer_date" value="{{ old('transfer_date', now()->toDateString()) }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_reference_no') }}</label>
                    <input type="text" name="reference_no" value="{{ old('reference_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_purpose') }}</label>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.finance.transfers') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
