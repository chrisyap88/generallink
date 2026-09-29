@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.matching_workspace_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — Bank Reconciliation Module upgrade,
     Phase 3, spec section 3.4. Left = unmatched Bank Transactions
     (Phase 2's raw statement lines). Right = unmatched System Entries
     (GL journal lines actually posted to this bank account's own
     chart-of-accounts entry — covers transfers/AP/AR/bills/adjustments,
     not just simple treasurer entries). Tick one or more on each side
     and Match Selected — the two sides' totals must agree (within the
     node's configured tolerance) to save, supporting 1:1, 1:many and
     many:1. Auto-Match runs the same Reconciliation Rules first and
     handles the straightforward 1:1 pairs automatically. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.matching_workspace_page_title') }}@if($reconciliation->reconciliation_no)<span style="color:#9ca3af; font-weight:600;"> — {{ $reconciliation->reconciliation_no }}</span>@endif</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.accounting.bank-reconciliations.matched', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.br_matched_items_link') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations.adjustment.create', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.br_add_adjustment_link') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations.show', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="flex-shrink:0; margin-bottom:8px;">
        <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.auto-match', $reconciliation->reconciliation_id) }}">
            @csrf
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.auto_match_button') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.manual-match', $reconciliation->reconciliation_id) }}" id="matchForm" style="flex:1; min-height:0; display:flex; flex-direction:column;">
        @csrf
        <div style="flex:1; min-height:0; display:flex; gap:10px;">

            {{-- Bank side --}}
            <div style="flex:1; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px; display:flex; flex-direction:column;">
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-size:9.5px; font-weight:700; color:#263238; text-transform:uppercase;">{{ __('cbe_accounting.col_bank_side') }}</span>
                    <span id="bankTotal" style="font-size:9.5px; font-weight:700; color:var(--gl-blue);">RM 0.00</span>
                </div>
                <div style="flex:1; min-height:0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:9px;">
                        <thead>
                            <tr style="background:var(--gl-light);">
                                <th style="width:20px;"></th>
                                <th style="text-align:left; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_transaction_date') }}</th>
                                <th style="text-align:left; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                                <th style="text-align:right; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bankTxns as $t)
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:3px 4px;"><input type="checkbox" name="bank_transaction_ids[]" value="{{ $t->transaction_id }}" class="bankCk" data-amount="{{ $t->amount }}"></td>
                                <td style="padding:3px 4px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($t->transaction_date)->format('d/m/Y') }}</td>
                                <td style="padding:3px 4px; color:#263238;">{{ \Illuminate\Support\Str::limit($t->description, 22) }}</td>
                                <td style="padding:3px 4px; text-align:right; color:{{ $t->amount >= 0 ? '#2e7d32' : '#b71c1c' }};">{{ number_format($t->amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="padding:14px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_unmatched_bank_txns_note') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                    @if($bankTxns->onFirstPage())
                        <span style="background:#1565C0; color:#fff; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:700;">{{ __('network.prev') }}</span>
                    @else
                        <a href="{{ $bankTxns->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:600;">{{ __('network.prev') }}</a>
                    @endif
                    <span style="font-size:8.5px; color:#6b7280;">{{ $bankTxns->currentPage() }}/{{ $bankTxns->lastPage() }}</span>
                    @if($bankTxns->hasMorePages())
                        <a href="{{ $bankTxns->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:600;">{{ __('network.next') }}</a>
                    @else
                        <span style="background:#1565C0; color:#fff; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:700;">{{ __('network.next') }}</span>
                    @endif
                </div>
            </div>

            {{-- System side --}}
            <div style="flex:1; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:8px; display:flex; flex-direction:column;">
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                    <span style="font-size:9.5px; font-weight:700; color:#263238; text-transform:uppercase;">{{ __('cbe_accounting.col_system_side') }}</span>
                    <span id="systemTotal" style="font-size:9.5px; font-weight:700; color:var(--gl-blue);">RM 0.00</span>
                </div>
                <div style="flex:1; min-height:0; overflow:hidden;">
                    <table style="width:100%; border-collapse:collapse; font-size:9px;">
                        <thead>
                            <tr style="background:var(--gl-light);">
                                <th style="width:20px;"></th>
                                <th style="text-align:left; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_transaction_date') }}</th>
                                <th style="text-align:left; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_source_type') }}</th>
                                <th style="text-align:right; padding:3px 4px; font-size:7.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($systemEntries as $e)
                            @php $signed = round((float) $e->debit - (float) $e->credit, 2); @endphp
                            <tr style="border-bottom:1px solid #f3f4f6;">
                                <td style="padding:3px 4px;"><input type="checkbox" name="journal_line_ids[]" value="{{ $e->line_id }}" class="sysCk" data-amount="{{ $signed }}"></td>
                                <td style="padding:3px 4px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($e->entry_date)->format('d/m/Y') }}</td>
                                <td style="padding:3px 4px; color:#263238;">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', strtolower($e->source_type))) }}</td>
                                <td style="padding:3px 4px; text-align:right; color:{{ $signed >= 0 ? '#2e7d32' : '#b71c1c' }};">{{ number_format($signed, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="4" style="padding:14px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_unmatched_system_entries_note') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding-top:6px;">
                    @if($systemEntries->onFirstPage())
                        <span style="background:#1565C0; color:#fff; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:700;">{{ __('network.prev') }}</span>
                    @else
                        <a href="{{ $systemEntries->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:600;">{{ __('network.prev') }}</a>
                    @endif
                    <span style="font-size:8.5px; color:#6b7280;">{{ $systemEntries->currentPage() }}/{{ $systemEntries->lastPage() }}</span>
                    @if($systemEntries->hasMorePages())
                        <a href="{{ $systemEntries->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:600;">{{ __('network.next') }}</a>
                    @else
                        <span style="background:#1565C0; color:#fff; border-radius:16px; padding:3px 10px; font-size:9px; font-weight:700;">{{ __('network.next') }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:flex-end;">
            <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:7px 20px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.match_selected_button') }}</button>
        </div>
    </form>
</div>

<script>
(function () {
    function sumChecked(cls, target) {
        var total = 0;
        document.querySelectorAll('.' + cls + ':checked').forEach(function (cb) { total += parseFloat(cb.dataset.amount) || 0; });
        document.getElementById(target).textContent = 'RM ' + total.toFixed(2);
    }
    document.querySelectorAll('.bankCk').forEach(function (cb) { cb.addEventListener('change', function () { sumChecked('bankCk', 'bankTotal'); }); });
    document.querySelectorAll('.sysCk').forEach(function (cb) { cb.addEventListener('change', function () { sumChecked('sysCk', 'systemTotal'); }); });
})();
</script>
@endsection
