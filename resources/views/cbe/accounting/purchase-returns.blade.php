@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.purchase_returns_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.purchase_returns_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_return_ref') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_po_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_supplier') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_dn_reason') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_po_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $returnStatusColors = ['DRAFT' => '#9ca3af', 'CONFIRMED' => '#D97706', 'CREDIT_RAISED' => '#2e7d32', 'CANCELLED' => '#c62828']; @endphp
                    @forelse($returns as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; font-weight:600; color:#263238; white-space:nowrap;">{{ $r->doc_ref_no }}</td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($r->return_date)->format('d M Y') }}</td>
                        <td style="padding:5px 6px; color:#263238;">{{ $r->supplier_name }}</td>
                        <td style="padding:5px 6px; color:#6b7280;">{{ $r->reason ?: '—' }}</td>
                        <td style="padding:5px 6px; text-align:center;">
                            <span style="background:{{ $returnStatusColors[$r->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8px; font-weight:600;">{{ __('cbe_accounting.return_status_'.strtolower($r->status)) }}</span>
                        </td>
                        <td style="padding:5px 6px; text-align:right;">
                            <a href="{{ route('cbe.accounting.purchase-returns.show', $r->return_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600; font-size:9px;">{{ __('cbe_accounting.jv_view_button') }}</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_purchase_returns_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($returns->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $returns->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $returns->currentPage(), 'last' => $returns->lastPage(), 'total' => $returns->total()]) }}</span>
            @if($returns->hasMorePages())
                <a href="{{ $returns->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
