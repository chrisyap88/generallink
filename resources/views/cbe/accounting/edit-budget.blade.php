@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.edit_button') . ' - ' . __('cbe_accounting.budgets_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.edit_button') }} - {{ __('cbe_accounting.budgets_page_title') }}</div>
        <a href="{{ route('cbe.accounting.budgets') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #d32f2f; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">
        {{ $errors->first() }}
    </div>
    @endif

    <form method="POST" action="{{ route('cbe.accounting.budgets.update', $row->budget_id) }}" style="flex:1; min-height:0; display:flex; flex-direction:column; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px;">
        @csrf
        @method('PUT')

        <div style="flex:1; min-height:0; overflow:hidden; display:grid; grid-template-columns:1fr 1fr; gap:10px 16px;">

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_fiscal_year') }} *</label>
                <input type="number" name="fiscal_year" value="{{ old('fiscal_year', $row->fiscal_year) }}" min="2000" max="2100" required
                    style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
            </div>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_budget_name') }} *</label>
                <input type="text" name="budget_name" value="{{ old('budget_name', $row->budget_name) }}" maxlength="255" required
                    style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
            </div>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_cost_centre') }}</label>
                <select name="cost_centre_id" style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
                    <option value="">{{ __('cbe_records.none_option') }}</option>
                    @foreach($costCentres as $cc)
                    <option value="{{ $cc->centre_id }}" @selected(old('cost_centre_id', $row->cost_centre_id) == $cc->centre_id)>{{ $cc->centre_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_fund') }}</label>
                <select name="fund_id" style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
                    <option value="">{{ __('cbe_records.none_option') }}</option>
                    @foreach($funds as $f)
                    <option value="{{ $f->fund_id }}" @selected(old('fund_id', $row->fund_id) == $f->fund_id)>{{ $f->fund_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_pr_account') }}</label>
                <select name="account_id" style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
                    <option value="">{{ __('cbe_records.none_option') }}</option>
                    @foreach($accounts as $a)
                    <option value="{{ $a->account_id }}" @selected(old('account_id', $row->account_id) == $a->account_id)>{{ $a->account_code }} - {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label style="display:block; font-size:9.5px; font-weight:600; color:#546E7A; margin-bottom:3px;">{{ __('cbe_accounting.field_budget_amount') }} (RM) *</label>
                <input type="number" step="0.01" min="0" name="budget_amount" value="{{ old('budget_amount', $row->budget_amount) }}" required
                    style="width:100%; box-sizing:border-box; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px;">
            </div>
        </div>

        <div style="flex-shrink:0; margin-top:10px; display:flex; justify-content:flex-end; gap:10px;">
            <a href="{{ route('cbe.accounting.budgets') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
        </div>
    </form>
</div>
@endsection
