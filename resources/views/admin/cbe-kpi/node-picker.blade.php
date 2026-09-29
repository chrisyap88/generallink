@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

{{-- NEW 28 Aug 2026 — per Chris: "develop all the soon programs" — Donor
     Maintenance, Consultant Maintenance, and Member Maintenance used to
     show as "soon" for Admin because Admin has no single node (unlike an
     officer, who is tied to one community). This shared picker gives
     Admin the same group→node picker already used by Entity Maintenance
     (admin/cbe-kpi/hierarchy-nodes/create.blade.php) — pick a CBE Group,
     then pick an entity within it — and once a node is picked, the
     controller redirects into the real destination screen with
     ?node=<id>, exactly as an officer already gets automatically. --}}

@section('page-title', $pickerTitle)

@section('content')

@php
    // Safe defaults — AdminCbeMembersController / AdminCbeDonorsController
    // call this view directly (not via ResolvesCbeActiveNode) and don't
    // pass these two, so they fall back to the original all-levels,
    // no-count behaviour those two screens already had.
    $leafOnly = $leafOnly ?? false;
    $showCounts = $showCounts ?? false;
@endphp

<style>
.npk-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.npk-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.npk-row{display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #eef2f7; font-size:9.5px; cursor:pointer; text-decoration:none; color:#263238;}
.npk-row:last-child{border-bottom:none;}
.npk-row:hover{background:var(--gl-light);}
.npk-lvl-tag{font-size:7.5px; font-weight:800; color:var(--gl-blue); background:var(--gl-light); border-radius:4px; padding:2px 6px; white-space:nowrap; margin-right:8px; flex-shrink:0;}
.npk-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
.npk-count-badge{font-size:8.5px; font-weight:800; color:var(--gl-blue); background:var(--gl-light); border-radius:10px; padding:2px 9px; white-space:nowrap; flex-shrink:0;}
.npk-search-field{position:relative;}
.npk-search-field input{width:100%; font-family:'Poppins',sans-serif; font-size:10px; color:#263238; border:1px solid #d1d5db; border-radius:6px; padding:8px 10px; box-sizing:border-box;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ $pickerTitle }}</div>
            @if($group)
            <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $group->group_name }}</div>
            @endif
        </div>
        @if($group)
        <a href="{{ route($pickerRoute) }}" class="npk-btn-outline" style="flex-shrink:0;">{{ __('cbe_masterfile.picker_change_group') }}</a>
        @endif
    </div>

    @if(! $group)
    {{-- Step 1: no CBE Group selected yet — pick one. UPDATED 10 Sep
    2026 — per Chris: type-ahead search is compulsory on every search
    field across the app; this step was a plain static list, missed
    when Step 2 below got its search box. Client-side filter added
    since the group list is always small enough to load in full. --}}
    <div class="npk-box">
        <div style="flex-shrink:0; padding:10px 12px; border-bottom:1px solid #eef2f7;" class="npk-search-field">
            <input type="text" id="npk-group-search-input" placeholder="{{ __('cbe_masterfile.picker_search_group') }}" autocomplete="off">
        </div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($groups as $g)
            <a href="{{ route($pickerRoute, ['group' => $g->group_label_id]) }}" class="npk-row npk-group-row" data-name="{{ mb_strtolower($g->group_name) }}">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $g->group_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_no_groups') }}</div>
            @endforelse
            <div id="npk-group-no-matches" style="display:none; padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_no_group_matches') }}</div>
        </div>
    </div>
    @if($groups->isNotEmpty())
    <script>
    (function(){
        var input = document.getElementById('npk-group-search-input');
        var rows = document.querySelectorAll('.npk-group-row');
        var noMatches = document.getElementById('npk-group-no-matches');
        input.addEventListener('input', function(){
            var q = input.value.trim().toLowerCase();
            var visibleCount = 0;
            rows.forEach(function(row){
                var match = !q || row.getAttribute('data-name').indexOf(q) !== -1;
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });
            noMatches.style.display = visibleCount === 0 ? '' : 'none';
        });
    })();
    </script>
    @endif
    @else
    {{-- Step 2: group chosen — pick an entity/node within it.
    UPDATED 28 Aug 2026 — per Chris: "no scroll at meeting minutes... why
    display temple? no need to display just display the persatuan name
    and number of minutes at right end." When $leafOnly, this is always a
    flat list of real entities (never HQ/State/Branch groupings), so the
    level tag is dropped, and this becomes a type-to-search box instead
    of a long scrollable list — nothing renders until you type, same
    pattern as Donors/Members/Customers search screens, so it never
    needs a scrollbar even for a group with hundreds of entities.
    $showCounts adds a "(N)" badge at the right end of each match instead
    of a bare › — computed by the controller from the exact same table
    the destination screen itself lists, so the number always matches
    what you see after drilling in. --}}
    <div class="npk-box">
        @if($nodes->isEmpty())
        <div style="flex-shrink:0; padding:10px 12px; font-size:8.5px; color:#94A3B8; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.picker_select_node') }}</div>
        <div style="flex:1; display:flex; align-items:center; justify-content:center;">
            <div style="text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_no_nodes') }}</div>
        </div>
        @else
        <div style="flex-shrink:0; padding:10px 12px; border-bottom:1px solid #eef2f7;" class="npk-search-field">
            <input type="text" id="npk-search-input" placeholder="{{ __('cbe_masterfile.picker_search_node') }}" autocomplete="off">
        </div>
        <div id="npk-results" style="flex:1; min-height:0; overflow-y:auto;">
            <div id="npk-empty-hint" style="padding:24px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_type_to_search') }}</div>
        </div>
        @endif
    </div>
    @endif

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi') }}" class="npk-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_directory.back_to_temple') }}</a>
    </div>
