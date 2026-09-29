@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.invoices_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.invoices_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.invoices.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_invoice_button') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_ref_no') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_customer') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_due_date') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_paid') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.pay_button') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ $inv->doc_ref_no ?: '—' }}</td>
                        <td style="padding:5px 6px; font-weight:600; color:#263238;">{{ $inv->customer_name }}</td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') }}</td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d M Y') : '—' }}</td>
                        <td style="padding:5px 6px; text-align:right; font-weight:700; color:#263238;">RM {{ number_format($inv->amount, 2) }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#2e7d32;">RM {{ number_format($inv->paid_amount, 2) }}</td>
                        <td style="padding:5px 6px;">
                            @php $statusColors = ['UNPAID' => '#c62828', 'PARTIALLY_PAID' => '#D97706', 'PAID' => '#2e7d32', 'CANCELLED' => '#9ca3af']; @endphp
                            <span style="color:{{ $statusColors[$inv->status] }}; font-weight:600;">{{ __('cbe_accounting.status_'.strtolower($inv->status)) }}</span>
                        </td>
                        <td style="padding:5px 6px; text-align:right;">
                            @if($inv->status === 'UNPAID' || $inv->status === 'PARTIALLY_PAID')
                            <form method="POST" action="{{ route('cbe.accounting.invoices.pay', $inv->invoice_id) }}" style="display:flex; gap:3px; justify-content:flex-end; align-items:center;">
                                @csrf
                                <input type="hidden" name="payment_date" value="{{ now()->toDateString() }}">
                                <input type="number" step="0.01" min="0.01" name="amount" value="{{ number_format($inv->amount - $inv->paid_amount, 2, '.', '') }}" required style="width:70px; border:1px solid #d1d5db; border-radius:5px; padding:3px 5px; font-size:9px; box-sizing:border-box;">
                                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:4px 8px; font-size:8.5px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.pay_button') }}</button>
                            </form>
                            @else
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_invoices_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($invoices->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $invoices->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $invoices->currentPage(), 'last' => $invoices->lastPage(), 'total' => $invoices->total()]) }}</span>
            @if($invoices->hasMorePages())
                <a href="{{ $invoices->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
