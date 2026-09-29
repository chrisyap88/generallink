@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.trial_balance_enquiry_page_title'))

@section('content')

{{-- NEW 3 Sep 2026 (Task #384) — Trial Balance Enquiry: an on-screen,
     paginated version of the trialBalance() Excel export — same
     figures, no download needed. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.trial_balance_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.trial-balance-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="width:170px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_as_of') }}</label>
                <input type="date" name="as_of" value="{{ $asOf }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
            <div style="margin-left:auto; text-align:right;">
                <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_debit') }} / {{ __('cbe_accounting.col_credit') }}</div>
                <div style="font-size:12px; font-weight:700; color:{{ abs($totalDebit - $totalCredit) < 0.01 ? '#2e7d32' : '#c62828' }};">{{ number_format($totalDebit, 2) }} / {{ number_format($totalCredit, 2) }}</div>
            </div>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_code') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_debit') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($balancesPage as $b)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $b->account_code }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $b->account_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_accounting.type_'.strtolower($b->account_type)) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ $b->debit > 0 ? number_format($b->debit, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ $b->credit > 0 ? number_format($b->credit, 2) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_accounts_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($balancesPage->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $balancesPage->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $balancesPage->currentPage(), 'last' => $balancesPage->lastPage(), 'total' => $balancesPage->total()]) }}</span>
            @if($balancesPage->hasMorePages())
                <a href="{{ $balancesPage->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
