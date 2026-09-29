{{-- NEW 27 Sep 2026 — per Chris: Entity Maintenance search starts with the
     CBE Group, then HQ → State → Branch (only the ones that exist for that
     CBE, from the real records), each narrowing the next. Type-ahead with
     code + name. Picking a CBE Group reloads the boxes for that CBE. --}}
@php
    $tierLabels = ['hq' => __('cbe_exec.row_tier_hq'), 'state' => __('cbe_exec.row_tier_state'), 'branch' => __('cbe_exec.row_tier_branch')];
    $pickedName = function ($t) use ($tierOptions) {
        $id = (string) request($t.'_id');
        foreach ($tierOptions[$t] ?? [] as $o) { if ($o['id'] === $id) return $o['v']; }
        return '';
    };
@endphp
<div class="esb-field" style="flex:1 1 170px;">
    <label>{{ __('cbe_masterfile.hier_link_group') }}</label>
    <input type="text" id="esb-group-text" value="{{ $group->group_name ?? '' }}" list="esb-group-list" autocomplete="off" placeholder="{{ ($groupOptional ?? false) ? __('member_file.all_groups') : __('cbe_masterfile.pick_group_first') }}">
    <datalist id="esb-group-list">@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
    <input type="hidden" name="group" id="esb-group-id" value="{{ $group->group_label_id ?? '' }}">
</div>
@if($group)
@foreach(['hq', 'state', 'branch'] as $t)
@if(! empty($tierOptions[$t]))
<div class="esb-field" style="flex:1 1 150px;">
    <label>{{ $tierLabels[$t] }}</label>
    <input type="text" class="esb-tier" data-tier="{{ $t }}" value="{{ $pickedName($t) }}" list="esb-tier-{{ $t }}" autocomplete="off">
    <datalist id="esb-tier-{{ $t }}"></datalist>
    <input type="hidden" name="{{ $t }}_id" id="esb-tier-{{ $t }}-id" value="{{ request($t.'_id') }}">
</div>
@endif
@endforeach
@endif
<script>
(function(){
    var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values());
    var gText = document.getElementById('esb-group-text'), gId = document.getElementById('esb-group-id');
    gText.addEventListener('change', function(){
        var hit = groups.filter(function(g){ return g.v === gText.value; })[0];
        var base = @json($groupReloadUrl ?? route('admin.cbe-kpi.hierarchy-nodes.index'));
        // CHANGED 28 Sep 2026 — Member Maintenance: CBE Group is optional (all CBEs when blank)
        if (!hit && gText.value.trim() === '' && gId.value && @json((bool) ($groupOptional ?? false))) { window.location.href = base; return; }
        if (!hit || hit.id === gId.value) return;
        // new CBE: reload the search boxes for it (no search yet)
        window.location.href = base + '?group=' + encodeURIComponent(hit.id);
    });
    var opts = @json($tierOptions ?? []);
    var order = ['hq', 'state', 'branch'];
    function picked(t){ var h = document.getElementById('esb-tier-' + t + '-id'); return h ? h.value : ''; }
    function fill(t){
        var dl = document.getElementById('esb-tier-' + t); if (!dl) return;
        // only the options under every box picked before this one
        var ups = order.slice(0, order.indexOf(t)).map(picked).filter(Boolean);
        dl.innerHTML = '';
        (opts[t] || []).forEach(function(o){
            if (ups.some(function(u){ return o.path.indexOf('/' + u + '/') === -1; })) return;
            var op = document.createElement('option'); op.value = o.v; op.label = (o.l ? o.l + ' · ' : '') + o.v; dl.appendChild(op);
        });
    }
    document.querySelectorAll('.esb-tier').forEach(function(inp){
        var t = inp.dataset.tier;
        inp.addEventListener('change', function(){
            var hit = (opts[t] || []).filter(function(o){ return o.v === inp.value; })[0];
            document.getElementById('esb-tier-' + t + '-id').value = hit ? hit.id : '';
            // clear and re-list the boxes after this one
            order.slice(order.indexOf(t) + 1).forEach(function(n){
                var i = document.querySelector('.esb-tier[data-tier="' + n + '"]'); if (i) { i.value = ''; document.getElementById('esb-tier-' + n + '-id').value = ''; }
                fill(n);
            });
        });
        inp.addEventListener('input', function(){ if (inp.value === '') document.getElementById('esb-tier-' + t + '-id').value = ''; });
    });
    order.forEach(fill);
})();
</script>
