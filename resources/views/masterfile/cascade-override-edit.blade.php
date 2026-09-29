@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.multi_tier_override_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div>
                <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.override_for_header', ['name' => $owner->full_name]) }} <span style="color:#9ca3af; font-weight:400;">({{ $owner->agent_code }}, {{ \App\Services\RoleLabelService::shortLabel($owner->role) }})</span></div>
                <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.envelope_received_label', ['pct' => number_format($envelope, 2)]) }}</div>
            </div>
        </div>

        <div style="padding:12px 16px; flex:1; min-height:0; overflow-y:auto;">
            <form method="POST" action="{{ route('admin.masterfile.commissions.cascade-overrides.update', [$structure->structure_id, $owner->agent_id]) }}" autocomplete="off" id="cascadeForm">
                @csrf @method('PUT')

                <div style="max-width:260px; margin-bottom:14px;">
                    <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:2px;">{{ __('masterfile.keeps_for_themselves_label', ['name' => $owner->full_name]) }} <span style="color:#ef4444;">*</span></label>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="number" id="selfPct" name="self_pct" value="{{ old('self_pct', $override->self_pct ?? 0) }}" step="0.01" min="0" style="flex:1; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:11px; outline:none; box-sizing:border-box;" oninput="updateCascadeTotal()">
                        <span style="font-size:10px; color:#6b7280;">%</span>
                    </div>
                </div>

                @if($childRoleForNaming)
                <div style="background:#f0f9ff; border-radius:8px; padding:10px 14px; border:1px solid #e0f2fe; margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:600; color:#1565C0; margin-bottom:6px;">{{ __('masterfile.envelope_handed_to_label', ['roles' => \App\Services\RoleLabelService::shortLabel($childRoleForNaming).'s', 'name' => $owner->full_name]) }}</div>
                    <div style="font-size:10px; color:#6b7280; margin-bottom:8px;">{{ __('masterfile.add_agent_row_hint', ['role' => \App\Services\RoleLabelService::shortLabel($childRoleForNaming)]) }}</div>
                    <div id="agentRows"></div>
                    <button type="button" onclick="addAgentRow()" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:5px 14px; font-size:10.5px; font-weight:600; cursor:pointer; margin-top:4px;">{{ __('masterfile.add_role_button', ['role' => \App\Services\RoleLabelService::shortLabel($childRoleForNaming)]) }}</button>
                </div>
                @endif

                @if($ranksForOwner->isNotEmpty())
                <div style="background:#f0f9ff; border-radius:8px; padding:10px 14px; border:1px solid #e0f2fe; margin-bottom:14px;">
                    <div style="font-size:11px; font-weight:600; color:#1565C0; margin-bottom:6px;">{{ __('masterfile.envelope_split_by_rank_label', ['role' => \App\Services\RoleLabelService::shortLabel($ranksForOwner->first()->role), 'name' => $owner->full_name]) }}</div>
                    <div style="font-size:10px; color:#6b7280; margin-bottom:8px;">{{ __('masterfile.rank_split_hint', ['role' => \App\Services\RoleLabelService::shortLabel($ranksForOwner->first()->role), 'name' => $owner->full_name]) }}</div>
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px;">
                        @foreach($ranksForOwner as $rank)
                        <div>
                            <label style="display:block; font-size:9.5px; color:#374151; margin-bottom:1px;">{{ $rank->rank_no }} — {{ $rank->rank_name }}</label>
                            <input type="number" name="rank_pct[{{ $rank->rank_id }}]" class="cascadePctInput" value="{{ old('rank_pct.'.$rank->rank_id, $rankAllocations[$rank->rank_id] ?? 0) }}" step="0.01" min="0" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; font-size:10.5px; outline:none; box-sizing:border-box;" oninput="updateCascadeTotal()">
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                <div style="font-size:10px; color:#9ca3af; margin-bottom:14px;">{{ __('masterfile.no_ranks_for_group_hint', ['name' => $owner->full_name]) }}</div>
                @endif

                <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-top:1px solid #e0f2fe; margin-top:4px;">
                    <span style="font-size:11px; font-weight:600; color:#374151;">{{ __('masterfile.total_used_label') }}</span>
                    <span id="cascadeTotal" style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.cascade_total_format', ['used' => '0.00', 'envelope' => number_format($envelope, 2)]) }}</span>
                </div>
                <div id="cascadeMsg" style="font-size:10px; color:#6b7280; text-align:right; margin-bottom:10px;"></div>

                <div style="display:flex; gap:10px; align-items:center;">
                    <a href="{{ route('admin.masterfile.commissions.cascade-overrides', $structure->structure_id) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:7px 20px; font-size:12px; font-weight:700; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
                    <button type="submit" style="background:#1565C0; color:#fff; border:none; border-radius:6px; padding:7px 26px; font-size:12px; font-weight:600; cursor:pointer;">{{ __('masterfile.save_override_button') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
var cascadeI18n = {
    typeNameOrCode: @json(__('masterfile.type_agent_name_code_placeholder')),
    totalFormat: @json(__('masterfile.cascade_total_format', ['used' => ':used', 'envelope' => ':envelope'])),
    exceeds: @json(__('masterfile.exceeds_envelope_msg', ['pct' => ':pct'])),
    okLeft: @json(__('masterfile.ok_left_unallocated_msg', ['pct' => ':pct'])),
    usesFull: @json(__('masterfile.uses_full_envelope_msg'))
};
var ENVELOPE_PCT = {{ (float) $envelope }};
var OWNER_AGENT_ID = '{{ $owner->agent_id }}';
var TYPEAHEAD_URL = '{{ $childRoleForNaming ? route("admin.masterfile.commissions.cascade-overrides.agent-typeahead", $structure->structure_id) : "" }}';
var rowCounter = 0;

// Existing named-agent allocations, prefilled server-side so Edit shows
// what was already saved (not just blank rows).
var EXISTING_AGENT_ALLOCATIONS = [
    @foreach($agentAllocations as $alloc)
    { agentId: '{{ $alloc->target_agent_id }}', name: '{{ $namedAgentNames[$alloc->target_agent_id] ?? __('masterfile.unknown_agent_fallback') }}', pct: {{ (float) $alloc->pct }} },
    @endforeach
];

function addAgentRow(prefill) {
    prefill = prefill || {};
    var idx = rowCounter++;
    var row = document.createElement('div');
    row.style.cssText = 'display:flex; align-items:center; gap:6px; margin-bottom:6px; position:relative;';
    row.innerHTML =
        '<input type="text" id="agentBox_' + idx + '" autocomplete="off" placeholder="' + cascadeI18n.typeNameOrCode + '" value="' + (prefill.name || '') + '" style="flex:2; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; outline:none; box-sizing:border-box;">' +
        '<input type="hidden" name="agent_target[]" id="agentTarget_' + idx + '" value="' + (prefill.agentId || '') + '">' +
        '<input type="number" name="agent_pct[]" value="' + (prefill.pct || 0) + '" step="0.01" min="0" style="width:80px; border:1px solid #d1d5db; border-radius:5px; padding:5px 8px; font-size:10.5px; outline:none; box-sizing:border-box;" oninput="updateCascadeTotal()">' +
        '<span style="font-size:10px; color:#6b7280;">%</span>' +
        '<button type="button" onclick="this.parentNode.remove(); updateCascadeTotal();" style="background:none; border:none; color:#dc2626; font-size:14px; cursor:pointer; padding:0 4px;">✕</button>' +
        '<div id="agentList_' + idx + '" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>';
    document.getElementById('agentRows').appendChild(row);
    wireAgentTypeahead(idx);
}

function wireAgentTypeahead(idx) {
    var box = document.getElementById('agentBox_' + idx);
    var hidden = document.getElementById('agentTarget_' + idx);
    var list = document.getElementById('agentList_' + idx);
    var timer = null;

    box.addEventListener('input', function() {
        hidden.value = '';
        clearTimeout(timer);
        var q = box.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch(TYPEAHEAD_URL + '?q=' + encodeURIComponent(q) + '&parent_id=' + encodeURIComponent(OWNER_AGENT_ID))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data.length) { list.style.display = 'none'; return; }
                    var rect = box.getBoundingClientRect();
                    list.style.left = rect.left + 'px';
                    list.style.top = rect.bottom + 'px';
                    list.style.width = Math.max(rect.width, 220) + 'px';
                    data.forEach(function(item) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:5px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.label;
                        row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                        row.addEventListener('mouseout', function() { row.style.background = ''; });
                        row.addEventListener('mousedown', function() {
                            box.value = item.label;
                            hidden.value = item.agent_id;
                            list.style.display = 'none';
                        });
                        list.appendChild(row);
                    });
                    list.style.display = 'block';
                });
        }, 200);
    });

    document.addEventListener('click', function(e) {
        if (e.target !== box) { list.style.display = 'none'; }
    });
}

