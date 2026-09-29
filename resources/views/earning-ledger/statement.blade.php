@extends('layouts.dashboard')
@section('page-title', __('earning_ledger.statement_title'))
@push('styles')
<style>
.el-table{border-collapse:collapse; font-size:10px; table-layout:fixed;}
.el-table th, .el-table td{border:1px solid #CBD5E0; padding:5px 7px;}
.el-table th{background:#F7FAFC; color:#2D3748; font-weight:700; text-align:left;}
</style>
@endpush
@section('content')
@php
    // Column widths defined once here and reused below so the bottom
    // summary bar's boxes line up exactly under the same table columns
    // (No / Date / Description / Debit / Credit / Balance) — per Chris.
    $wNo = 28; $wDate = 70; $wDesc = 420; $wDebit = 105; $wCredit = 105; $wBalance = 110;
    $wTotal = $wNo + $wDate + $wDesc + $wDebit + $wCredit + $wBalance;

    // Page-scoped sub totals — same figures as the "Page Sub Total" row
    // inside the table, reused here so Opening + Debit − Credit always
    // equals Closing Balance shown together on this bar (they must be
    // the SAME scope — both "this page" — or the arithmetic won't add
    // up when shown side by side).
    $pageDebit = $entries->getCollection()->sum(fn($e) => $e->entry_type === 'DEBIT' ? $e->amount : 0);
    $pageCredit = $entries->getCollection()->sum(fn($e) => $e->entry_type === 'CREDIT' ? $e->amount : 0);
    $pageClosing = $openingBalance + $pageDebit - $pageCredit;
@endphp
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:6px; font-size:10px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ $agent->full_name }} ({{ $agent->agent_code }}) — {{ ['ADMIN'=>__('gl.role_admin'),'GROUP_LEADER'=>__('gl.role_group_leader'),'TEAM_LEADER'=>__('gl.role_team_leader'),'INTRODUCER'=>__('gl.role_introducer')][$agent->role] ?? $agent->role }}</div>
            <div style="font-size:10.5px; color:#4A5568;">{{ __('earning_ledger.ledger_as_at_page_x_of_y', ['date' => \Carbon\Carbon::parse($asAtDate)->format('d/m/Y'), 'current' => $entries->currentPage(), 'last' => max(1, $entries->lastPage())]) }}</div>
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <form method="GET" action="{{ route($routePrefix . '.earning-ledger.statement') }}" style="display:flex; gap:4px; align-items:center;">
                <input type="hidden" name="agent_id" value="{{ $agent->agent_id }}">
                <input type="date" name="as_at_date" value="{{ $asAtDate }}" style="border:1px solid #d1d5db; border-radius:5px; padding:3px 6px; font-size:10px; height:24px; box-sizing:border-box;">
                <button type="submit" style="background:#1B9AE4; color:#fff; border:none; border-radius:5px; padding:4px 12px; font-size:10px; font-weight:600; cursor:pointer; height:24px;">{{ __('network.go_button') }}</button>
            </form>
            <a href="{{ route($routePrefix . '.earning-ledger') }}" style="background:#f3f4f6; color:#4A5568; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('earning_ledger.back_to_ledger_link') }}</a>
        </div>
    </div>

    <div style="flex-shrink:0; background:#EEF4FB; border:1px solid #C9DCF0; border-radius:6px; padding:5px 10px; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:10.5px; font-weight:700; color:#1565C0;">{{ __('earning_ledger.opening_balance_label', ['amount' => number_format($openingBalance, 2)]) }}</span>
        <span style="font-size:9px; color:#718096;">{{ $entries->currentPage() == 1 ? __('earning_ledger.no_prior_history_note') : __('earning_ledger.carried_forward_note') }}</span>
    </div>

    <div style="background:#fff; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; padding:8px;">
        <div style="flex:1; overflow:hidden; min-height:0;">
            <table class="el-table" style="width:{{ $wTotal }}px;">
                <colgroup>
                    <col style="width:{{ $wNo }}px;"><col style="width:{{ $wDate }}px;"><col style="width:{{ $wDesc }}px;">
                    <col style="width:{{ $wDebit }}px;"><col style="width:{{ $wCredit }}px;"><col style="width:{{ $wBalance }}px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('earning_ledger.col_no') }}</th>
                        <th>{{ __('gl.col_date') }}</th>
                        <th>{{ __('earning_ledger.col_description') }}</th>
                        <th style="text-align:right;">{{ __('earning_ledger.col_debit_rm') }}</th>
                        <th style="text-align:right;">{{ __('earning_ledger.col_credit_rm') }}</th>
                        <th style="text-align:right;">{{ __('earning_ledger.col_balance_rm') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                    {{-- CHANGED 2 Aug 2026 — per Chris: row numbers restart
                         at 1 on every page (this differs from the
                         "running sequence number across the whole list"
                         rule used on other list screens — flagging that
                         in my reply). --}}
                    @php $rowNum = $loop->iteration; @endphp
                    <tr>
                        <td style="text-align:center; color:#718096;">{{ $rowNum }}</td>
                        <td>{{ \Carbon\Carbon::parse($e->entry_date)->format('d/m/Y') }}</td>
                        <td style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $e->description }}</td>
                        <td style="text-align:right; color:#B71C1C; font-weight:600;">{{ $e->entry_type === 'DEBIT' ? number_format($e->amount, 2) : '' }}</td>
                        <td style="text-align:right; color:#1B5E20; font-weight:600;">{{ $e->entry_type === 'CREDIT' ? number_format($e->amount, 2) : '' }}</td>
                        <td style="text-align:right; font-weight:700;">{{ number_format($e->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#9ca3af; border:none;">{{ __('earning_ledger.no_transactions_yet_note') }}</td></tr>
                    @endforelse
                </tbody>
                @if($entries->count() > 0)
                {{-- Page Sub Total — Debit/Credit columns only, same
                     numbers reused in the bottom summary bar below so
                     both stay in agreement. --}}
                <tfoot>
                    <tr style="background:#F7FAFC; font-weight:700;">
                        <td colspan="3" style="text-align:right; color:#4A5568;">{{ __('earning_ledger.page_sub_total_label') }}</td>
                        <td style="text-align:right; color:#B71C1C;">{{ number_format($pageDebit, 2) }}</td>
                        <td style="text-align:right; color:#1B5E20;">{{ number_format($pageCredit, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- FIXED 2 Aug 2026 (round 2) — per Chris: the stat boxes still
         didn't line up with the table columns because Prev shared the
         same row and forced an offset. Split into two rows instead:
         Prev/Next get their OWN row (far left / far right, per the
         app's standing pagination rule), and the stat row below sits
         completely undisturbed, starting at the exact same x-offset as
         the table above using the identical $wNo/$wDate/... widths —
         so it lines up exactly, no compromise needed. --}}
    <div style="flex-shrink:0; background:#1565C0; border-radius:6px; padding:6px 8px; display:flex; flex-direction:column; gap:5px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            @if($entries->onFirstPage())
            <span style="background:rgba(255,255,255,.25); color:#fff; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('network.prev') }}</span>
            @else
            <a href="{{ $entries->previousPageUrl() }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            @if($entries->hasMorePages())
            <a href="{{ $entries->nextPageUrl() }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('network.next') }}</a>
            @else
            <span style="background:rgba(255,255,255,.25); color:#fff; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('network.next') }}</span>
            @endif
        </div>
        <div style="display:flex; align-items:center;">
            <div style="width:{{ $wNo }}px; flex-shrink:0;"></div>
            <div style="width:{{ $wDate + $wDesc }}px; flex-shrink:0;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('earning_ledger.stat_opening_balance') }}</div>
                <div style="font-size:11px; color:#fff; font-weight:700;">RM {{ number_format($openingBalance, 2) }}</div>
            </div>
            <div style="width:{{ $wDebit }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('earning_ledger.stat_total_debit') }}</div>
                <div style="font-size:11px; color:#FFCDD2; font-weight:700;">RM {{ number_format($pageDebit, 2) }}</div>
            </div>
            <div style="width:{{ $wCredit }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('earning_ledger.stat_total_credit') }}</div>
                <div style="font-size:11px; color:#C8E6C9; font-weight:700;">RM {{ number_format($pageCredit, 2) }}</div>
            </div>
            <div style="width:{{ $wBalance }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('earning_ledger.stat_closing_balance') }}</div>
                <div style="font-size:11.5px; color:#fff; font-weight:800;">RM {{ number_format($pageClosing, 2) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
