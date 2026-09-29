@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.gl_enquiry_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #384) — General Ledger Enquiry: pick one
     account and a date range, see its transactions with a running
     balance — mirrors supplier-enquiry.blade.php on the AP side. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.gl_enquiry_page_title') }}</div>
        {{-- CHANGED 23 Sep 2026 per Chris: "the prev should be in blue and
             bring back to previous screen" -- restyled as the house blue
             Prev pill (was plain blue text) and now always returns to the
             exact Chart of Accounts search (filters + page preserved via
             ?back=...) when opened from there, instead of relying on
             browser history or falling back to the generic Accounting hub. --}}
        <a href="{{ $back !== '' ? $back : route('cbe.accounting.index') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('cbe_accounting.prev_label') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        {{-- CHANGED 23 Sep 2026 per Chris: "display proper the account
             name, display in one screen reduce the font size, it should
             display on top before select date range" -- the account
             summary band (Code/Type/Opening Balance/Account Name) now
             comes FIRST, above the date-range filter form, in a smaller
             single-line font so the full bilingual name fits without
             wrapping and without pushing the table into a scroll. --}}
        @if($account)
        <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:6px 10px; margin-bottom:6px; display:flex; gap:18px; align-items:center; min-width:0;">
            <div style="flex-shrink:0;">
                <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_code') }}</div>
                <div style="font-size:10px; color:#263238; font-weight:600; white-space:nowrap;">{{ $account->account_code }}</div>
            </div>
            <div style="flex-shrink:0;">
                <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_type') }}</div>
                <div style="font-size:10px; color:#263238; font-weight:600; white-space:nowrap;">{{ __('cbe_accounting.type_'.strtolower($account->account_type)) }}</div>
            </div>
            <div style="flex-shrink:0;">
                <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_opening_balance') }}</div>
                <div style="font-size:10px; color:#263238; font-weight:600; white-space:nowrap;">{{ number_format($openingBalance, 2) }}</div>
            </div>
            <div style="flex:1 1 auto; min-width:0; border-left:1px solid var(--gl-cyan2); padding-left:14px;">
                <div style="font-size:7.5px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.gl_enquiry_account_name_label') }}</div>
                <div style="font-size:10.5px; font-weight:700; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $account->account_name_zh ? $account->account_name.' ('.$account->account_name_zh.')' : $account->account_name }}">{{ $account->account_name_zh ? $account->account_name.' ('.$account->account_name_zh.')' : $account->account_name }}</div>
            </div>
        </div>
        @endif

        <form method="GET" action="{{ route('cbe.accounting.general-ledger-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <input type="hidden" name="back" value="{{ $back }}">
            <div style="flex:1; min-width:0;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_account_code') }}</label>
                <select name="account_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($accountsList as $a)
                    <option value="{{ $a->account_id }}" {{ $accountId == $a->account_id ? 'selected' : '' }}>{{ $a->account_code }} — {{ $a->account_name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="width:130px; flex-shrink:0;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_from_date') }}</label>
                <input type="date" name="from" value="{{ $from }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
            </div>
            <div style="width:130px; flex-shrink:0;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_to_date') }}</label>
                <input type="date" name="to" value="{{ $to }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10px; box-sizing:border-box;">
            </div>
            <button type="submit" style="flex-shrink:0; background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
            @if($account)
            <a href="{{ route('cbe.accounting.general-ledger-enquiry') }}{{ $back !== '' ? '?back='.urlencode($back) : '' }}" style="flex-shrink:0; background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
            @endif
        </form>

        @if(! $account)
        <div style="flex:1; display:flex; align-items:center; justify-content:center; color:#9ca3af; font-size:11px;">{{ __('cbe_accounting.gl_enquiry_pick_account_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                {{-- CHANGED 23 Sep 2026 per Chris: exact column set --
                     Date, Description, Transaction Type, DR, CR, Closing
                     Balance. Journal No and the per-row JV link were
                     dropped to match exactly what was asked for. --}}
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_description') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_transaction_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_debit') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_credit') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_closing_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->txn_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#263238; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:1px;">{{ $t->description ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#546E7A; white-space:nowrap;">{{ $t->transaction_type }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ (float) $t->debit > 0 ? number_format($t->debit, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ (float) $t->credit > 0 ? number_format($t->credit, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">{{ number_format($t->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_gl_enquiry_transactions_note') }}</td></tr>
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
        @endif
    </div>
</div>
@endsection
