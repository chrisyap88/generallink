@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.br_detail_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.br_detail_page_title') }} — {{ \Carbon\Carbon::parse($reconciliation->statement_date)->format('d M Y') }}@if($reconciliation->reconciliation_no)<span style="color:#9ca3af; font-weight:600;"> ({{ $reconciliation->reconciliation_no }})</span>@endif</div>
        <div style="display:flex; gap:12px; align-items:center;">
            @if($reconciliation->bank_account_id)
            <a href="{{ route('cbe.accounting.bank-reconciliations.match', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.br_matching_workspace_link') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations.matched', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.br_matched_items_link') }}</a>
            @endif
            <a href="{{ route('cbe.accounting.reports.bank-reconciliation-statement', $reconciliation->reconciliation_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.report_bank_reconciliation_statement') }}</a>
            <a href="{{ route('cbe.accounting.bank-reconciliations') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">

        {{-- Summary strip --}}
        <div style="flex-shrink:0; display:flex; align-items:center; gap:14px; background:{{ $isBalanced ? '#e8f5e9' : '#fff8e1' }}; border-radius:6px; padding:8px 10px; margin-bottom:8px; font-size:10px;">
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.field_bank_account') }}</span><br>{{ $reconciliation->bank_name ? $reconciliation->bank_name.($reconciliation->account_name ? ' — '.$reconciliation->account_name : '') : __('cbe_accounting.field_bank_account_default') }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.col_ending_balance') }}</span><br>RM {{ number_format($reconciliation->ending_balance, 2) }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.br_ledger_balance') }}</span><br>RM {{ number_format($ledgerBalance, 2) }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8px;">{{ __('cbe_accounting.br_outstanding_total') }}</span><br>RM {{ number_format($outstandingTotal, 2) }}</div>
            <div style="flex:1; text-align:right;">
                @if($isBalanced)
                    <span style="background:#38A169; color:#fff; border-radius:12px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('cbe_accounting.br_balanced_label') }}</span>
                @else
                    <span style="background:#D97706; color:#fff; border-radius:12px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('cbe_accounting.br_not_balanced_label', ['count' => $unmatchedCount]) }}</span>
                @endif
                @if($reconciliation->status === 'DRAFT')
                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.complete', $reconciliation->reconciliation_id) }}" style="display:inline-block; margin-left:8px;" onsubmit="return confirm('{{ __('cbe_accounting.br_complete_confirm') }}');">
                    @csrf
                    <button type="submit" {{ $isBalanced ? '' : 'disabled' }} style="background:{{ $isBalanced ? 'var(--gl-blue)' : '#c4c9d0' }}; color:#fff; border:none; border-radius:14px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:{{ $isBalanced ? 'pointer' : 'not-allowed' }};">{{ __('cbe_accounting.br_complete_button') }}</button>
                </form>
                @elseif($reconciliation->status === 'PENDING_APPROVAL')
                {{-- NEW 4 Sep 2026 (Task #396) — Maker-Checker: sign-off now
                     happens on the shared Approvals screen, not here, so a
                     different officer (or an Admin) always makes that
                     decision away from the preparer's own record. --}}
                <span style="margin-left:8px; background:#D97706; color:#fff; border-radius:12px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('cbe_accounting.br_status_pending_approval') }}</span>
                @if($isAdmin)
                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.reopen', $reconciliation->reconciliation_id) }}" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('{{ __('cbe_accounting.br_reopen_confirm') }}');">
                    @csrf
                    <button type="submit" style="background:none; border:1px solid #D97706; color:#D97706; border-radius:14px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.br_reopen_button') }}</button>
                </form>
                @endif
                @else
                {{-- NEW 2 Sep 2026 (Task #349) — Month-End Reconciliation
                     Lock: once COMPLETED, this record is locked (server
                     now rejects any line edit too, not just the UI). Only
                     an Admin can Reopen it. --}}
                <span style="margin-left:8px; background:#e0e0e0; color:#546E7A; border-radius:12px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('cbe_accounting.br_status_completed') }}</span>
                @if($isAdmin)
                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.reopen', $reconciliation->reconciliation_id) }}" style="display:inline-block; margin-left:6px;" onsubmit="return confirm('{{ __('cbe_accounting.br_reopen_confirm') }}');">
                    @csrf
                    <button type="submit" style="background:none; border:1px solid #D97706; color:#D97706; border-radius:14px; padding:5px 12px; font-size:9.5px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.br_reopen_button') }}</button>
                </form>
                @endif
                @endif
            </div>
        </div>

        {{-- Add lines --}}
        @if($reconciliation->status === 'DRAFT')
        <form method="POST" action="{{ route('cbe.accounting.bank-reconciliations.lines.store', $reconciliation->reconciliation_id) }}" style="flex-shrink:0; margin-bottom:8px;">
            @csrf
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.field_statement_date') }}</th>
                        <th style="text-align:left; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:right; padding:2px 4px; font-size:8px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.br_col_amount_signed') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 0; $i < 5; $i++)
                    <tr>
                        <td style="padding:2px 4px;"><input type="date" name="line_date[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box;"></td>
                        <td style="padding:2px 4px;"><input type="text" name="line_description[]" maxlength="255" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box;"></td>
                        <td style="padding:2px 4px;"><input type="number" step="0.01" name="line_amount[]" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:4px 6px; font-size:9px; box-sizing:border-box; text-align:right;"></td>
                    </tr>
                    @endfor
                </tbody>
            </table>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:3px;">
                <div style="font-size:8px; color:#9ca3af;">{{ __('cbe_accounting.br_lines_helper_note') }}</div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:14px; padding:4px 14px; font-size:9px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.br_add_lines_button') }}</button>
            </div>
        </form>
        @endif

        {{-- Lines list --}}
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_statement_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_line_description') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.br_col_amount_signed') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_approval_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $brStatusColors = ['UNMATCHED' => '#D97706', 'MATCHED' => '#2e7d32', 'OUTSTANDING' => '#9ca3af']; @endphp
                    @forelse($lines as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:4px 6px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($l->line_date)->format('d M Y') }}</td>
                        <td style="padding:4px 6px; color:#263238;">{{ $l->description ?: ($l->matched_description ?: '—') }}</td>
                        <td style="padding:4px 6px; text-align:right; color:{{ $l->amount < 0 ? '#c62828' : '#263238' }};">RM {{ number_format($l->amount, 2) }}</td>
                        <td style="padding:4px 6px; text-align:center;">
                            <span style="background:{{ $brStatusColors[$l->status] }}; color:#fff; border-radius:10px; padding:2px 8px; font-size:8px; font-weight:600;">{{ __('cbe_accounting.br_line_status_'.strtolower($l->status)) }}</span>
                        </td>
                        <td style="padding:4px 6px; text-align:right;">
                            @if($reconciliation->status === 'DRAFT')
                            <div style="display:flex; gap:4px; justify-content:flex-end; align-items:center;">
                                @if($l->status === 'UNMATCHED')
                                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliation-lines.match', $l->line_id) }}" style="display:flex; gap:3px;">
                                    @csrf
                                    <select name="transaction_id" required style="border:1px solid #d1d5db; border-radius:5px; padding:3px 4px; font-size:8px; max-width:140px;">
                                        <option value="">{{ __('cbe_records.select_placeholder') }}</option>
                                        @foreach($candidateTxns as $t)
                                        <option value="{{ $t->transaction_id }}">{{ \Carbon\Carbon::parse($t->transaction_date)->format('d/m') }} — RM {{ number_format($t->amount, 2) }} {{ Str::limit($t->description, 20) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" style="background:#38A169; color:#fff; border:none; border-radius:5px; padding:3px 8px; font-size:8px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.br_match_button') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliation-lines.outstanding', $l->line_id) }}">
                                    @csrf
                                    <button type="submit" style="background:none; border:1px solid #d1d5db; color:#546E7A; border-radius:5px; padding:3px 8px; font-size:8px; font-weight:600; cursor:pointer;">{{ __('cbe_accounting.br_mark_outstanding_button') }}</button>
                                </form>
                                @else
                                <form method="POST" action="{{ route('cbe.accounting.bank-reconciliation-lines.unmatch', $l->line_id) }}">
                                    @csrf
                                    <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:8.5px; cursor:pointer;">{{ __('cbe_accounting.br_undo_button') }}</button>
                                </form>
                                @endif
                            </div>
                            @else
                            <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_accounting.no_br_lines_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
