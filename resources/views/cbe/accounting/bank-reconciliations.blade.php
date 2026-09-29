@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.bank_reconciliations_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.bank_reconciliations_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.bank-reconciliation-enquiry') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.bank_reconciliation_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.unmatched-transaction-enquiry') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.unmatched_transaction_enquiry_page_title') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_bank_reconciliation_button') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="flex-shrink:0; background:#eef4fb; border-left:3px solid var(--gl-blue); color:#263238; border-radius:6px; padding:6px 10px; font-size:9.5px; margin-bottom:6px;">{{ __('cbe_accounting.bank_account_note', ['balance' => number_format($ledgerCashBalance, 2)]) }}</div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_statement_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_bank_account') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_opening_balance') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_ending_balance') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reconciliations as $r)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.bank-reconciliations.show', $r->reconciliation_id) }}';">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ \Carbon\Carbon::parse($r->statement_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $r->bank_name ? $r->bank_name.($r->account_name ? ' — '.$r->account_name : '') : __('cbe_accounting.field_bank_account_default') }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">RM {{ number_format($r->opening_balance, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">RM {{ number_format($r->ending_balance, 2) }}</td>
                        <td style="padding:5px 8px;">
                            @php $brListColors = ['COMPLETED' => '#2e7d32', 'PENDING_APPROVAL' => '#D97706', 'DRAFT' => '#9ca3af']; @endphp
                            <span style="color:{{ $brListColors[$r->status] ?? '#D97706' }}; font-weight:600;">{{ __('cbe_accounting.br_status_'.strtolower($r->status)) }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_bank_reconciliations_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($reconciliations->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $reconciliations->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $reconciliations->currentPage(), 'last' => $reconciliations->lastPage(), 'total' => $reconciliations->total()]) }}</span>
            @if($reconciliations->hasMorePages())
                <a href="{{ $reconciliations->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
