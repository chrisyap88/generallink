@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.minutes_page_title'))

@section('content')

{{-- NEW 22 Aug 2026 — CBE Meeting Minutes list, scoped to the agent's own
     cbe_node_id (their Temple/Branch/State/HQ). Same no-scroll shell +
     Prev/Next pagination pattern as admin.notice-board.index. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.minutes_page_title') }}</div>
        @if($hasNode && $canManage)
        <div style="display:flex; gap:8px; align-items:center;">
            <a href="{{ route('cbe.minutes.meeting-types') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10px; font-weight:600;">{{ __('cbe_records.manage_meeting_types_link') }}</a>
            <a href="{{ route('cbe.minutes.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.minutes_add_button') }}</a>
        </div>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_date') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_title') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_attachment') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($minutes as $m)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ \Carbon\Carbon::parse($m->meeting_date)->format('d M Y') }}</td>
                        <td style="padding:5px 8px;">
                            <a href="{{ route('cbe.minutes.show', $m->minute_id) }}" style="font-weight:600; color:var(--gl-blue); text-decoration:none;">{{ $m->title }}</a>
                        </td>
                        <td style="padding:5px 8px;">
                            @if($m->attachment_path)
                            <a href="{{ route('cbe.minutes.download', $m->minute_id) }}" style="color:var(--gl-blue); font-weight:600; text-decoration:none;">{{ __('cbe_records.view_attachment') }}</a>
                            @else
                            <span style="color:#9ca3af;">{{ __('cbe_records.no_attachment') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_minutes_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($minutes->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $minutes->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $minutes->currentPage(), 'last' => $minutes->lastPage(), 'total' => $minutes->total()]) }}</span>
            @if($minutes->hasMorePages())
                <a href="{{ $minutes->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
