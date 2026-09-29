@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_events.contributions_page_title'))

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div>
            <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_events.contributions_page_title') }}</div>
            <div style="font-size:9.5px; color:#6b7280;">{{ $event->event_name }}</div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('cbe.events.show', $event->event_id) }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600; align-self:center;">{{ __('cbe_events.back_to_event') }}</a>
            <a href="{{ route('cbe.contributions.create', $event->event_id) }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_events.contribution_add_button') }}</a>
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
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_donor_name') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_type') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_pledged') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_received') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_events.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($contributions as $c)
                    @php
                        $statusColor = ['PLEDGED' => '#f9a825', 'PARTIALLY_PAID' => 'var(--gl-blue)', 'FULLY_PAID' => '#2e7d32', 'RECEIVED' => '#2e7d32', 'CANCELLED' => '#9ca3af'][$c->status] ?? '#6b7280';
                    @endphp
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.contributions.show', $c->contribution_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:#263238;">{{ $c->donor_name }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_events.type_' . strtolower($c->contribution_type)) }}</td>
                        <td style="padding:5px 8px; text-align:right; color:#6b7280;">{{ $c->pledged_amount ? 'RM ' . number_format($c->pledged_amount, 2) : '—' }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:600; color:#2e7d32;">{{ $c->received_amount > 0 ? 'RM ' . number_format($c->received_amount, 2) : '—' }}</td>
                        <td style="padding:5px 8px;"><span style="background:{{ $statusColor }}22; color:{{ $statusColor }}; border-radius:10px; padding:2px 9px; font-size:9px; font-weight:700;">{{ __('cbe_events.status_' . strtolower($c->status)) }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_events.no_contributions_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($contributions->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $contributions->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $contributions->currentPage(), 'last' => $contributions->lastPage(), 'total' => $contributions->total()]) }}</span>
            @if($contributions->hasMorePages())
                <a href="{{ $contributions->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
