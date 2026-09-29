@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', $ticket->subject)

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:70%;" title="{{ $ticket->subject }}">{{ $ticket->subject }}</div>
        <a href="{{ route('cbe.tickets.index') }}" style="color:var(--gl-blue); text-decoration:none; font-size:10.5px; font-weight:600;">{{ __('cbe_records.back_to_list') }}</a>
    </div>

    @if(session('success'))
    <div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
    @endif

    <div style="flex:1; min-height:0; display:flex; gap:14px;">

        <div style="flex:1.2; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; display:flex; flex-direction:column;">
            <div style="flex-shrink:0; display:flex; gap:8px; margin-bottom:6px; font-size:9.5px; color:#6b7280;">
                <span>{{ $ticket->category_label ?? __('cbe_records.ticket_none_category') }}</span>
                <span>·</span>
                <span>{{ __('cbe_records.ticket_status_'.strtolower($ticket->status)) }}</span>
                <span>·</span>
                <span>{{ $ticket->raised_by_name }}</span>
            </div>
            <div style="flex:1; min-height:0; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                <div style="background:#f8f9fb; border-radius:8px; padding:8px 10px;">
                    <div style="font-size:9px; font-weight:700; color:#546E7A; margin-bottom:2px;">{{ $ticket->raised_by_name }} · {{ \Carbon\Carbon::parse($ticket->created_at)->format('d M Y g:i A') }}</div>
                    <div style="font-size:11px; color:#263238; white-space:pre-wrap;">{{ $ticket->body }}</div>
                </div>
                @foreach($replies as $r)
                <div style="background:{{ $r->sender_agent_id === auth('agent')->id() ? '#e3f0ff' : '#f8f9fb' }}; border-radius:8px; padding:8px 10px; max-width:88%; {{ $r->sender_agent_id === auth('agent')->id() ? 'align-self:flex-end;' : '' }}">
                    <div style="font-size:9px; font-weight:700; color:#546E7A; margin-bottom:2px;">{{ $r->sender_name }} · {{ \Carbon\Carbon::parse($r->created_at)->format('d M Y g:i A') }}</div>
                    <div style="font-size:11px; color:#263238; white-space:pre-wrap;">{{ $r->body }}</div>
                </div>
                @endforeach
            </div>

            @if(!in_array($ticket->status, ['RESOLVED', 'CLOSED']) || $canManage)
            <form method="POST" action="{{ route('cbe.tickets.reply', $ticket->ticket_id) }}" style="flex-shrink:0; margin-top:8px;">
                @csrf
                <textarea name="body" id="ticketReplyBody" maxlength="3000" required placeholder="{{ __('cbe_records.field_ticket_reply_placeholder') }}" style="width:100%; resize:none; height:56px; border:1px solid #d1d5db; border-radius:6px; padding:7px 10px; font-size:11px; box-sizing:border-box; margin-bottom:4px;"></textarea>
                @include('partials.carolyn-write-assist', ['carolynBodyId' => 'ticketReplyBody', 'carolynType' => 'cbe_ticket_message'])
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:6px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.ticket_reply_button') }}</button>
            </form>
            @endif
        </div>

        @if($canManage)
        <div style="flex:0.7; min-width:0; background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:12px; overflow-y:auto;">
            <div style="font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:8px;">{{ __('cbe_records.triage_panel_title') }}</div>
            <form method="POST" action="{{ route('cbe.tickets.triage', $ticket->ticket_id) }}" style="display:flex; flex-direction:column; gap:8px;">
                @csrf
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_ticket_category') }}</label>
                    <select name="category_id" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        <option value="">{{ __('cbe_records.ticket_none_category') }}</option>
                        @foreach($categories as $c)
                        <option value="{{ $c->id }}" @selected($ticket->category_id === $c->id)>{{ $c->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.col_ticket_status') }}</label>
                    <select name="status" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                        @foreach(['OPEN', 'TRIAGED', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'] as $status)
                        <option value="{{ $status }}" @selected($ticket->status === $status)>{{ __('cbe_records.ticket_status_'.strtolower($status)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="position:relative;">
                    <label style="display:block; font-size:9.5px; font-weight:700; color:#546E7A; text-transform:uppercase; margin-bottom:3px;">{{ __('cbe_records.field_ticket_assign_to') }}</label>
                    <input type="text" id="assigneeSearch" autocomplete="off" placeholder="{{ __('cbe_records.field_ticket_assign_to_placeholder') }}" value="{{ $ticket->assigned_to_name }}" style="width:100%; border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; box-sizing:border-box;">
                    <input type="hidden" name="assigned_to_agent_id" id="assigneeAgentId" value="{{ $ticket->assigned_to_agent_id }}">
                    <div id="assigneeResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:#fff; border:1px solid #d1d5db; border-radius:6px; max-height:140px; overflow-y:auto; z-index:10; box-shadow:0 4px 10px rgba(0,0,0,.08);"></div>
                </div>
                <button type="submit" style="background:var(--gl-blue); color:#fff; border:none; border-radius:20px; padding:7px 18px; font-size:10.5px; font-weight:600; cursor:pointer;">{{ __('cbe_records.save_button') }}</button>
            </form>
        </div>
        @endif
    </div>
</div>

@if($canManage)
<script>
(function() {
    var input = document.getElementById('assigneeSearch');
    var hidden = document.getElementById('assigneeAgentId');
    var box = document.getElementById('assigneeResults');
    var timer = null;

    input.addEventListener('input', function() {
        hidden.value = '';
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { box.style.display = 'none'; box.innerHTML = ''; return; }
        timer = setTimeout(function() {
            fetch('{{ route("cbe.tickets.assignee-typeahead", $ticket->ticket_id) }}?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(rows) {
                    box.innerHTML = '';
                    if (!rows.length) { box.style.display = 'none'; return; }
                    rows.forEach(function(row) {
                        var item = document.createElement('div');
                        item.textContent = row.full_name;
                        item.style.padding = '6px 10px';
                        item.style.fontSize = '11px';
                        item.style.cursor = 'pointer';
                        item.onmouseover = function() { item.style.background = '#f3f4f6'; };
                        item.onmouseout = function() { item.style.background = '#fff'; };
                        item.onclick = function() {
                            input.value = row.full_name;
                            hidden.value = row.agent_id;
                            box.style.display = 'none';
                        };
                        box.appendChild(item);
                    });
                    box.style.display = 'block';
                });
        }, 250);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== input) { box.style.display = 'none'; }
    });
})();
</script>
@endif
@endsection
