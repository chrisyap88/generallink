@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('document_credit.detail_title_prefix') . $agent->full_name)

@section('content')

{{-- NEW 21 Jul 2026 — drill-down from the Agent Balances tab: this
     agent's full top-up + usage history, paginated (Prev/Next), per
     Chris's explicit instruction that list screens must never be a
     flat dump-all. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">


    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:8px; flex-shrink:0;">
        <div style="background:#1565C0; border-radius:8px; padding:10px 12px; color:#fff;">
            <div style="font-size:9px; opacity:.85;">{{ $agent->full_name }} ({{ $agent->agent_code }} — {{ $agent->role }})</div>
            <div style="font-size:18px; font-weight:700;">RM {{ number_format($balance, 2) }}</div>
        </div>
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px 12px;">
            <div style="font-size:9px; color:#9ca3af; margin-bottom:4px;">{{ __('document_credit.topup_requests_recent_label', ['count' => $topupRequests->count()]) }}</div>
            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                @php
                    $adcStatusLabels = ['APPROVED' => __('points.status_approved'), 'REJECTED' => __('points.status_rejected'), 'PENDING' => __('points.status_pending')];
                @endphp
                @forelse($topupRequests as $t)
                <span style="font-size:9px; padding:2px 7px; border-radius:20px; font-weight:600;
                    background:{{ $t->status === 'APPROVED' ? '#e8f5e9' : ($t->status === 'REJECTED' ? '#fde8e8' : '#fff8e1') }};
                    color:{{ $t->status === 'APPROVED' ? '#1b5e20' : ($t->status === 'REJECTED' ? '#b71c1c' : '#92400e') }};">
                    RM {{ number_format($t->amount_requested, 2) }} — {{ $adcStatusLabels[$t->status] ?? $t->status }}
                </span>
                @empty
                <span style="font-size:9.5px; color:#9ca3af;">{{ __('document_credit.no_topup_requests_note') }}</span>
                @endforelse
            </div>
        </div>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="font-size:11px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('document_credit.full_ledger_heading') }}</div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_type') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_note') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_amount') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_balance_after') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $adcTypeLabels = ['TOPUP' => __('document_credit.type_topup'), 'DEDUCTION' => __('document_credit.type_deduction'), 'TRANSFER_IN' => __('document_credit.type_transfer_in'), 'TRANSFER_OUT' => __('document_credit.type_transfer_out')];
                    @endphp
                    @forelse($history as $h)
                    @php
                        // FIXED 21 Jul 2026 — per Chris's new Credit
                        // Transfer feature: ledger now has 4 possible
                        // types, not just TOPUP/DEDUCTION. TOPUP and
                        // TRANSFER_IN both add to balance (+green/blue);
                        // DEDUCTION and TRANSFER_OUT both reduce it.
                        $isCredit = in_array($h->type, ['TOPUP', 'TRANSFER_IN']);
                        $badgeBg = match($h->type) { 'TOPUP' => '#e8f5e9', 'TRANSFER_IN' => '#e3f2fd', 'TRANSFER_OUT' => '#fff3e0', default => '#f3f4f6' };
                        $badgeColor = match($h->type) { 'TOPUP' => '#1b5e20', 'TRANSFER_IN' => '#1565C0', 'TRANSFER_OUT' => '#e65100', default => '#374151' };
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px;">{{ \Carbon\Carbon::parse($h->created_at)->format('d M Y, h:i A') }}</td>
                        <td style="padding:5px 8px;">
                            <span style="padding:1px 7px; border-radius:20px; font-size:8.5px; font-weight:600; background:{{ $badgeBg }}; color:{{ $badgeColor }};">{{ $adcTypeLabels[$h->type] ?? $h->type }}</span>
                        </td>
                        <td style="padding:5px 8px; color:#4b5563;">{{ $h->note }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:600; color:{{ $isCredit ? '#1b5e20' : '#374151' }};">{{ $isCredit ? '+' : '-' }}RM {{ number_format($h->amount, 2) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">RM {{ number_format($h->balance_after, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('document_credit.no_ledger_activity_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($history->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $history->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('document_credit.page_x_of_y_entries_paren', ['current' => $history->currentPage(), 'last' => $history->lastPage(), 'total' => $history->total()]) }}</span>
            @if($history->hasMorePages())
                <a href="{{ $history->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
@endsection
