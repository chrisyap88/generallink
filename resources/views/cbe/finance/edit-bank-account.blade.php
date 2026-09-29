@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.edit_bank_account_button'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.edit_bank_account_button') }}</div>
        <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:16px; flex:1; min-height:0; overflow:hidden;">
        <form method="POST" action="{{ route('cbe.finance.bank-accounts.update', $account->bank_account_id) }}" style="height:100%; display:flex; flex-direction:column;">
            @csrf
            @method('PUT')
            <div style="flex:1; min-height:0; display:flex; flex-direction:column; gap:8px; max-width:520px;">
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_bank_name') }}</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $account->bank_name) }}" maxlength="150" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_account_type') }}</label>
                        <select name="account_type" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                            @foreach(['BANK_CURRENT','BANK_SAVINGS','CASH','PETTY_CASH','FIXED_DEPOSIT'] as $t)
                            <option value="{{ $t }}" {{ old('account_type', $account->account_type) === $t ? 'selected' : '' }}>{{ __('cbe_records.acct_type_'.strtolower($t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_account_name') }}</label>
                        <input type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_account_number') }}</label>
                        <input type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" maxlength="60" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_branch') }}</label>
                        <input type="text" name="branch" value="{{ old('branch', $account->branch) }}" maxlength="150" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_signatories') }}</label>
                        <input type="text" name="signatories" value="{{ old('signatories', $account->signatories) }}" maxlength="255" placeholder="{{ __('cbe_records.field_signatories_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_description') }}</label>
                        <input type="text" name="description" value="{{ old('description', $account->description) }}" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_statement_password') }}</label>
                        <input type="password" name="statement_password" maxlength="100" autocomplete="off" placeholder="{{ $hasStatementPassword ? __('cbe_records.field_statement_password_placeholder_saved') : __('cbe_records.field_statement_password_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box;">
                    </div>
                </div>
                @if($hasStatementPassword)
                <label style="display:flex; align-items:center; gap:5px; font-size:9px; color:#546E7A;">
                    <input type="checkbox" name="clear_statement_password" value="1" style="margin:0;">
                    {{ __('cbe_records.field_statement_password_clear') }}
                </label>
                @else
                <div style="font-size:9px; color:#9ca3af; line-height:1.5;">{{ __('cbe_records.field_statement_password_helper') }}</div>
                @endif

                <div style="margin-top:4px; padding-top:8px; border-top:1px solid #f3f4f6; display:grid; grid-template-columns:repeat(2, 1fr); gap:8px; font-size:10px;">
                    <div><span style="color:#9ca3af; font-weight:700; text-transform:uppercase; font-size:8px;" title="{{ __('cbe_records.col_account_code_helper') }}">{{ __('cbe_records.col_account_code') }}</span><br>{{ $account->account_code ?: __('cbe_records.account_code_pending_note') }}</div>
                    <div><span style="color:#9ca3af; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_records.field_opening_balance') }}</span><br>RM {{ number_format($account->opening_balance, 2) }}</div>
                </div>
                <div style="font-size:9px; color:#9ca3af; line-height:1.5;">{{ __('cbe_records.edit_bank_account_locked_note') }}</div>
            </div>
            <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end; gap:8px;">
                <a href="{{ route('cbe.finance.bank-accounts') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:700;">{{ __('network.prev') }}</a>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
