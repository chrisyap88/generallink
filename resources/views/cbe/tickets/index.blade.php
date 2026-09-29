@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.tickets_page_title'))

@section('content')

{{-- NEW 17 Sep 2026 — per Chris: CBE Support Tickets. Ordinary members
     see only their own tickets; the Secretary/officers see every
     ticket in the entity (their triage queue) — see CbeTicketController. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.tickets_page_title') }}</div>
        @if($hasNode)
        <a href="{{ route('cbe.tickets.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.tickets_add_button') }}</a>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        @if(!$hasNode)
        <div style="margin:auto; text-align:center; color:#9ca3af; font-size:11px; max-width:320px;">{{ __('cbe_records.no_node_note') }}</div>
        @else
        <div style="flex-shrink:0; display:flex; gap:6px; margin-bottom:8px;">
            <a href="{{ route('cbe.tickets.index', ['tab' => 'open']) }}" style="text-decoration:none; padding:4px 12px; border-radius:14px; font-size:9.5px; font-weight:600; {{ $tab === 'open' ? 'background:var(--gl-blue); color:#fff;' : 'background:var(--gl-light); color:#546E7A;' }}">{{ __('cbe_records.tickets_tab_open') }}</a>
            <a href="{{ route('cbe.tickets.index', ['tab' => 'resolved']) }}" style="text-decoration:none; padding:4px 12px; border-radius:14px; font-size:9.5px; font-weight:600; {{ $tab === 'resolved' ? 'background:var(--gl-blue); color:#fff;' : 'background:var(--gl-light); color:#546E7A;' }}">{{ __('cbe_records.tickets_tab_resolved') }}</a>
        </div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:var(--gl-light);">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_ticket_subject') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_ticket_category') }}</th>
                        @if($canManage)
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_ticket_raised_by') }}</th>
                        @endif
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.col_ticket_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $t)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('cbe.tickets.show', $t->ticket_id) }}'">
                        <td style="padding:5px 8px; font-weight:600; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px;" title="{{ $t->subject }}">{{ $t->subject }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $t->category_label ?? __('cbe_records.ticket_none_category') }}</td>
                        @if($canManage)
                        <td style="padding:5px 8px; color:#6b7280;">{{ $t->raised_by_name }}</td>
                        @endif
                        <td style="padding:5px 8px; color:#6b7280;">{{ __('cbe_records.ticket_status_'.strtolower($t->status)) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canManage ? 4 : 3 }}" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_tickets_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($tickets->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $tickets->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['current' => $tickets->currentPage(), 'last' => $tickets->lastPage(), 'total' => $tickets->total()]) }}</span>
            @if($tickets->hasMorePages())
                <a href="{{ $tickets->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
    </div>
</div>
@endsection
