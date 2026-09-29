@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.bank_transactions_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 2, spec section 2. Raw bank-side entries for ONE bank
     account, decoupled from any reconciliation session — manual entry
     or CSV/Excel import. Matching against the books happens in
     Phase 3 (Reconciliation Entry). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.bank_transactions_page_title') }} — {{ $account->bank_name }}@if($account->account_name)<span style="color:#9ca3af;"> ({{ $account->account_name }})</span>@endif</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.finance.bank-transaction-import.create', $account->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.import_transactions_button') }}</a>
            <a href="{{ route('cbe.finance.bank-transactions.create', $account->bank_account_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.transaction_add_button') }}</a>
            <a href="{{ route('cbe.finance.bank-accounts') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_bank_accounts') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div style="background:#fff8e1; border-left:3px solid #f9a825; color:#8d6e00; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('warning') }}</div>
    @endif

    <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:6px 10px; margin-bottom:8px;">
        <form method="GET" action="{{ route('cbe.finance.bank-transactions', $account->bank_account_id) }}" style="display:flex; gap:8px; align-items:flex-end;">
            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_date_from') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px;">
            </div>
            <div>
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_records.field_date_to') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" style="border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10px;">
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('cbe_records.btn_modify_search') }}</button>
            <span style="font-size:9px; color:#9ca3af;">{{ __('cbe_records.bank_transaction_source_hint') }}</span>
        </form>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_transaction_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_type_name') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_reference') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_source') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->transaction_date)->format('d/m/Y') }}</td>
                        <td style="padding:5px 6px; color:#263238;">{{ \Illuminate\Support\Str::limit($t->description, 40) }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $t->type_name ?: '—' }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $t->reference_no ?: ($t->cheque_no ?: '—') }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:{{ $t->amount >= 0 ? '#2e7d32' : '#b71c1c' }};">{{ $t->amount >= 0 ? '+' : '' }}{{ number_format($t->amount, 2) }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ __('cbe_records.source_'.strtolower($t->source)) }}</td>
                        <td style="padding:5px 6px;"><span style="background:{{ $t->status === 'UNRECONCILED' ? '#fff8e1' : '#e8f5e9' }}; color:{{ $t->status === 'UNRECONCILED' ? '#8d6e00' : '#1b5e20' }}; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('cbe_records.txn_status_'.strtolower($t->status)) }}</span></td>
                        <td style="padding:5px 6px; text-align:right; white-space:nowrap;">
                            @if($t->journal_id)
                            <a href="{{ route('cbe.accounting.journal-vouchers.show', $t->journal_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; margin-right:8px;">{{ __('cbe_accounting.view_journal_link') }}</a>
                            @endif
                            @if($t->status === 'UNRECONCILED')
                            <form method="POST" action="{{ route('cbe.finance.bank-transactions.delete', $t->transaction_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_records.delete_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_records.delete_button') }}</button>
                            </form>
                            @elseif(!$t->journal_id)
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_bank_transactions_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($transactions->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $transactions->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $transactions->currentPage(), 'last' => $transactions->lastPage(), 'total' => $transactions->total()]) }}</span>
            @if($transactions->hasMorePages())
                <a href="{{ $transactions->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
