@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.ap_debit_notes_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.ap_debit_notes_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.ap-debit-notes.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.add_ap_debit_note_button') }}</a>
            <a href="{{ route('cbe.accounting.suppliers') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_suppliers') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_doc_ref_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_supplier') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_dn_reason') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_bill_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_gl_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notes as $n)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $n->doc_ref_no ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($n->note_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $n->supplier_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $n->bill_doc_ref_no ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $n->reason ?: '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#c62828;">+ RM {{ number_format($n->amount, 2) }}</td>
                        <td style="padding:5px 8px;">
                            @if($n->journal_id)
                            <a href="{{ route('cbe.accounting.journal-vouchers.show', $n->journal_id) }}" style="font-weight:700; text-decoration:none; color:{{ $n->gl_posting_status === 'POSTED' ? '#2e7d32' : ($n->gl_posting_status === 'REVERSED' ? '#c62828' : '#9e9e9e') }};">{{ __('cbe_accounting.gl_status_'.strtolower($n->gl_posting_status ?: 'not_posted')) }}</a>
                            @else
                            <form method="POST" action="{{ route('cbe.accounting.ap-debit-notes.post', $n->debit_note_id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none; border:1px solid #D97706; color:#D97706; border-radius:10px; padding:2px 9px; font-size:8.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.post_to_gl_button') }}</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_ap_debit_notes_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($notes->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $notes->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $notes->currentPage(), 'last' => $notes->lastPage(), 'total' => $notes->total()]) }}</span>
            @if($notes->hasMorePages())
                <a href="{{ $notes->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
