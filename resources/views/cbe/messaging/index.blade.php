@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_records.messaging_page_title'))

@section('content')

{{-- NEW 17 Sep 2026 — per Chris: internal messaging within one CBE
     entity (president message finance, finance reply, secretary
     message a member, etc.) — anyone to anyone in the SAME entity, no
     upline/downline rule (see CbeMessagingController). --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238;">{{ __('cbe_records.messaging_page_title') }}</div>
        @if($hasNode)
        <a href="{{ route('cbe.messaging.create') }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:6px; padding:6px 16px; font-size:10.5px; font-weight:600;">{{ __('cbe_records.messaging_new_button') }}</a>
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
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_message_subject') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_message_with') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('cbe_records.field_message_last_activity') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($threads as $t)
                    @php($isInitiator = $t->initiator_agent_id === $agentId)
                    @php($otherName = $isInitiator ? $t->recipient_name : $t->initiator_name)
                    @php($unread = $isInitiator ? !$t->initiator_read_at : !$t->recipient_read_at)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:5px 8px; {{ $unread ? 'font-weight:700;' : 'font-weight:600;' }} color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:260px;">
                            <a href="{{ route('cbe.messaging.show', $t->thread_id) }}" style="color:inherit; text-decoration:none;">{{ $t->subject }}</a>
                            @if($unread)<span class="badge" style="margin-left:6px;">{{ __('cbe_records.messaging_unread_tag') }}</span>@endif
                        </td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $otherName }}</td>
                        <td style="padding:5px 8px; color:#6b7280; white-space:nowrap;">{{ $t->last_message_at ? \Carbon\Carbon::parse($t->last_message_at)->format('d M Y g:i A') : '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('cbe_records.no_messages_note') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($threads instanceof \Illuminate\Pagination\AbstractPaginator && $threads->hasPages())
        <div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
            @if($threads->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $threads->previousPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.prev') }}</a>
            @endif
            <div style="font-size:9.5px; color:#6b7280;">{{ __('cbe_records.page_x_of_y', ['x' => $threads->currentPage(), 'y' => $threads->lastPage()]) }}</div>
            @if($threads->hasMorePages())
                <a href="{{ $threads->nextPageUrl() }}" style="background:var(--gl-blue); color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:600;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
        @endif
        @endif
    </div>
</div>
@endsection
