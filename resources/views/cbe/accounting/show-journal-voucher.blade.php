@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_accounting.jv_detail_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_accounting.jv_detail_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            @if(!empty($bankReconciliationId))
            <a href="{{ route('cbe.accounting.bank-reconciliations.show', $bankReconciliationId) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.jv_view_bank_reconciliation_link') }}</a>
            @endif
            <a href="{{ url()->previous() }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div style="background:#fdecea; border-left:3px solid #e53935; color:#b71c1c; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('error') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:14px; flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="flex-shrink:0; display:flex; gap:20px; margin-bottom:6px; font-size:10.5px; align-items:flex-start;">
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_journal_no') }}</span><br>{{ $voucher->journal_no ?: '—' }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_jv_ref_no') }}</span><br>{{ $voucher->reference_no ?: '—' }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_jv_date') }}</span><br>{{ \Carbon\Carbon::parse($voucher->entry_date)->format('d M Y') }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_th_source') }}</span><br>{{ __('cbe_accounting.th_source_'.strtolower($voucher->source_type)) }}</div>
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.field_journal_type') }}</span><br>{{ $voucher->type_name_zh ? $voucher->type_name.' ('.$voucher->type_name_zh.')' : ($voucher->type_name ?: '—') }}</div>
            <div style="text-align:right; margin-left:auto;">
                <span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_status') }}</span><br>
                <span style="color:{{ $voucher->status === 'VOIDED' ? '#9ca3af' : '#2e7d32' }}; font-weight:700;">{{ __('cbe_accounting.th_status_'.strtolower($voucher->status)) }}</span>
            </div>
        </div>
        <div style="flex-shrink:0; margin-bottom:10px; font-size:10.5px; display:flex; justify-content:space-between; align-items:flex-end;">
            <div><span style="color:#546E7A; font-weight:700; text-transform:uppercase; font-size:8.5px;">{{ __('cbe_accounting.col_jv_description') }}</span><br>{{ $voucher->description ?: '—' }}</div>
            @if($voucher->attachment_path)
            <a href="{{ route('cbe.accounting.journal-vouchers.attachment', $voucher->journal_id) }}" target="_blank" style="color:var(--gl-blue); text-decoration:none; font-weight:600; white-space:nowrap;">{{ __('cbe_accounting.jv_view_attachment_link') }}</a>
            @endif
        </div>

        @if($voucher->status === 'VOIDED')
        <div style="flex-shrink:0; background:#f3f4f6; border-radius:6px; padding:6px 10px; margin-bottom:8px; font-size:9.5px; color:#546E7A;">
            {{ __('cbe_accounting.jv_voided_note', ['date' => \Carbon\Carbon::parse($voucher->voided_at)->format('d M Y'), 'reason' => $voucher->void_reason]) }}
            @if($reversedByRefNo)
            — <a href="{{ route('cbe.accounting.journal-vouchers.show', $voucher->reversed_by_journal_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ __('cbe_accounting.jv_view_reversal_link', ['ref' => $reversedByRefNo]) }}</a>
            @endif
        </div>
        @endif
        @if($voucher->reverses_journal_id)
        <div style="flex-shrink:0; background:#eef4fb; border-radius:6px; padding:6px 10px; margin-bottom:8px; font-size:9.5px; color:#263238;">
            {{ __('cbe_accounting.jv_is_reversal_note') }}
            <a href="{{ route('cbe.accounting.journal-vouchers.show', $voucher->reverses_journal_id) }}" style="color:var(--gl-blue); text-decoration:none; font-weight:600;">{{ $reversesRefNo ?: __('cbe_accounting.jv_view_original_link') }}</a>
        </div>
        @endif
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:10px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_name') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_jv_line_description') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.col_cost_centre') }}</th>
                        <th style="text-align:left; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:110px;">{{ __('cbe_accounting.col_fund') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_jv_debit') }}</th>
                        <th style="text-align:right; padding:5px 6px; font-size:8.5px; color:#546E7A; text-transform:uppercase; width:100px;">{{ __('cbe_accounting.col_jv_credit') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $l)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px 6px; font-weight:600; color:#263238;">{{ $l->account_code }} — {{ $l->account_name }}</td>
                        <td style="padding:6px 6px; color:#6b7280;">{{ $l->memo ?: '—' }}</td>
                        <td style="padding:6px 6px; color:#6b7280;">{{ $l->centre_name_zh ? $l->centre_name.' ('.$l->centre_name_zh.')' : ($l->centre_name ?: '—') }}</td>
                        <td style="padding:6px 6px; color:#6b7280;">{{ $l->fund_name ?: '—' }}</td>
                        <td style="padding:6px 6px; text-align:right;">{{ $l->debit > 0 ? 'RM '.number_format($l->debit, 2) : '—' }}</td>
                        <td style="padding:6px 6px; text-align:right;">{{ $l->credit > 0 ? 'RM '.number_format($l->credit, 2) : '—' }}</td>
                    </tr>
                    @endforeach
                    <tr>
                        <td style="padding:6px 6px;"></td>
                        <td style="padding:6px 6px;"></td>
                        <td style="padding:6px 6px;"></td>
                        <td style="padding:6px 6px; font-weight:700; text-align:right;">{{ __('cbe_accounting.jv_total_label') }}</td>
                        <td style="padding:6px 6px; text-align:right; font-weight:700; border-top:2px solid #263238;">RM {{ number_format($lines->sum('debit'), 2) }}</td>
                        <td style="padding:6px 6px; text-align:right; font-weight:700; border-top:2px solid #263238;">RM {{ number_format($lines->sum('credit'), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @if($voucher->status === 'POSTED' && ! $voucher->reverses_journal_id)
        <form method="POST" action="{{ route('cbe.accounting.journal-vouchers.void', $voucher->journal_id) }}" style="flex-shrink:0; display:flex; gap:6px; align-items:flex-end; margin-top:8px; padding-top:8px; border-top:1px solid #f3f4f6;" onsubmit="return confirm('{{ __('cbe_accounting.jv_void_confirm_js') }}');">
            @csrf
            <div style="flex:1;">
                <label style="display:block; font-size:8px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:2px;">{{ __('cbe_accounting.field_void_reason') }}</label>
                <input type="text" name="void_reason" maxlength="255" required style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10px; box-sizing:border-box;">
            </div>
            <button type="submit" style="background:#c62828; color:#fff; border:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; cursor:pointer; white-space:nowrap;">{{ __('cbe_accounting.jv_void_button') }}</button>
        </form>
        @endif
    </div>
</div>
@endsection
