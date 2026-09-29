@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_ai.batches_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_ai.batches_page_title') }}</div>
        <div style="display:flex; gap:12px; align-items:center;">
            <a href="{{ route('cbe.ai-accounting.batches.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:14px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('cbe_ai.upload_button') }}</a>
            <a href="{{ route('cbe.accounting.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_accounting.back_to_accounting') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
    <div style="background:#fff8e1; border-left:3px solid #D97706; color:#8d6e00; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('warning') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_batch_label') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.field_bank_account') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_ai.col_uploaded_by') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_accounting.col_status') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('breakaway.col_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $batchStatusColors = ['UPLOADED' => '#9ca3af', 'REVIEWED' => '#D97706', 'COMMITTED' => '#2e7d32']; @endphp
                    @forelse($batches as $b)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; font-weight:600;"><a href="{{ route('cbe.ai-accounting.batches.show', $b->batch_id) }}" style="color:var(--gl-blue); text-decoration:none;">{{ $b->label }}</a></td>
                        <td style="padding:5px 8px; color:#263238;">{{ $b->bank_name ? $b->bank_name.($b->account_name ? ' — '.$b->account_name : '') : '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $b->uploaded_by_name }}</td>
                        <td style="padding:5px 8px;"><span style="color:{{ $batchStatusColors[$b->status] ?? '#546E7A' }}; font-weight:600;">{{ __('cbe_ai.batch_status_'.strtolower($b->status)) }}</span></td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($b->created_at)->format('d M Y') }}</td>
                        {{-- NEW 16 Sep 2026 — per Chris: "you should have
                             delete option". Hidden once any line in the
                             batch has been committed to real bank
                             transactions — the server blocks that delete
                             too, but hiding the link here avoids showing
                             an action that would only get refused. --}}
                        <td style="padding:5px 8px; text-align:right; white-space:nowrap;">
                            @if(! $b->has_committed_lines)
                            <form method="POST" action="{{ route('cbe.ai-accounting.batches.destroy', $b->batch_id) }}" style="display:inline;" onsubmit="return confirm({{ json_encode(__('cbe_ai.delete_batch_confirm_js')) }});">
                                @csrf
                                <button type="submit" style="background:none; border:none; color:#e53935; font-weight:600; font-size:9.5px; cursor:pointer;">{{ __('cbe_ai.delete_button') }}</button>
                            </form>
                            @else
                            <span style="color:#c4c9d0;">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_ai.no_batches_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($batches->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $batches->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $batches->currentPage(), 'last' => $batches->lastPage(), 'total' => $batches->total()]) }}</span>
            @if($batches->hasMorePages())
                <a href="{{ $batches->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
