@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.unmatched_transaction_enquiry_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 4, spec section 4.4. Every UNRECONCILED bank transaction
     across every account for this node, in one place — always shows
     results (no search gate), since "what needs attention right now"
     is the whole point of this screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.unmatched_transaction_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.bank-reconciliations') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.bank_reconciliations_page_title') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.unmatched-transaction-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_bank_account') }}</label>
                <select name="bank_account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($bankAccounts as $b)
                    <option value="{{ $b->bank_account_id }}" {{ $bankAccountId == $b->bank_account_id ? 'selected' : '' }}>{{ $b->bank_name }}@if($b->account_name) — {{ $b->account_name }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_date_from') }}</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:8.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_date_to') }}</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="display:flex; gap:8px;">
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
                <a href="{{ route('cbe.accounting.unmatched-transaction-enquiry') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
            </div>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_transaction_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_bank') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->transaction_date)->format('d/m/Y') }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $t->bank_name }}@if($t->account_name)<span style="color:#9ca3af;"> — {{ $t->account_name }}</span>@endif</td>
                        <td style="padding:5px 8px; color:#263238;">{{ \Illuminate\Support\Str::limit($t->description, 32) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:{{ $t->amount >= 0 ? '#2e7d32' : '#b71c1c' }};">{{ $t->amount >= 0 ? '+' : '' }}{{ number_format($t->amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right;"><a href="{{ route('cbe.finance.bank-transactions', $t->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_records.transactions_link_short') }}</a></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.unmatched_enquiry_all_clear_note') }}</td></tr>
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
