@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.transaction_history_page_title'))

@section('content')

{{-- NEW 2 Sep 2026 (Task #338) — the general audit trail: every posted
     journal entry regardless of where it came from (transactions, bills,
     bill payments, petty cash, disposals, manual JVs, reversals...),
     unlike the Journal Vouchers list which only shows manually-keyed
     entries. Click any row to view its lines and, if it's still posted,
     void it (which posts a reversal — nothing is ever edited/deleted). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.transaction_history_page_title') }}</div>
        <a href="{{ route('cbe.accounting.index') }}" onclick="history.back(); return false;" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <form method="GET" action="{{ route('cbe.accounting.transaction-history') }}" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:8px;">
        <div style="width:140px;">
            <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_from_date') }}</label>
            <input type="date" name="from" value="{{ $from }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
        </div>
        <div style="width:140px;">
            <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_to_date') }}</label>
            <input type="date" name="to" value="{{ $to }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:9.5px; box-sizing:border-box;">
        </div>
        <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:14px; padding:6px 16px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.th_filter_button') }}</button>
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_ref_no') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_description') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_th_source') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_th_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $thStatusColors = ['POSTED' => '#2e7d32', 'VOIDED' => '#9ca3af']; @endphp
                    @forelse($entries as $e)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer; {{ $e->status === 'VOIDED' ? 'opacity:.6;' : '' }}" onclick="window.location='{{ route('cbe.accounting.journal-vouchers.show', $e->journal_id) }}';">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($e->entry_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#263238; font-weight:600; white-space:nowrap;">{{ $e->reference_no ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#263238;">{{ $e->description ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_accounting.th_source_'.strtolower($e->source_type)) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#263238;">RM {{ number_format($e->amount, 2) }}</td>
                        <td style="padding:5px 8px;">
                            <span style="color:{{ $thStatusColors[$e->status] ?? '#546E7A' }}; font-weight:600;">{{ __('cbe_accounting.th_status_'.strtolower($e->status)) }}</span>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_transaction_history_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($entries->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $entries->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $entries->currentPage(), 'last' => $entries->lastPage(), 'total' => $entries->total()]) }}</span>
            @if($entries->hasMorePages())
                <a href="{{ $entries->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
