@extends('layouts.dashboard')

@section('title', __('support_tickets.page_title'))
@section('page-title', __('support_tickets.page_title'))

{{-- NEW 29 Jul 2026 — Support Tickets (task #259). Per Chris: "make full
     use of EspoCRM" — Help Desk / Case Management module. Every ticket
     here also exists as a Case in EspoCRM (free, core feature) —
     GeneralLink stays the only screen anyone actually uses; see
     EspoCrmService::createCase / updateCaseStatus. Scoped exactly like
     the Customer record itself (owning agent only, never downline). --}}

@section('content')

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:0 16px; box-sizing:border-box;">

<div style="flex-shrink:0; padding:6px 0 4px;">
    <p style="font-size:9.5px; color:#9ca3af; margin:2px 0 0;">{{ __('support_tickets.intro_note') }}</p>
</div>

@if(session('success'))
<div style="background:#e8f5e9; border-left:3px solid #38A169; color:#1b5e20; border-radius:6px; padding:4px 10px; font-size:10px; margin-bottom:6px;">{{ session('success') }}</div>
@endif

<form method="GET" style="flex-shrink:0; display:flex; gap:8px; align-items:flex-end; margin-bottom:6px;">
    <div>
        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('support_tickets.field_status') }}</label>
        <select name="status" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px;">
            <option value="">{{ __('support_tickets.open_in_progress_default_option') }}</option>
            <option value="OPEN" {{ request('status')==='OPEN'?'selected':'' }}>{{ __('customers.ticket_status_open') }}</option>
            <option value="IN_PROGRESS" {{ request('status')==='IN_PROGRESS'?'selected':'' }}>{{ __('customers.ticket_status_in_progress') }}</option>
            <option value="RESOLVED" {{ request('status')==='RESOLVED'?'selected':'' }}>{{ __('customers.ticket_status_resolved') }}</option>
            <option value="CLOSED" {{ request('status')==='CLOSED'?'selected':'' }}>{{ __('customers.ticket_status_closed') }}</option>
        </select>
    </div>
    <div style="flex:1 1 220px; position:relative;">
        <label style="font-size:9px; font-weight:600; color:#9ca3af; display:block; margin-bottom:2px;">{{ __('gl.search_button') }}</label>
        <input type="text" name="search" id="stSearchInput" autocomplete="off" value="{{ request('search') }}" placeholder="{{ __('support_tickets.search_subject_customer_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; box-sizing:border-box;">
        <div id="stSearchDropdown" style="display:none; position:absolute; top:100%; left:0; width:100%; background:#fff; border:1px solid #d1d5db; border-radius:5px; box-shadow:0 4px 10px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto; z-index:50;"></div>
    </div>
    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:5px; padding:6px 14px; font-size:10.5px; font-weight:700; cursor:pointer;">{{ __('gl.search_button') }}</button>
</form>

