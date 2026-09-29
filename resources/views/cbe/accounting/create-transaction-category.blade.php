@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.transaction_categories_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_transaction_category_button') }}</div>
        <a href="{{ route('cbe.accounting.transaction-categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_coa') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="flex-shrink:0; font-size:9px; color:#546E7A; margin-bottom:8px;">{{ __('cbe_accounting.transaction_category_note') }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; max-width:600px;">
        <form method="POST" action="{{ route('cbe.accounting.transaction-categories.store') }}">
            @csrf
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name') }}</label>
                <input type="text" name="category_name" maxlength="150" required autofocus style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
            </div>
            <div style="margin-bottom:10px;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name_zh') }}</label>
                <input type="text" name="category_name_zh" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
            </div>
            <div style="display:flex; gap:10px; margin-bottom:14px;">
                <div style="width:150px;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_account_type') }}</label>
                    <select name="type" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
                        <option value="EXPENSE">{{ __('cbe_accounting.type_expense') }}</option>
                        <option value="INCOME">{{ __('cbe_accounting.type_income') }}</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_gl_account') }}</label>
                    <select name="chart_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 9px; font-size:11px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.coa_no_parent') }}</option>
                        @foreach($glAccounts as $a)
                        <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.add_transaction_category_button') }}</button>
        </form>
    </div>
</div>
@endsection
