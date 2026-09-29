@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.bank_account_enquiry_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 4, spec section 4.1. Read-only search across bank accounts,
     with current balance and last reconciliation status — "search then
     list" pattern, same as Asset Enquiry. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.bank_account_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.bank_accounts_page_title') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.bank-account-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:2;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.select_placeholder') }} — {{ __('cbe_records.col_bank') }}</label>
                <input type="text" name="keyword" value="{{ $keyword }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_account_type') }}</label>
                <select name="account_type" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    <option value="BANK_CURRENT" {{ $accountType === 'BANK_CURRENT' ? 'selected' : '' }}>{{ __('cbe_records.acct_type_bank_current') }}</option>
                    <option value="BANK_SAVINGS" {{ $accountType === 'BANK_SAVINGS' ? 'selected' : '' }}>{{ __('cbe_records.acct_type_bank_savings') }}</option>
                    <option value="CASH" {{ $accountType === 'CASH' ? 'selected' : '' }}>{{ __('cbe_records.acct_type_cash') }}</option>
                    <option value="PETTY_CASH" {{ $accountType === 'PETTY_CASH' ? 'selected' : '' }}>{{ __('cbe_records.acct_type_petty_cash') }}</option>
                    <option value="FIXED_DEPOSIT" {{ $accountType === 'FIXED_DEPOSIT' ? 'selected' : '' }}>{{ __('cbe_records.acct_type_fixed_deposit') }}</option>
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.col_status') }}</label>
                <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    <option value="ACTIVE" {{ $status === 'ACTIVE' ? 'selected' : '' }}>{{ __('cbe_accounting.fa_status_active') }}</option>
                    <option value="INACTIVE" {{ $status === 'INACTIVE' ? 'selected' : '' }}>{{ __('cbe_records.inactive_label') }}</option>
                </select>
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" name="search" value="1" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
                <a href="{{ route('cbe.accounting.bank-account-enquiry') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
            </div>
        </form>

        @if(! $searched)
        <div style="flex:1; min-height:0; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:10.5px;">{{ __('cbe_accounting.enquiry_start_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_bank') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_account_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_current_balance') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_last_reconciliation') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $a)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer; {{ !$a->is_active ? 'opacity:.5;' : '' }}" onclick="window.location='{{ route('cbe.finance.bank-transactions', $a->bank_account_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ $a->bank_name }}@if($a->account_name)<span style="color:#9ca3af;"> — {{ $a->account_name }}</span>@endif</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.acct_type_'.strtolower($a->account_type ?: 'bank_current')) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($a->current_balance, 2) }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->last_reconciliation_date ? \Carbon\Carbon::parse($a->last_reconciliation_date)->format('d/m/Y').' ('.__('cbe_accounting.br_status_'.strtolower($a->last_reconciliation_status)).')' : __('cbe_accounting.no_reconciliation_yet_note') }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $a->is_active ? __('cbe_accounting.fa_status_active') : __('cbe_records.inactive_label') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.enquiry_no_results_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($accounts->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $accounts->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $accounts->currentPage(), 'last' => $accounts->lastPage(), 'total' => $accounts->total()]) }}</span>
            @if($accounts->hasMorePages())
                <a href="{{ $accounts->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