<div style="flex:1 1 auto; min-height:0; overflow-y:auto;">
    <div style="background:#fff; border:1px solid #E2E8F0; border-radius:8px; overflow:hidden;">
        <table style="width:100%; border-collapse:collapse; font-size:9.5px; table-layout:fixed;">
            <thead>
                <tr style="background:#f0f7ff;">
                    <th style="width:80px; padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('support_tickets.col_priority') }}</th>
                    <th style="padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('gl.col_customer') }}</th>
                    <th style="padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('support_tickets.col_subject') }}</th>
                    <th style="width:100px; padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('support_tickets.col_type') }}</th>
                    <th style="width:90px; padding:6px 8px; text-align:left; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('support_tickets.col_due') }}</th>
                    <th style="width:130px; padding:6px 8px; text-align:center; font-size:8.5px; color:#546E7A; text-transform:uppercase; letter-spacing:0.4px; border-bottom:1px solid #B2EBF2;">{{ __('gl.col_status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tickets as $t)
                @php
                    $pc = ['HIGH'=>['#fee2e2','#991b1b'],'MEDIUM'=>['#fef3c7','#92400e'],'LOW'=>['#e0f2fe','#075985']][$t->priority] ?? ['#f3f4f6','#374151'];
                    $sc = ['OPEN'=>['#dbeafe','#1e40af'],'IN_PROGRESS'=>['#fef3c7','#92400e'],'RESOLVED'=>['#d1fae5','#065f46'],'CLOSED'=>['#f3f4f6','#374151']][$t->status] ?? ['#f3f4f6','#374151'];
                    $overdue = $t->due_at && \Carbon\Carbon::parse($t->due_at)->isPast() && !in_array($t->status, ['RESOLVED','CLOSED']);
                    $priorityLabels = ['HIGH'=>__('customers.priority_high'),'MEDIUM'=>__('customers.priority_medium'),'LOW'=>__('customers.priority_low')];
                    $ticketTypeLabels = ['COMPLAINT'=>__('customers.ticket_type_complaint'),'SERVICE_REQUEST'=>__('customers.ticket_type_service_request'),'QUESTION'=>__('customers.ticket_type_question'),'OTHER'=>__('customers.ticket_type_other')];
                    $ticketStatusLabels = ['OPEN'=>__('customers.ticket_status_open'),'IN_PROGRESS'=>__('customers.ticket_status_in_progress'),'RESOLVED'=>__('customers.ticket_status_resolved'),'CLOSED'=>__('customers.ticket_status_closed')];
                @endphp
                <tr style="border-bottom:1px solid #f0f4f8;">
                    <td style="padding:6px 8px;"><span style="background:{{ $pc[0] }}; color:{{ $pc[1] }}; padding:1px 8px; border-radius:20px; font-size:8.5px; font-weight:700;">{{ $priorityLabels[$t->priority] ?? $t->priority }}</span></td>
                    <td style="padding:6px 8px; font-weight:600; color:#1565C0;">{{ $t->customer_name }}</td>
                    <td style="padding:6px 8px; color:#374151; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $t->subject }}</td>
                    <td style="padding:6px 8px; color:#6b7280;">{{ $ticketTypeLabels[$t->ticket_type] ?? ucwords(strtolower(str_replace('_',' ',$t->ticket_type))) }}</td>
                    <td style="padding:6px 8px; {{ $overdue ? 'color:#B91C1C; font-weight:700;' : 'color:#546E7A;' }}">
                        {{ $t->due_at ? \Carbon\Carbon::parse($t->due_at)->format('d M, g:ia') : '—' }}
                        @if($overdue) <br><span style="font-size:8px;">{{ __('support_tickets.overdue_word') }}</span> @endif
                    </td>
                    <td style="padding:6px 8px; text-align:center;">
                        <form method="POST" action="{{ route('support-tickets.update-status', $t->ticket_id) }}" style="margin:0;">
                            @csrf
                            <select name="status" onchange="this.form.submit()" style="background:{{ $sc[0] }}; color:{{ $sc[1] }}; border:none; border-radius:20px; padding:2px 6px; font-size:8.5px; font-weight:700;">
                                <option value="OPEN" {{ $t->status==='OPEN'?'selected':'' }}>{{ __('customers.ticket_status_open') }}</option>
                                <option value="IN_PROGRESS" {{ $t->status==='IN_PROGRESS'?'selected':'' }}>{{ __('customers.ticket_status_in_progress') }}</option>
                                <option value="RESOLVED" {{ $t->status==='RESOLVED'?'selected':'' }}>{{ __('customers.ticket_status_resolved') }}</option>
                                <option value="CLOSED" {{ $t->status==='CLOSED'?'selected':'' }}>{{ __('customers.ticket_status_closed') }}</option>
                            </select>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="padding:24px; text-align:center; color:#999; font-size:10.5px;">{{ __('support_tickets.no_tickets_found_note') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div style="flex-shrink:0; display:flex; justify-content:space-between; align-items:center; padding:6px 0;">
    @if($tickets->onFirstPage())
    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</span>
    @else
    <a href="{{ $tickets->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.prev') }}</a>
    @endif
    <span style="font-size:9px; color:#607d8b;">{{ __('support_tickets.page_x_of_y_total', ['current' => $tickets->currentPage(), 'last' => $tickets->lastPage(), 'total' => $tickets->total()]) }}</span>
    @if($tickets->hasMorePages())
    <a href="{{ $tickets->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</a>
    @else
    <span style="background:#1565C0; color:#fff; border-radius:20px; padding:4px 12px; font-size:9.5px; font-weight:700;">{{ __('network.next') }}</span>
    @endif
</div>

</div>
<script>
(function () {
    var input = document.getElementById('stSearchInput');
    var dropdown = document.getElementById('stSearchDropdown');
    if (!input || !dropdown) { return; }
    var timer = null;

    function hide() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

    function render(items) {
        if (!items.length) { hide(); return; }
        dropdown.innerHTML = items.map(function (item) {
            return '<div class="st-suggestion" data-subject="' + item.subject.replace(/"/g, '&quot;') + '" ' +
                'style="padding:6px 10px; font-size:10.5px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; border-bottom:1px solid #f3f4f6;">' +
                '<span>' + item.subject + '</span>' +
                '<span style="color:#9ca3af;">' + item.customer_name + '</span></div>';
        }).join('');
        dropdown.style.display = 'block';
        Array.prototype.forEach.call(dropdown.querySelectorAll('.st-suggestion'), function (row) {
            row.onmousedown = function () {
                input.value = row.getAttribute('data-subject');
                hide();
                input.form.submit();
            };
        });
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        if (timer) { clearTimeout(timer); }
        if (q.length < 1) { hide(); return; }
        timer = setTimeout(function () {
            fetch('{{ route('support-tickets.typeahead') }}?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); }).then(render).catch(hide);
        }, 250);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 100); });
})();
</script>
@endsection
