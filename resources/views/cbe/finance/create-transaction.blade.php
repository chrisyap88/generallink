@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.transaction_add_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.transaction_add_button') }}</div>
        <a href="{{ route('cbe.finance.index', ['tab' => 'transactions']) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    @if($categories->isEmpty())
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#7a5c00; border-radius:6px; padding:8px 10px; font-size:10.5px; margin-bottom:8px;">
        {{ __('cbe_records.no_categories_note') }} <a href="{{ route('cbe.finance.categories') }}" style="color:var(--gl-blue); font-weight:600;">{{ __('cbe_records.manage_categories_link') }}</a>
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.finance.transactions.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:9px; max-width:520px;">
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_transaction_date') }}</label>
                        <input type="date" name="transaction_date" value="{{ old('transaction_date') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_amount') }}</label>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_category') }}</label>
                        <select name="category_id" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                            @foreach($categories as $c)
                            <option value="{{ $c->category_id }}" {{ old('category_id') == $c->category_id ? 'selected' : '' }}>{{ $c->type === 'INCOME' ? __('cbe_records.income_prefix') : __('cbe_records.expense_prefix') }} — {{ $c->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_account_type') }}</label>
                        <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            @foreach($bankAccounts as $b)
                            <option value="{{ $b->bank_account_id }}" {{ old('bank_account_id') == $b->bank_account_id ? 'selected' : '' }}>{{ $b->bank_name }}@if($b->account_name) — {{ $b->account_name }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_description') }}</label>
                    <input type="text" name="description" value="{{ old('description') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_linked_statement') }}</label>
                    <select name="bank_statement_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.none_option') }}</option>
                        @foreach($statements as $s)
                        <option value="{{ $s->statement_id }}" {{ old('bank_statement_id') == $s->statement_id ? 'selected' : '' }}>{{ \Carbon\Carbon::createFromDate($s->statement_year, $s->statement_month, 1)->format('M Y') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.finance.index', ['tab' => 'transactions']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
