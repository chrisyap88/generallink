@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')
@section('page-title', __('masterfile.multi_tier_overrides_title'))
@section('content')
<div style="height:calc(100vh - 66px); overflow:hidden; padding:8px 12px; display:flex; flex-direction:column; gap:8px;">

    @if(session('success'))
    <div style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:6px; padding:5px 12px; color:#065f46; font-size:11px; font-weight:500; flex-shrink:0;">✅ {{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div style="background:#fee2e2; border:1px solid #fecaca; border-radius:6px; padding:5px 12px; color:#991b1b; font-size:11px; flex-shrink:0;">@foreach($errors->all() as $e) ⚠ {{ $e }}<br>@endforeach</div>
    @endif

    {{-- NEW 31 Jul 2026 — Rank system Phase 2. Admin-only screen: pick a
    specific GL or TL under this structure and give THEM their own
    envelope split, instead of the shared flat % everyone else keeps
    using. Per Chris: rare, one-off, only when that individual asks for
    it — most agents will never appear here. --}}
    <div style="background:#fff; border-radius:10px; box-shadow:0 1px 3px rgba(0,0,0,.08); flex:1; min-height:0; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:10px 16px; border-bottom:1px solid #e0f2fe; display:flex; align-items:center; justify-content:space-between; flex-shrink:0;">
            <div>
                <div style="font-size:13px; font-weight:700; color:#1565C0;">{{ __('masterfile.multi_tier_overrides_header', ['vendor' => $structure->vendor_name, 'product' => $structure->product_name]) }}</div>
                <div style="font-size:10px; color:#6b7280; margin-top:2px;">{{ __('masterfile.shared_split_note', ['gl' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), 'glPct' => number_format($structure->group_leader_pct, 2), 'tl' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER'), 'tlPct' => number_format($structure->team_leader_pct, 2), 'introducer' => \App\Services\RoleLabelService::label('INTRODUCER'), 'introPct' => number_format($structure->introducer_pct, 2)]) }}</div>
            </div>
        </div>

        <div style="padding:12px 16px; flex-shrink:0; border-bottom:1px solid #f3f4f6;">
            <label style="display:block; font-size:10px; font-weight:600; color:#374151; margin-bottom:3px;">{{ __('masterfile.setup_override_for_label', ['gl' => \App\Services\RoleLabelService::shortLabel('GROUP_LEADER'), 'tl' => \App\Services\RoleLabelService::shortLabel('TEAM_LEADER')]) }}</label>
            <div style="position:relative; max-width:400px;">
                <input type="text" id="pickAgentBox" autocomplete="off" placeholder="{{ __('masterfile.type_agent_name_code_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:7px 10px; font-size:11px; outline:none; box-sizing:border-box;">
                <div id="pickAgentList" style="position:fixed; display:none; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,.12); z-index:9999; max-height:220px; overflow-y:auto;"></div>
            </div>
        </div>

        <div style="flex:1; min-height:0; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:10.5px; table-layout:fixed;">
                <colgroup>
                    <col style="width:26%;"><col style="width:14%;">
                    <col style="width:14%;"><col style="width:16%;">
                    <col style="width:16%;"><col style="width:14%;">
                </colgroup>
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e0f2fe; position:sticky; top:0;">
                        <th style="text-align:left; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_agent') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_role') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_envelope') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_self_pct') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_downline_allocated') }}</th>
                        <th style="text-align:center; padding:8px 6px; font-weight:600; color:#374151;">{{ __('masterfile.col_action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($overrides as $o)
                    <tr style="border-bottom:1px solid #f3f4f6;">
                        <td style="padding:6px; font-weight:600; color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $o->full_name }} <span style="color:#9ca3af; font-weight:400;">({{ $o->agent_code }})</span></td>
                        <td style="padding:6px; text-align:center;">{{ \App\Services\RoleLabelService::shortLabel($o->role) }}</td>
                        <td style="padding:6px; text-align:center; color:#6b7280;">—</td>
                        <td style="padding:6px; text-align:center; font-weight:700; color:#1565C0;">{{ number_format($o->self_pct, 2) }}%</td>
                        <td style="padding:6px; text-align:center;">{{ number_format($allocatedTotals[$o->override_id] ?? 0, 2) }}%</td>
                        <td style="padding:6px; text-align:center; white-space:nowrap;">
                            <a href="{{ route('admin.masterfile.commissions.cascade-overrides.edit', [$structure->structure_id, $o->owner_agent_id]) }}" style="background:#e0f2fe; color:#1565C0; text-decoration:none; border-radius:6px; padding:3px 8px; font-size:9.5px; font-weight:600;">✏️ {{ __('masterfile.edit') }}</a>
                            <form method="POST" action="{{ route('admin.masterfile.commissions.cascade-overrides.destroy', [$structure->structure_id, $o->owner_agent_id]) }}" style="display:inline; margin:0 0 0 4px;" onsubmit="return confirm({{ json_encode(__('masterfile.remove_override_confirm', ['name' => $o->full_name])) }});">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none; border:none; color:#dc2626; font-weight:600; font-size:9.5px; cursor:pointer; padding:0;">{{ __('masterfile.del_button') }}</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center; padding:30px; color:#9ca3af;">{{ __('masterfile.no_overrides_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.masterfile.commissions', ['mode'=>'edit', 'structure_id'=>$structure->structure_id]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:5px 14px; font-size:11px; font-weight:700; white-space:nowrap; display:inline-flex; align-items:center;">{{ __('masterfile.prev') }}</a>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var TYPEAHEAD_URL = '{{ route('admin.masterfile.commissions.cascade-overrides.agent-typeahead', $structure->structure_id) }}';
    var box = document.getElementById('pickAgentBox');
    var list = document.getElementById('pickAgentList');
    var timer = null;

    box.addEventListener('input', function() {
        clearTimeout(timer);
        var q = box.value.trim();
        if (q.length < 1) { list.style.display = 'none'; return; }
        timer = setTimeout(function() {
            fetch(TYPEAHEAD_URL + '?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    list.innerHTML = '';
                    if (!data.length) { list.style.display = 'none'; return; }
                    var rect = box.getBoundingClientRect();
                    list.style.left = rect.left + 'px';
                    list.style.top = rect.bottom + 'px';
                    list.style.width = Math.max(rect.width, 250) + 'px';
                    data.forEach(function(item) {
                        var row = document.createElement('div');
                        row.style.cssText = 'padding:6px 8px; font-size:10.5px; cursor:pointer; border-bottom:1px solid #f3f4f6;';
                        row.textContent = item.label;
                        row.addEventListener('mouseover', function() { row.style.background = '#EBF5FB'; });
                        row.addEventListener('mouseout', function() { row.style.background = ''; });
                        row.addEventListener('mousedown', function() {
                            window.location = '{{ url("admin/masterfile/commissions/{$structure->structure_id}/cascade-overrides") }}/' + item.agent_id + '/edit';
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
})();
</script>
@endpush
@endsection
