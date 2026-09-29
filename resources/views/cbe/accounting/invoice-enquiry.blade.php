@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.invoice_enquiry_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #359) — Invoice Enquiry: on-screen search +
     read-only lookup of a single invoice, separate from the Invoices
     entry/list screen. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.invoice_enquiry_page_title') }}</div>
        <a href="{{ route('cbe.accounting.invoices') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_invoices') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.invoice-enquiry') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:1; max-width:220px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_invoice_no_search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
            </div>
            <div style="flex:1; max-width:220px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.field_customer') }}</label>
                <select name="customer_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                    @foreach($customers as $c)
                    <option value="{{ $c->customer_id }}" {{ request('customer_id') == $c->customer_id ? 'selected' : '' }}>{{ $c->customer_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
            <a href="{{ route('cbe.accounting.invoice-enquiry') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_invoice') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_customer') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_outstanding_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $inv)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.accounting.invoice-enquiry.show', $inv->invoice_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:var(--gl-blue);">{{ $inv->invoice_no }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $inv->customer_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_accounting.status_'.strtolower($inv->status)) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">RM {{ number_format($inv->amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:{{ ($inv->amount - $inv->paid_amount) > 0.004 ? '#c62828' : '#2e7d32' }};">RM {{ number_format($inv->amount - $inv->paid_amount, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_invoices_note') }}</td></tr>
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
