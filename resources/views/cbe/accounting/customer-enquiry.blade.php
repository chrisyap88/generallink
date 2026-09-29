@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.customer_enquiry_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #359) — Debtor Account Enquiry: on-screen
     lookup (not a download) of one customer's category/terms,
     outstanding balance, and full transaction history with a running
     balance — per Chris's Temple/NGO AR spec. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ $customer->customer_name }}</div>
        <a href="{{ route('cbe.accounting.customers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="flex-shrink:0; background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:8px; padding:8px 10px; margin-bottom:8px; display:flex; gap:24px; align-items:center;">
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_customer_category') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ $customer->category_name ?: '—' }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.field_payment_terms') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ $customer->term_name ? $customer->term_name.' ('.$customer->net_days.' '.__('cbe_accounting.days_suffix').')' : '—' }}</div>
        </div>
        <div>
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.col_phone') }}</div>
            <div style="font-size:10.5px; color:#263238; font-weight:600;">{{ $customer->phone ?: '—' }}</div>
        </div>
        <div style="margin-left:auto; text-align:right;">
            <div style="font-size:8px; color:#546E7A; text-transform:uppercase; font-weight:700;">{{ __('cbe_accounting.outstanding_balance_label') }}</div>
            <div style="font-size:15px; font-weight:700; color:{{ $outstanding > 0 ? '#c62828' : '#2e7d32' }};">RM {{ number_format($outstanding, 2) }}</div>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_txn_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_invoice') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_debit') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_credit') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_balance') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_gl_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->txn_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $t->txn_type }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $t->invoice_no ?: '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ (float) $t->debit > 0 ? number_format($t->debit, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ (float) $t->credit > 0 ? number_format($t->credit, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#263238;">{{ number_format($t->running_balance, 2) }}</td>
                        <td style="padding:5px 8px; text-align:center;">
                            @php
                                $glStatus = $t->gl_posting_status ?? null;
                                $glColor = $glStatus === 'POSTED' ? '#2e7d32' : ($glStatus === 'REVERSED' ? '#c62828' : '#9e9e9e');
                            @endphp
                            @if($t->journal_id)
                                <a href="{{ route('cbe.accounting.journal-vouchers.show', $t->journal_id) }}" style="color:{{ $glColor }}; text-decoration:none; font-weight:700; font-size:8.5px; white-space:nowrap;">{{ __('cbe_accounting.gl_status_'.strtolower($glStatus ?: 'not_posted')) }}</a>
                            @else
                                <span style="color:{{ $glColor }}; font-weight:600; font-size:8.5px; white-space:nowrap;">{{ __('cbe_accounting.gl_status_'.strtolower($glStatus ?: 'not_posted')) }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_customer_transactions_note') }}</td></tr>
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
