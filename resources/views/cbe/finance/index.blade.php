@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.finance_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.finance_page_title') }}</div>
        @if($hasNode)
            @if($tab === 'transactions')
            <a href="{{ route('cbe.finance.transactions.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.transaction_add_button') }}</a>
            @else
            <a href="{{ route('cbe.finance.statements.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.statement_add_button') }}</a>
            @endif
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="flex-shrink:0; margin-bottom:8px; display:flex; justify-content:space-between; align-items:flex-end;">
        <div style="display:flex; gap:3px;">
            <a href="{{ route('cbe.finance.index', ['tab' => 'statements']) }}" style="text-decoration:none; padding:5px 14px; font-size:9.5px; font-weight:700; border-radius:6px 6px 0 0; border:1px solid #E2E8F0; border-bottom:none; {{ $tab === 'statements' ? 'background:#fff; color:var(--gl-blue);' : 'background:#F7FAFC; color:#6b7280;' }}">{{ __('cbe_records.tab_statements') }}</a>
            <a href="{{ route('cbe.finance.index', ['tab' => 'transactions']) }}" style="text-decoration:none; padding:5px 14px; font-size:9.5px; font-weight:700; border-radius:6px 6px 0 0; border:1px solid #E2E8F0; border-bottom:none; {{ $tab === 'transactions' ? 'background:#fff; color:var(--gl-blue);' : 'background:#F7FAFC; color:#6b7280;' }}">{{ __('cbe_records.tab_transactions') }}</a>
            <a href="{{ route('cbe.finance.bank-accounts') }}" style="text-decoration:none; padding:5px 14px; font-size:9.5px; font-weight:700; border-radius:6px 6px 0 0; border:1px solid #E2E8F0; border-bottom:none; background:#F7FAFC; color:#6b7280;">{{ __('cbe_records.bank_accounts_page_title') }}</a>
        </div>
        <a href="{{ route('cbe.finance.categories') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10px; font-weight:600;">{{ __('cbe_records.manage_categories_link') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:0 8px 8px 8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @elseif($tab === 'transactions')
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->transaction_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $t->category_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $t->description ?: '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:{{ $t->category_type === 'INCOME' ? '#2e7d32' : '#c62828' }};">{{ $t->category_type === 'INCOME' ? '+' : '-' }}RM {{ number_format($t->amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_transactions_note') }}</td></tr>
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
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_period') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_attachment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($statements as $s)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ \Carbon\Carbon::createFromDate($s->statement_year, $s->statement_month, 1)->format('M Y') }}</td>
                        <td style="padding:5px 8px;">
                            <a href="{{ route('cbe.finance.statements.download', $s->statement_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('cbe_records.view_attachment') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_statements_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($statements->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $statements->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $statements->currentPage(), 'last' => $statements->lastPage(), 'total' => $statements->total()]) }}</span>
            @if($statements->hasMorePages())
                <a href="{{ $statements->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
