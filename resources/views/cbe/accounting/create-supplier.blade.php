@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.add_supplier_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.add_supplier_button') }}</div>
        <a href="{{ route('cbe.accounting.suppliers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.accounting.suppliers.store') }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            <div style="flex:1; min-height:0; display:grid; grid-template-columns:1fr 1fr; gap:7px 14px; align-content:start;">
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_supplier_name') }}</label>
                    <input type="text" name="supplier_name" value="{{ old('supplier_name') }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_business_reg_no') }}</label>
                    <input type="text" name="business_reg_no" value="{{ old('business_reg_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_supplier_category') }}</label>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->category_id }}" {{ old('category_id') == $c->category_id ? 'selected' : '' }}>{{ $c->category_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_contact_person') }}</label>
                    <input type="text" name="contact_person" value="{{ old('contact_person') }}" maxlength="100" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" maxlength="30" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_email') }}</label>
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_payment_term') }}</label>
                    <select name="payment_term_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($paymentTerms as $t)
                        <option value="{{ $t->term_id }}" {{ old('payment_term_id') == $t->term_id ? 'selected' : '' }}>{{ $t->term_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_payment_method') }}</label>
                    <select name="payment_method_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($paymentMethods as $m)
                        <option value="{{ $m->method_id }}" {{ old('payment_method_id') == $m->method_id ? 'selected' : '' }}>{{ $m->method_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column:1 / -1;">
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_address') }}</label>
                    <input type="text" name="address" value="{{ old('address') }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_bank_name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name') }}" maxlength="100" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_bank_account_no') }}</label>
                    <input type="text" name="bank_account_no" value="{{ old('bank_account_no') }}" maxlength="60" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_bank_account_holder') }}</label>
                    <input type="text" name="bank_account_holder" value="{{ old('bank_account_holder') }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_notes') }}</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_default_ap_account') }}</label>
                    <select name="default_ap_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($glAccounts as $a)
                        <option value="{{ $a->account_id }}" {{ old('default_ap_account_id') == $a->account_id ? 'selected' : '' }}>{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_default_expense_account') }}</label>
                    <select name="default_expense_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($glAccounts as $a)
                        <option value="{{ $a->account_id }}" {{ old('default_expense_account_id') == $a->account_id ? 'selected' : '' }}>{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_default_tax_account') }}</label>
                    <select name="default_tax_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                        @foreach($glAccounts as $a)
                        <option value="{{ $a->account_id }}" {{ old('default_tax_account_id') == $a->account_id ? 'selected' : '' }}>{{ $a->account_code }} — {{ $a->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.accounting.suppliers') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
