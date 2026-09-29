@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.expenses_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_events.expenses_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $event->event_name }}</div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('cbe.events.show', $event->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600; align-self:center;">{{ __('cbe_events.back_to_event') }}</a>
            <a href="{{ route('cbe.expenses.create', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_events.expense_add_button') }}</a>
        </div>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_category') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_description') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_amount') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_attachment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($expenses as $x)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($x->expense_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px; color:#263238; font-weight:600;">{{ $x->category_name ?: '—' }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $x->description ?: '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:700; color:#c62828;">RM {{ number_format($x->amount, 2) }}</td>
                        <td style="padding:5px 8px;">
                            @if($x->receipt_attachment_path)
                            <a href="{{ route('cbe.expenses.receipt', $x->expense_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('cbe_records.view_attachment') }}</a>
                            @else
                            <span style="color:#9ca3af;">{{ __('cbe_records.no_attachment') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_events.no_expenses_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($expenses->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $expenses->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $expenses->currentPage(), 'last' => $expenses->lastPage(), 'total' => $expenses->total()]) }}</span>
            @if($expenses->hasMorePages())
                <a href="{{ $expenses->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
