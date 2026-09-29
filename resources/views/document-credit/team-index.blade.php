@extends('layouts.dashboard')

@section('page-title', __('document_credit.team_title'))

@section('content')

{{-- NEW 21 Jul 2026 — read-only downline view of Document Credit
     balances for GL/TL/Introducer. GL gets a TL dropdown to narrow
     to one team; TL/Introducer are already scoped to their own
     downline server-side, so no dropdown needed for them. --}}

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box;">

    <div style="flex-shrink:0; margin-bottom:6px;">
        <div style="font-size:9.5px; color:#9ca3af; margin-top:2px;">{{ __('document_credit.team_view_only_note') }}</div>
    </div>

    <form method="GET" action="{{ route('team-document-credit.index') }}" style="position:relative; margin-bottom:8px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; flex-shrink:0;" autocomplete="off">
        @if($isGL)
        <select name="tl_id" onchange="this.form.submit()" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10px; background:#fff; min-width:180px;">
            <option value="">{{ __('document_credit.all_my_role_option', ['role' => \App\Services\RoleLabelService::plural('TEAM_LEADER')]) }}</option>
            @foreach($myTLs as $tl)
            <option value="{{ $tl->agent_id }}" {{ $tlId === $tl->agent_id ? 'selected' : '' }}>{{ $tl->full_name }} ({{ $tl->agent_code }})</option>
            @endforeach
        </select>
        @endif
        <div style="position:relative; min-width:180px; max-width:280px;">
            <input type="text" name="agent_search" id="teamDcAgentInput" value="{{ $agentSearch }}" placeholder="{{ __('document_credit.search_name_code_placeholder') }}" autocomplete="off" style="border:1px solid #d1d5db; border-radius:6px; padding:6px 8px; font-size:10.5px; width:100%; box-sizing:border-box;">
            <div id="teamDcAgentDropdown" style="display:none; position:absolute; top:100%; left:0; width:320px; max-width:80vw; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.12); max-height:220px; overflow-y:auto; z-index:50;"></div>
        </div>
        <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:6px 16px; font-size:10px; font-weight:600; cursor:pointer;">{{ __('gl.search_button') }}</button>
        @if($agentSearch || $tlId)
        <a href="{{ route('team-document-credit.index') }}" style="background:#f3f4f6; color:#374151; text-decoration:none; border-radius:6px; padding:6px 14px; font-size:10px; font-weight:600; display:flex; align-items:center;">{{ __('dashboard.clear_word') }}</a>
        @endif
    </form>

    <div style="background:#fff; border:1px solid #d1d5db; border-radius:8px; padding:10px; flex:1; min-height:0; display:flex; flex-direction:column;">
        <div style="flex:1; min-height:0; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse; font-size:9.5px;">
                <thead>
                    <tr style="background:#f0f9ff;">
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('gl.col_agent') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('network.col_code') }}</th>
                        <th style="text-align:left; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_role') }}</th>
                        <th style="text-align:right; padding:4px 8px; font-size:8.5px; color:#546E7A; text-transform:uppercase;">{{ __('document_credit.col_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teamBalances as $a)
                    <tr style="border-bottom:1px solid #f3f4f6; cursor:pointer;" onclick="window.location='{{ route('team-document-credit.agent-detail', $a->agent_id) }}'">
                        <td style="padding:5px 8px; color:#1565C0; font-weight:600;">{{ $a->full_name }} &rarr;</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->agent_code }}</td>
                        <td style="padding:5px 8px; color:#6b7280;">{{ $a->role }}</td>
                        <td style="padding:5px 8px; text-align:right; font-weight:600; {{ $a->document_credit_balance <= 0 ? 'color:#b71c1c;' : 'color:#1b5e20;' }}">RM {{ number_format($a->document_credit_balance, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="padding:16px; text-align:center; color:#9ca3af;">{{ __('document_credit.no_team_members_note') }}{{ $agentSearch ? __('document_credit.matching_search_suffix', ['term' => $agentSearch]) : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:center; padding-top:8px;">
            @if($teamBalances->onFirstPage())
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</span>
            @else
                <a href="{{ $teamBalances->previousPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.prev') }}</a>
            @endif
            <span style="font-size:9.5px; color:#6b7280;">{{ __('special_group.page_x_of_y_paren', ['current' => $teamBalances->currentPage(), 'last' => $teamBalances->lastPage(), 'total' => $teamBalances->total()]) }}</span>
            @if($teamBalances->hasMorePages())
                <a href="{{ $teamBalances->nextPageUrl() }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</a>
            @else
                <span style="background:#1565C0; color:#fff; border-radius:20px; padding:5px 14px; font-size:10px; font-weight:700;">{{ __('network.next') }}</span>
            @endif
        </div>
    </div>

</div>
<script>
(function () {
    var input = document.getElementById('teamDcAgentInput');
    var dropdown = document.getElementById('teamDcAgentDropdown');
    if (!input || !dropdown) { return; }
    var timer = null;
    var tlSelect = document.querySelector('select[name="tl_id"]');

    function hide() { dropdown.style.display = 'none'; dropdown.innerHTML = ''; }

    function render(items) {
        if (!items.length) { hide(); return; }
        dropdown.innerHTML = items.map(function (item) {
            return '<div class="team-dc-suggestion" data-name="' + item.full_name.replace(/"/g, '&quot;') + '" ' +
                'style="padding:6px 10px; font-size:10.5px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; border-bottom:1px solid #f3f4f6;">' +
                '<span>' + item.full_name + '</span>' +
                '<span style="color:#6b7280; font-family:monospace;">' + item.agent_code + '</span></div>';
        }).join('');
        dropdown.style.display = 'block';
        Array.prototype.forEach.call(dropdown.querySelectorAll('.team-dc-suggestion'), function (row) {
            row.onmousedown = function () {
                input.value = row.getAttribute('data-name');
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
            var url = '{{ route('team-document-credit.typeahead') }}?q=' + encodeURIComponent(q);
            if (tlSelect && tlSelect.value) { url += '&tl_id=' + encodeURIComponent(tlSelect.value); }
            fetch(url).then(function (r) { return r.json(); }).then(render).catch(hide);
        }, 250);
    });

    input.addEventListener('blur', function () { setTimeout(hide, 100); });
})();
</script>
@endsection
