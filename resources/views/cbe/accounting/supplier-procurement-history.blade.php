@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.supplier_procurement_history_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #394 gap-fix) — combines this supplier's
     Requisitions, Quotations, Purchase Orders, Goods Receipts and Returns
     into one activity list, closing the drill-down gap where Supplier
     Enquiry only ever showed the AP side (bills/payments/notes). --}}

@php
    $docTypeLabels = ['PR' => __('cbe_accounting.doctype_pr'), 'RFQ' => __('cbe_accounting.doctype_rfq'), 'PO' => __('cbe_accounting.doctype_po'), 'GRN' => __('cbe_accounting.doctype_grn'), 'PRN' => __('cbe_accounting.doctype_prn')];
    $docRoutes = ['PR' => 'cbe.accounting.purchase-requests.show', 'RFQ' => 'cbe.accounting.purchase-quotations.show', 'PO' => 'cbe.accounting.purchase-orders.show', 'GRN' => 'cbe.accounting.goods-receipts.show', 'PRN' => 'cbe.accounting.purchase-returns.show'];
    $statusLabel = function ($docType, $status) {
        return match ($docType) {
            'PR' => ['PENDING' => __('cbe_records.status_pending'), 'APPROVED' => __('cbe_records.status_approved'), 'REJECTED' => __('cbe_records.status_rejected'), 'CONVERTED' => __('cbe_accounting.status_converted'), 'CONVERTED_TO_PO' => __('cbe_accounting.status_converted_to_po')][$status] ?? $status,
            'RFQ' => __('cbe_accounting.rfq_status_'.strtolower($status)),
            'PO' => __('cbe_accounting.po_status_'.strtolower($status)),
            'GRN' => __('cbe_accounting.grn_status_'.strtolower($status)),
            'PRN' => __('cbe_accounting.return_status_'.strtolower($status)),
            default => $status,
        };
    };
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.supplier_procurement_history_page_title') }} — {{ $supplier->supplier_name }}</div>
        <a href="{{ route('cbe.accounting.supplier-enquiry', $supplier->supplier_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_supplier_enquiry') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                        <th style="text-align:center; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($documents as $d)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280;">{{ $docTypeLabels[$d->doc_type] }}</td>
                        <td style="padding:5px 8px; font-weight:600;"><a href="{{ route($docRoutes[$d->doc_type], $d->doc_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $d->doc_ref_no ?: '—' }}</a></td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($d->doc_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; text-align:center; color:#263238;">{{ $statusLabel($d->doc_type, $d->status) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">{{ (float) $d->amount > 0 ? 'RM '.number_format($d->amount, 2) : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_supplier_procurement_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($documents->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $documents->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $documents->currentPage(), 'last' => $documents->lastPage(), 'total' => $documents->total()]) }}</span>
            @if($documents->hasMorePages())
                <a href="{{ $documents->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
