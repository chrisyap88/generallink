@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.asset_categories_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_asset_category_button') }}</div>
        <a href="{{ route('cbe.accounting.asset-categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.asset-categories.store') }}">
            @csrf
            <div style="display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
                <div style="flex:1;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_category_name') }}</label>
                    <input type="text" name="category_name" maxlength="150" required autofocus style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:130px;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_default_useful_life') }}</label>
                    <input type="number" step="1" min="1" name="default_useful_life_months" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div style="width:150px;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_depreciation_method') }}</label>
                    <select name="default_depreciation_method" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="STRAIGHT_LINE">{{ __('cbe_accounting.depr_method_straight_line') }}</option>
                        <option value="REDUCING_BALANCE">{{ __('cbe_accounting.depr_method_reducing_balance') }}</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:8px; align-items:flex-end;">
                <div style="flex:1;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_gl_fixed_asset_account') }}</label>
                    <select name="fixed_asset_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.field_gl_use_default') }}</option>
                        @foreach($accounts as $a)
                        <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_gl_accum_depreciation_account') }}</label>
                    <select name="accum_depreciation_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.field_gl_use_default') }}</option>
                        @foreach($accounts as $a)
                        <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_gl_depreciation_expense_account') }}</label>
                    <select name="depreciation_expense_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_accounting.field_gl_use_default') }}</option>
                        @foreach($accounts as $a)
                        <option value="{{ $a->account_id }}">{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="margin-top:12px;">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:8px 24px; font-size:11px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.add_asset_category_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
