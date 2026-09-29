@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.purchasing_audit_log_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #394 gap-fix) — Purchasing Audit Trail (spec
     section 26). Read-only browse over the append-only
     cbe_purchasing_audit_log table (one row per purchasing action,
     written alongside — not instead of — each document's own status
     columns). Each row links back to its source document's own show
     screen, same $docRoutes mapping used by Supplier Procurement
     History. --}}

@php
    $docTypeLabels = [
        'PR' => __('cbe_accounting.doctype_pr'), 'RFQ' => __('cbe_accounting.doctype_rfq'),
        'PO' => __('cbe_accounting.doctype_po'), 'GRN' => __('cbe_accounting.doctype_grn'),
        'PRN' => __('cbe_accounting.doctype_prn'), 'BILL' => __('cbe_accounting.doctype_bill'),
    ];
    $docRoutes = [
        'PR' => 'cbe.accounting.purchase-requests.show', 'RFQ' => 'cbe.accounting.purchase-quotations.show',
        'PO' => 'cbe.accounting.purchase-orders.show', 'GRN' => 'cbe.accounting.goods-receipts.show',
        'PRN' => 'cbe.accounting.purchase-returns.show', 'BILL' => 'cbe.accounting.bill-enquiry.show',
    ];
    $actionLabels = [
        'CREATED' => __('cbe_accounting.audit_action_created'), 'APPROVED' => __('cbe_accounting.audit_action_approved'),
        'REJECTED' => __('cbe_accounting.audit_action_rejected'), 'CONVERTED' => __('cbe_accounting.audit_action_converted'),
        'CANCELLED' => __('cbe_accounting.audit_action_cancelled'), 'RECEIVED' => __('cbe_accounting.audit_action_received'),
        'MATCHED' => __('cbe_accounting.audit_action_matched'), 'SELECTED_SUPPLIER' => __('cbe_accounting.audit_action_selected_supplier'),
        'QUOTE_SAVED' => __('cbe_accounting.audit_action_quote_saved'),
    ];
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.purchasing_audit_log_page_title') }}</div>
        <a href="{{ route('cbe.accounting.purchasing-reports-hub') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_purchasing_reports') }}</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <form method="GET" action="{{ route('cbe.accounting.purchasing-audit-log') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
            <div style="flex:1; max-width:220px;">
                <label style="display:block; font-size:9px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_accounting.col_doc_type') }}</label>
                <select name="doc_type" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px; box-sizing:border-box;">
                    <option value="">{{ __('cbe_accounting.filter_all_doc_types') }}</option>
                    @foreach($docTypeLabels as $code => $label)
                    <option value="{{ $code }}" {{ $docType === $code ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.go_button') }}</button>
            <a href="{{ route('cbe.accounting.purchasing-audit-log') }}" style="background:#c4c9d0; color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.btn_modify_search') }}</a>
        </form>

        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_datetime') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_action') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_actor') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($l->created_at)->format('d M Y g:ia') }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $docTypeLabels[$l->doc_type] ?? $l->doc_type }}</td>
                        <td style="padding:5px 8px; font-weight:600;">
                            @if(isset($docRoutes[$l->doc_type]))
                            <a href="{{ route($docRoutes[$l->doc_type], $l->doc_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $l->doc_ref_no ?: '—' }}</a>
                            @else
                            {{ $l->doc_ref_no ?: '—' }}
                            @endif
                        </td>
                        <td style="padding:5px 8px; color:#263238;">{{ $actionLabels[$l->action] ?? $l->action }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $l->actor_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $l->notes ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_audit_log_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($logs->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $logs->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $logs->currentPage(), 'last' => $logs->lastPage(), 'total' => $logs->total()]) }}</span>
            @if($logs->hasMorePages())
                <a href="{{ $logs->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