function updateCascadeTotal() {
    var self = parseFloat(document.getElementById('selfPct').value) || 0;
    var sum = self;
    document.querySelectorAll('input[name="agent_pct[]"]').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });
    document.querySelectorAll('.cascadePctInput').forEach(function(inp) { sum += parseFloat(inp.value) || 0; });

    var el = document.getElementById('cascadeTotal');
    var msg = document.getElementById('cascadeMsg');
    el.textContent = cascadeI18n.totalFormat.replace(':used', sum.toFixed(2)).replace(':envelope', ENVELOPE_PCT.toFixed(2));
    if (sum - ENVELOPE_PCT > 0.001) {
        el.style.color = '#dc2626';
        msg.textContent = cascadeI18n.exceeds.replace(':pct', (sum - ENVELOPE_PCT).toFixed(2));
        msg.style.color = '#dc2626';
    } else {
        el.style.color = '#16a34a';
        msg.textContent = sum < ENVELOPE_PCT ? cascadeI18n.okLeft.replace(':pct', (ENVELOPE_PCT - sum).toFixed(2)) : cascadeI18n.usesFull;
        msg.style.color = '#16a34a';
    }
}

EXISTING_AGENT_ALLOCATIONS.forEach(function(a) { addAgentRow(a); });
updateCascadeTotal();
</script>
@endpush
@endsection
