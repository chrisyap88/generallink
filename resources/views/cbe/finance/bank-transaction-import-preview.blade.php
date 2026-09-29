@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.import_preview_page_title'))

@section('content')

{{-- NEW 4 Sep 2026 (Task #396) — shows every parsed row with its status
     (Valid / Invalid / Duplicate) before anything is saved. Only VALID
     rows are ever committed; Invalid/Duplicate rows stay visible here
     so the officer knows exactly what to fix and re-upload. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.import_preview_page_title') }} — {{ $account->bank_name }}</div>
        <a href="{{ route('cbe.finance.bank-transactions', $account->bank_account_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:8px;">
        <a href="{{ route('cbe.finance.bank-transaction-import.preview', ['batch' => $batch->batch_id, 'filter' => 'ALL']) }}" style="text-decoration:none; padding:6px 12px; border-radius:6px; font-size:9.5px; font-weight:700; background:{{ $filter === 'ALL' ? 'var(--gl-blue)' : '#f3f4f6' }}; color:{{ $filter === 'ALL' ? '#fff' : '#546E7A' }};">{{ __('cbe_records.filter_all') }} ({{ $batch->total_rows }})</a>
        <a href="{{ route('cbe.finance.bank-transaction-import.preview', ['batch' => $batch->batch_id, 'filter' => 'VALID']) }}" style="text-decoration:none; padding:6px 12px; border-radius:6px; font-size:9.5px; font-weight:700; background:{{ $filter === 'VALID' ? '#2e7d32' : '#e8f5e9' }}; color:{{ $filter === 'VALID' ? '#fff' : '#1b5e20' }};">{{ __('cbe_records.filter_valid') }} ({{ $batch->valid_rows }})</a>
        <a href="{{ route('cbe.finance.bank-transaction-import.preview', ['batch' => $batch->batch_id, 'filter' => 'DUPLICATE']) }}" style="text-decoration:none; padding:6px 12px; border-radius:6px; font-size:9.5px; font-weight:700; background:{{ $filter === 'DUPLICATE' ? '#f9a825' : '#fff8e1' }}; color:{{ $filter === 'DUPLICATE' ? '#fff' : '#8d6e00' }};">{{ __('cbe_records.filter_duplicate') }} ({{ $batch->duplicate_rows }})</a>
        <a href="{{ route('cbe.finance.bank-transaction-import.preview', ['batch' => $batch->batch_id, 'filter' => 'INVALID']) }}" style="text-decoration:none; padding:6px 12px; border-radius:6px; font-size:9.5px; font-weight:700; background:{{ $filter === 'INVALID' ? '#e53935' : '#fdecea' }}; color:{{ $filter === 'INVALID' ? '#fff' : '#b71c1c' }};">{{ __('cbe_records.filter_invalid') }} ({{ $batch->invalid_rows }})</a>
    </div>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">#</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_transaction_date') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                        <th style="text-align:right; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_status') }}</th>
                        <th style="text-align:left; padding:4px 6px; font-size:8px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_error') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $r)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 6px; color:#9ca3af;">{{ $r->row_number }}</td>
                        <td style="padding:5px 6px; color:#6b7280; white-space:nowrap;">{{ $r->transaction_date ? \Carbon\Carbon::parse($r->transaction_date)->format('d/m/Y') : ($r->transaction_date_raw ?: '—') }}</td>
                        <td style="padding:5px 6px; color:#263238;">{{ \Illuminate\Support\Str::limit($r->description ?: '—', 35) }}</td>
                        <td style="padding:5px 6px; text-align:right; color:#455A64;">{{ $r->credit ? '+'.number_format($r->credit, 2) : ($r->debit ? '-'.number_format($r->debit, 2) : '—') }}</td>
                        <td style="padding:5px 6px;">
                            @if($r->status === 'VALID')
                            <span style="background:#e8f5e9; color:#1b5e20; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('cbe_records.filter_valid') }}</span>
                            @elseif($r->status === 'DUPLICATE')
                            <span style="background:#fff8e1; color:#8d6e00; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('cbe_records.filter_duplicate') }}</span>
                            @else
                            <span style="background:#fdecea; color:#b71c1c; border-radius:10px; padding:2px 8px; font-size:8.5px; font-weight:600;">{{ __('cbe_records.filter_invalid') }}</span>
                            @endif
                        </td>
                        <td style="padding:5px 6px; color:#9ca3af; font-size:9px;">{{ $r->error_message ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_import_rows_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($rows->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $rows->currentPage(), 'last' => $rows->lastPage(), 'total' => $rows->total()]) }}</span>
            @if($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

    @if($batch->status === 'PREVIEW')
    <div style="flex-shrink:0; padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
        <form method="POST" action="{{ route('cbe.finance.bank-transaction-import.cancel', $batch->batch_id) }}" onsubmit="return confirm({{ json_encode(__('cbe_records.cancel_import_confirm_js')) }});">
            @csrf
            <button type="submit" style="background:#c4c9d0; color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.cancel_import_button') }}</button>
        </form>
        <form method="POST" action="{{ route('cbe.finance.bank-transaction-import.commit', $batch->batch_id) }}" style="display:flex; gap:8px; align-items:center;">
            @csrf
            <select name="transaction_type_id" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 9px; font-size:10.5px;">
                <option value="">{{ __('cbe_records.field_no_default_gl_account') }}</option>
                @foreach($types as $t)
                <option value="{{ $t->type_id }}">{{ $t->type_name }}</option>
                @endforeach
            </select>
            <button type="submit" {{ $batch->valid_rows > 0 ? '' : 'disabled' }} style="background:{{ $batch->valid_rows > 0 ? 'var(--gl-blue)' : '#c4c9d0' }}; color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:{{ $batch->valid_rows > 0 ? 'pointer' : 'not-allowed' }};">{{ __('cbe_records.commit_import_button', ['count' => $batch->valid_rows]) }}</button>
        </form>
    </div>
    @else
    <div style="flex-shrink:0; padding-top:10px; font-size:10px; color:#1b5e20;">{{ __('cbe_records.import_already_committed_note', ['count' => $batch->committed_rows]) }}</div>
    @endif
</div>
@endsection