</div>

@if($group && $nodes->isNotEmpty())
@php
    // FIXED 10 Sep 2026 — this used to build the JS array with a
    // multi-line closure directly inside @json(...), which Laravel's
    // Blade compiler failed to parse ("Unclosed '[' ... does not match
    // ')'"), breaking every screen that uses this shared picker.
    // Pre-computing the plain array here, before @json only has to
    // handle a simple variable, avoids that entirely.
    $nodesForJs = $nodes->map(function ($n) {
        return [
            'id' => $n->node_id,
            'name' => $n->node_name,
            'name_zh' => $n->node_name_zh,
            'level' => $n->level_name,
            'count' => $n->record_count ?? null,
        ];
    })->values();
@endphp
<script>
(function(){
    var isZh = {{ app()->getLocale() === 'zh' ? 'true' : 'false' }};
    var leafOnly = {{ $leafOnly ? 'true' : 'false' }};
    var showCounts = {{ $showCounts ? 'true' : 'false' }};
    var linkTpl = '{{ route($pickerRoute, ['node' => '__NODE__']) }}';
    var nodes = @json($nodesForJs);

    var input = document.getElementById('npk-search-input');
    var results = document.getElementById('npk-results');

    function primaryName(n){
        return (isZh && n.name_zh) ? n.name_zh : n.name;
    }

    // UPDATED 16 Sep 2026 — per Chris: hiding every entity behind a
    // "type to search" wall made no sense for a group with only a
    // handful of branches — he had to already know and correctly spell
    // the exact registered name before anything appeared. Small groups
    // (20 entities or fewer) now list everything immediately, same as
    // Step 1's group list already does; search still narrows it down as
    // you type. Only a genuinely large group (more than 20) keeps the
    // original type-to-search behaviour, so this still never needs a
    // scrollbar for a group with hundreds of entities.
    var SHOW_ALL_THRESHOLD = 20;

    function render(q){
        var qLower = q.toLowerCase();
        var matches;
        if (!q) {
            if (nodes.length > SHOW_ALL_THRESHOLD) {
                results.innerHTML = '<div id="npk-empty-hint" style="padding:24px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_type_to_search') }}</div>';
                return;
            }
            matches = nodes;
        } else {
            matches = nodes.filter(function(n){
                return primaryName(n).toLowerCase().indexOf(qLower) !== -1;
            }).slice(0, 30);
        }

        if (!matches.length) {
            results.innerHTML = '<div style="padding:24px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.picker_no_matches') }}</div>';
            return;
        }

        results.innerHTML = matches.map(function(n){
            var rightHtml = (showCounts && n.count !== null)
                ? '<span class="npk-count-badge">(' + n.count + ')</span>'
                : '<span style="color:var(--gl-blue); font-weight:700; flex-shrink:0;">›</span>';
            var lvlHtml = leafOnly ? '' : '<span class="npk-lvl-tag">' + n.level + '</span>';
            var href = linkTpl.replace('__NODE__', encodeURIComponent(n.id));
            return '<a href="' + href + '" class="npk-row">' +
                '<span style="display:flex; align-items:center; min-width:0;">' + lvlHtml +
                '<span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">' + primaryName(n) + '</span></span>' +
                rightHtml + '</a>';
        }).join('');
    }

    input.addEventListener('input', function(){
        render(input.value.trim());
    });

    render(''); // show the list immediately for a small group — see SHOW_ALL_THRESHOLD above
})();
</script>
@endif
@endsection
