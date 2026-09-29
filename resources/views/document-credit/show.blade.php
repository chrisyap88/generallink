@extends('layouts.dashboard')

@section('page-title', __('document_credit.title'))

@section('content')
<div style="height:calc(100vh - 46px); padding:6px 16px; box-sizing:border-box; overflow:hidden; display:flex; flex-direction:column;">

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fde8e8; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:6px 12px; font-size:11px; margin-bottom:6px;">
        @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
    </div>
    @endif

    {{-- Balance card --}}
    <div style="display:grid; grid-template-columns:1fr 2fr; gap:8px; margin-bottom:8px;">
        <div style="background:#1565C0; border-radius:8px; padding:10px 12px; color:#fff;">
            <div style="font-size:9px; opacity:.85;">{{ __('document_credit.balance_label') }}</div>
            <div style="font-size:18px; font-weight:700;">RM {{ number_format($balance, 2) }}</div>
        </div>
        <div style="background:#fff8e1; border:1px solid #ffe082; border-radius:8px; padding:10px 12px; display:flex; align-items:center;">
            <div style="font-size:9.5px; color:#92400e; line-height:1.4;">
                {!! __('document_credit.deduction_warning_note', ['read_document' => '<b>' . __('document_credit.read_document_bold') . '</b>', 'amount' => number_format($deductionAmount, 2)]) !!}
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1.4fr; gap:10px; flex:1; min-height:0;">

        {{-- Top-up request form --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; overflow:hidden; display:flex; flex-direction:column;">
            {{-- NEW 21 Jul 2026 — per Chris: agent sets their own
                 low-balance reminder threshold, whole RM number,
                 minimum RM1. --}}
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:6px;">{{ __('document_credit.low_balance_reminder_heading') }}</div>
            <form method="POST" action="{{ route('document-credit.reminder-threshold') }}" style="display:flex; align-items:center; gap:6px; margin-bottom:12px; padding-bottom:12px; border-bottom:1px solid #f3f4f6;">
                @csrf
                <span style="font-size:10px; color:#4b5563;">{{ __('document_credit.remind_if_below_label') }}</span>
                <span style="font-size:10px; font-weight:600;">RM</span>
                <input type="number" step="1" min="1" name="reminder_threshold" value="{{ $reminderThreshold }}" placeholder="e.g. 5" required style="width:70px; border:1px solid #d1d5db; border-radius:6px; padding:5px 6px; font-size:11px; box-sizing:border-box;">
                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('integrations.save_button') }}</button>
            </form>
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('document_credit.request_topup_heading') }}</div>
            <form method="POST" action="{{ route('document-credit.topup') }}" enctype="multipart/form-data">
                @csrf
                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('document_credit.field_amount_paid_label') }} <span style="color:#e53935;">*</span></label>
                <input type="number" step="0.01" min="1" name="amount_requested" required style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:11.5px; margin-bottom:8px; box-sizing:border-box;">

                <label style="font-size:9px; font-weight:600; color:#374151; display:block; margin-bottom:3px;">{{ __('document_credit.field_bank_slip_label') }} <span style="color:#e53935;">*</span></label>
                <input type="file" name="bank_slip" required accept=".jpg,.jpeg,.png,.pdf" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:5px 8px; font-size:10.5px; margin-bottom:10px; box-sizing:border-box; background:#fff;">

                <div style="font-size:9px; color:#9ca3af; margin-bottom:10px;">{{ __('document_credit.topup_fee_note', ['fee' => number_format($topupFee, 2)]) }}</div>

                <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 20px; font-size:11.5px; font-weight:600; cursor:pointer;">{{ __('document_credit.submit_topup_button') }}</button>
            </form>
        </div>

        {{-- Combined activity: pending requests + transaction ledger, Prev/Next paginated, no scroll --}}
        <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; display:flex; flex-direction:column; min-height:0;">
            <div style="font-size:11.5px; font-weight:700; color:#1565C0; margin-bottom:8px;">{{ __('document_credit.my_activity_heading') }}</div>

            @php
                $rows = [];
                $dcStatusLabels = ['PENDING' => __('points.status_pending'), 'REJECTED' => __('points.status_rejected')];
                $dcTypeLabels = ['TOPUP' => __('document_credit.type_topup'), 'DEDUCTION' => __('document_credit.type_deduction'), 'TRANSFER_IN' => __('document_credit.type_transfer_in'), 'TRANSFER_OUT' => __('document_credit.type_transfer_out')];
                foreach ($myTopupRequests as $r) {
                    if ($r->status === 'PENDING') {
                        $rows[] = ['date' => $r->requested_at, 'desc' => __('document_credit.topup_submitted_desc'), 'amount' => '+RM '.number_format($r->amount_requested, 2), 'status' => $dcStatusLabels['PENDING'], 'color' => '#92400e', 'bg' => '#fff8e1'];
                    } elseif ($r->status === 'REJECTED') {
                        $rows[] = ['date' => $r->reviewed_at, 'desc' => __('document_credit.topup_rejected_desc_prefix').($r->admin_note ?? ''), 'amount' => 'RM '.number_format($r->amount_requested, 2), 'status' => $dcStatusLabels['REJECTED'], 'color' => '#b71c1c', 'bg' => '#fde8e8'];
                    }
                }
                foreach ($history as $h) {
                    // FIXED 21 Jul 2026 — per Chris's new Credit Transfer
                    // feature: ledger now has 4 possible types, not just
                    // TOPUP/DEDUCTION. TOPUP and TRANSFER_IN both add to
                    // balance; DEDUCTION and TRANSFER_OUT both reduce it.
                    $isCredit = in_array($h->type, ['TOPUP', 'TRANSFER_IN']);
                    $rowColor = match ($h->type) { 'TOPUP' => '#1b5e20', 'TRANSFER_IN' => '#1565C0', 'TRANSFER_OUT' => '#e65100', default => '#374151' };
                    $rowBg = match ($h->type) { 'TOPUP' => '#e8f5e9', 'TRANSFER_IN' => '#e3f2fd', 'TRANSFER_OUT' => '#fff3e0', default => '#f3f4f6' };
                    $rows[] = [
                        'date' => $h->created_at,
                        'desc' => $h->note,
                        'amount' => ($isCredit ? '+RM ' : '-RM ') . number_format($h->amount, 2),
                        'status' => $dcTypeLabels[$h->type] ?? $h->type,
                        'color' => $rowColor,
                        'bg' => $rowBg,
                    ];
                }
                usort($rows, fn($a, $b) => strtotime($b['date']) <=> strtotime($a['date']));
            @endphp

            <table style="width:100%; border-collapse:collapse; font-size:10.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151; width:90px;">{{ __('gl.col_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:9.5px; color:#374151;">{{ __('document_credit.col_description') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:9.5px; color:#374151; width:90px;">{{ __('document_credit.col_amount') }}</th>
                        <th style="text-align:center; padding:4px 6px; font-size:9.5px; color:#374151; width:80px;">{{ __('gl.col_status') }}</th>
                    </tr>
                </thead>
                <tbody id="dcActivityBody"></tbody>
            </table>
            <div id="dcEmptyMsg" style="display:none; padding:16px; text-align:center; color:#9ca3af; font-size:10.5px;">{{ __('document_credit.no_activity_note') }}</div>

            <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
                <button type="button" id="dcPrevBtn" onclick="dcChangePage(-1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('document_credit.prev_js') }}</button>
                <span id="dcPageInfo" style="font-size:9.5px; color:#6b7280;"></span>
                <button type="button" id="dcNextBtn" onclick="dcChangePage(1)" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('document_credit.next_js') }}</button>
            </div>
        </div>

    </div>
</div>

<script>
const dcRows = @json(array_values($rows));
const dcPageSize = 6;
let dcPage = 0;

function dcRenderPage() {
    const tbody = document.getElementById('dcActivityBody');
    const emptyMsg = document.getElementById('dcEmptyMsg');
    tbody.innerHTML = '';

    if (dcRows.length === 0) {
        emptyMsg.style.display = 'block';
        document.getElementById('dcPageInfo').textContent = '';
        document.getElementById('dcPrevBtn').disabled = true;
        document.getElementById('dcNextBtn').disabled = true;
        return;
    }
    emptyMsg.style.display = 'none';

    const totalPages = Math.ceil(dcRows.length / dcPageSize);
    const start = dcPage * dcPageSize;
    const pageRows = dcRows.slice(start, start + dcPageSize);

    pageRows.forEach(r => {
        const tr = document.createElement('tr');
        tr.style.borderTop = '1px solid #f3f4f6';
        const d = new Date(r.date);
        const dateStr = isNaN(d) ? '' : d.toLocaleDateString('en-GB', {day:'2-digit', month:'short', year:'numeric'});
        tr.innerHTML = `
            <td style="padding:4px 6px;">${dateStr}</td>
            <td style="padding:4px 6px; overflow:hidden; text-overflow:ellipsis;">${r.desc ?? ''}</td>
            <td style="padding:4px 6px; text-align:right; color:${r.color};">${r.amount}</td>
            <td style="padding:4px 6px; text-align:center;"><span style="padding:2px 7px; border-radius:20px; font-size:8.5px; font-weight:600; background:${r.bg}; color:${r.color};">${r.status}</span></td>
        `;
        tbody.appendChild(tr);
    });

    document.getElementById('dcPageInfo').textContent = @json(__('document_credit.page_x_of_y_js', ['current' => '__CUR__', 'total' => '__TOT__'])).replace('__CUR__', dcPage + 1).replace('__TOT__', totalPages);
    document.getElementById('dcPrevBtn').disabled = dcPage === 0;
    document.getElementById('dcNextBtn').disabled = dcPage >= totalPages - 1;
}

function dcChangePage(delta) {
    const totalPages = Math.ceil(dcRows.length / dcPageSize);
    const next = dcPage + delta;
    if (next < 0 || next >= totalPages) return;
    dcPage = next;
    dcRenderPage();
}

dcRenderPage();
</script>
@endsection
