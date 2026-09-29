@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.override_ledger_statement_title'))
@push('styles')
<style>
.ovl-table{border-collapse:collapse; font-size:10px; table-layout:fixed;}
.ovl-table th, .ovl-table td{border:1px solid #CBD5E0; padding:5px 7px;}
.ovl-table th{background:#F7FAFC; color:#2D3748; font-weight:700; text-align:left;}
.ovl-table tfoot td{background:#F7FAFC; font-weight:700;}
</style>
@endpush
@section('content')
@php
    // Column widths defined once and reused by the bottom summary bar
    // so its boxes line up under the matching table columns — same
    // approach as the Earning Income Ledger statement, per Chris.
    $wNo = 28; $wDate = 70; $wDesc = 175; $wVoucher = 80; $wSales = 85; $wDebit = 80; $wCredit = 80; $wBalance = 85;
    $wTotal = $wNo + $wDate + $wDesc + $wVoucher + $wSales + $wDebit + $wCredit + $wBalance;

    // Page-scoped sub totals — same numbers as the Page Sub Total row
    // inside the table, reused here so Opening + Debit − Credit always
    // equals Closing Balance (all must be the SAME scope: this page).
    $pageDebit = $entries->getCollection()->sum(fn($e) => $e->entry_type === 'DEBIT' ? $e->amount : 0);
    $pageCredit = $entries->getCollection()->sum(fn($e) => $e->entry_type === 'CREDIT' ? $e->amount : 0);
@endphp
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:6px; font-size:10px;">

    <div style="display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.affiliate_partner_colon', ['name' => $member->full_name, 'code' => $member->override_member_code]) }}</div>
            <div style="font-size:10.5px; color:#4A5568;">{{ __('masterfile.ledger_as_at_page', ['date' => now()->format('d/m/Y'), 'current' => $entries->currentPage(), 'last' => max(1, $entries->lastPage())]) }}</div>
        </div>
        <a href="{{ route('admin.masterfile.override-ledger') }}" style="background:#f3f4f6; color:#4A5568; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:600;">{{ __('masterfile.back_to_ledger') }}</a>
    </div>

    <div style="flex-shrink:0; background:#EEF4FB; border:1px solid #C9DCF0; border-radius:6px; padding:5px 10px; display:flex; justify-content:space-between; align-items:center;">
        <span style="font-size:10.5px; font-weight:700; color:#1565C0;">{{ __('masterfile.opening_balance_label', ['amount' => number_format($openingBalance, 2)]) }}</span>
        <span style="font-size:9px; color:#718096;">{{ $entries->currentPage() == 1 ? __('masterfile.no_prior_history') : __('masterfile.carried_forward') }}</span>
    </div>

    <div style="background:#fff; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; padding:8px;">
        <div style="flex:1; overflow:hidden; min-height:0;">
            <table class="ovl-table" style="width:{{ $wTotal }}px;">
                <colgroup>
                    <col style="width:{{ $wNo }}px;"><col style="width:{{ $wDate }}px;"><col style="width:{{ $wDesc }}px;"><col style="width:{{ $wVoucher }}px;">
                    <col style="width:{{ $wSales }}px;"><col style="width:{{ $wDebit }}px;"><col style="width:{{ $wCredit }}px;"><col style="width:{{ $wBalance }}px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>{{ __('masterfile.col_no') }}</th>
                        <th>{{ __('masterfile.col_date') }}</th>
                        <th>{{ __('masterfile.col_description') }}</th>
                        <th>{{ __('masterfile.col_voucher_no') }}</th>
                        <th style="text-align:right;">{{ __('masterfile.col_sales_amt_rm') }}</th>
                        <th style="text-align:right;">{{ __('masterfile.col_debit_rm') }}</th>
                        <th style="text-align:right;">{{ __('masterfile.col_credit_rm') }}</th>
                        <th style="text-align:right;">{{ __('masterfile.col_balance_rm') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $e)
                    @php $rowNum = $loop->iteration; @endphp
                    <tr>
                        <td style="text-align:center; color:#718096;">{{ $rowNum }}</td>
                        <td>{{ \Carbon\Carbon::parse($e->entry_date)->format('d/m/Y') }}</td>
                        <td style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $e->description }}</td>
                        <td style="font-family:monospace; font-size:9.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $e->voucher_number }}</td>
                        <td style="text-align:right; color:#718096;">{{ $e->sales_basis_amount !== null ? number_format($e->sales_basis_amount, 2) : '—' }}</td>
                        <td style="text-align:right; color:#B71C1C; font-weight:600;">{{ $e->entry_type === 'DEBIT' ? number_format($e->amount, 2) : '' }}</td>
                        <td style="text-align:right; color:#1B5E20; font-weight:600;">{{ $e->entry_type === 'CREDIT' ? number_format($e->amount, 2) : '' }}</td>
                        <td style="text-align:right; font-weight:700;">{{ number_format($e->running_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center; padding:30px; color:#9ca3af; border:none;">{{ __('masterfile.no_ledger_entries_member') }}</td></tr>
                    @endforelse
                </tbody>
                @if($entries->count() > 0)
                {{-- Page Sub Total — Debit/Credit columns only, same
                     numbers reused in the bottom summary bar below. --}}
                <tfoot>
                    <tr>
                        <td colspan="5" style="text-align:right; color:#4A5568;">{{ __('masterfile.page_sub_total') }}</td>
                        <td style="text-align:right; color:#B71C1C;">{{ number_format($pageDebit, 2) }}</td>
                        <td style="text-align:right; color:#1B5E20;">{{ number_format($pageCredit, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- FIXED 2 Aug 2026 — per Chris: same round of fixes already
         applied to the Earning Income Ledger statement — (1) maths:
         Opening Balance is page-scoped, so Total Debit/Total Credit/
         Closing Balance must be too, or Opening + Debit − Credit never
         equals Closing. (2) Alignment: boxes line up under their
         matching table columns using the same widths as the table
         above. (3) Prev/Next in their own row, pinned to the true far
         left/right corners, per the app's standing pagination rule. --}}
    <div style="flex-shrink:0; background:#1565C0; border-radius:6px; padding:6px 8px; display:flex; flex-direction:column; gap:5px;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            @if($entries->onFirstPage())
            <span style="background:rgba(255,255,255,.25); color:#fff; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</span>
            @else
            <a href="{{ $entries->previousPageUrl() }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.prev') }}</a>
            @endif
            @if($entries->hasMorePages())
            <a href="{{ $entries->nextPageUrl() }}" style="background:#fff; color:#1565C0; text-decoration:none; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</a>
            @else
            <span style="background:rgba(255,255,255,.25); color:#fff; border-radius:5px; padding:3px 12px; font-size:10.5px; font-weight:600;">{{ __('masterfile.next') }}</span>
            @endif
        </div>
        <div style="display:flex; align-items:center;">
            <div style="width:{{ $wNo }}px; flex-shrink:0;"></div>
            <div style="width:{{ $wDate + $wDesc + $wVoucher + $wSales }}px; flex-shrink:0;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('masterfile.opening_balance_short') }}</div>
                <div style="font-size:11px; color:#fff; font-weight:700;">RM {{ number_format($openingBalance, 2) }}</div>
            </div>
            <div style="width:{{ $wDebit }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('masterfile.total_debit_label') }}</div>
                <div style="font-size:11px; color:#FFCDD2; font-weight:700;">RM {{ number_format($pageDebit, 2) }}</div>
            </div>
            <div style="width:{{ $wCredit }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('masterfile.total_credit_label') }}</div>
                <div style="font-size:11px; color:#C8E6C9; font-weight:700;">RM {{ number_format($pageCredit, 2) }}</div>
            </div>
            <div style="width:{{ $wBalance }}px; flex-shrink:0; text-align:right;">
                <div style="font-size:8px; color:#BBDEFB; text-transform:uppercase; letter-spacing:.4px;">{{ __('masterfile.closing_balance_short') }}</div>
                <div style="font-size:11.5px; color:#fff; font-weight:800;">RM {{ number_format($closingBalance, 2) }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
