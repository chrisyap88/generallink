@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.link_to_group_title'))

@section('content')

{{-- NEW 11 Sep 2026 (Task #411) — per Chris: a standalone entity (e.g. a
     Rotary Club chapter starting on its own, or a Temple not yet part of
     any federation) needs to be movable later into a different CBE
     group's real structure — "rotary club damansara can link back to
     Petaling Jaya branch ... Temple Z can link back to Klang Branch in
     Tao." Deliberately scoped to standalone (childless) entities only —
     see the controller for why a node with its own children isn't
     supported yet. Same step-by-step, one-question-at-a-time pattern as
     Entity Maintenance: source entity -> target group -> target level ->
     target parent -> save. --}}
<style>
.ltg-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.ltg-field{display:flex; flex-direction:column; gap:3px;}
.ltg-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.ltg-field input, .ltg-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.ltg-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.ltg-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.ltg-row{display:flex; align-items:center; justify-content:space-between; padding:9px 12px; border-bottom:1px solid #eef2f7; font-size:9.5px; cursor:pointer; text-decoration:none; color:#263238;}
.ltg-row:last-child{border-bottom:none;}
.ltg-row:hover{background:var(--gl-light);}
.ltg-row .lvl{font-size:7.5px; font-weight:800; color:var(--gl-blue); background:var(--gl-light); border-radius:4px; padding:2px 6px; white-space:nowrap; margin-right:8px;}
.ltg-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_masterfile.link_to_group_title') }}</div>
            @if($sourceNode)
            <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $sourceGroup->group_name }} › {{ $sourceNode->node_name }}@if($targetGroup) &nbsp;→&nbsp; {{ $targetGroup->group_name }}@if($targetLevel) › {{ $targetLevel->level_name }}@endif @endif</div>
            @endif
        </div>
    </div>

    @if(session('cbe_node_saved'))
    <div style="flex-shrink:0; font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.link_saved') }} ({{ session('cbe_node_saved') }})</div>
    @endif

    @if(! $sourceGroup)
    {{-- Step 1a: pick the source CBE group. --}}
    <div style="flex-shrink:0; font-size:8.5px; color:#94A3B8;">{{ __('cbe_masterfile.link_to_group_intro') }}</div>
    <div class="ltg-box">
        <div style="flex-shrink:0; padding:10px 12px; font-size:8.5px; color:#94A3B8; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.select_group_prompt') }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($groups as $g)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $g->group_label_id]) }}" class="ltg-row">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $g->group_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
            @endforelse
        </div>
    </div>

    @elseif(! $sourceNode)
    {{-- Step 1b: pick the source entity within that group (type-ahead search). --}}
    <div class="ltg-box">
        <div style="flex-shrink:0; padding:10px 12px; border-bottom:1px solid #eef2f7;">
            <div style="font-size:8.5px; color:#94A3B8; margin-bottom:6px;">{{ __('cbe_masterfile.select_source_node_prompt') }}</div>
            <input type="text" id="ltg-node-search" placeholder="{{ __('cbe_masterfile.source_node_search_placeholder') }}" style="width:100%; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px; font-size:9.5px; box-sizing:border-box;" oninput="ltgFilterNodes()">
        </div>
        <div style="flex:1; min-height:0; overflow-y:auto;" id="ltg-node-list">
            @forelse($sourceNodes as $n)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $sourceGroup->group_label_id, 'source_node' => $n->node_id]) }}" class="ltg-row ltg-node-row" data-name="{{ strtolower($n->node_name) }}">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><span class="lvl">{{ $n->level_name }}</span>{{ $n->node_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
            @endforelse
        </div>
    </div>

    @elseif(! $targetGroup)
    {{-- Step 2: pick the target CBE group (any group other than the source). --}}
    @if($sourceChildCount > 0)
    <div style="flex-shrink:0; background:#FDECEA; border:1px solid #F5C6CB; color:#c62828; font-size:8.5px; border-radius:6px; padding:8px 10px;">
        {{ __('cbe_masterfile.err_link_has_children', ['count' => $sourceChildCount]) }}
    </div>
    @else
    <div class="ltg-box">
        <div style="flex-shrink:0; padding:10px 12px; font-size:8.5px; color:#94A3B8; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.select_target_group_prompt') }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @php($otherGroups = $groups->where('group_label_id', '!=', $sourceGroup->group_label_id))
            @forelse($otherGroups as $g)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $sourceGroup->group_label_id, 'source_node' => $sourceNode->node_id, 'target_group' => $g->group_label_id]) }}" class="ltg-row">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $g->group_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.no_other_groups_note') }}</div>
            @endforelse
        </div>
    </div>
    @endif

    @elseif(! $targetLevel)
    {{-- Step 3: pick which level, in the target group, this entity joins at. --}}
    <div class="ltg-box">
        <div style="flex-shrink:0; padding:10px 12px; font-size:8.5px; color:#94A3B8; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.select_target_level_prompt') }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @foreach($targetLevels as $lv)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $sourceGroup->group_label_id, 'source_node' => $sourceNode->node_id, 'target_group' => $targetGroup->group_label_id, 'target_level' => $lv->level_id]) }}" class="ltg-row">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:700;">{{ $lv->level_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @endforeach
        </div>
    </div>

    @else
    {{-- CHANGED 12 Sep 2026 — per Chris: no more Target Parent dropdown
    here either — same reasoning as the Create screen. Where this entity
    lands inside the target community is worked out automatically from
    its own Postcode against the coverage ranges already set up there
    (skipping straight to whichever of City/Branch/State/HQ actually
    exists), so Step 4 is now just a plain confirm-and-save. --}}
    <div class="ltg-box">
        <div style="flex:1; min-height:0; overflow-y:auto; padding:12px;">
            <div style="background:#e8f4fd; border-radius:6px; padding:8px 10px; font-size:9px; color:#374151; margin-bottom:10px;">
                {{ __('cbe_masterfile.form_target_parent_auto_note', ['postcode' => $sourceNode->postcode ?: __('cbe_masterfile.form_parent_none')]) }}
            </div>
            <form method="POST" action="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group.store') }}" style="display:flex; flex-direction:column; gap:8px;">
                @csrf
                <input type="hidden" name="source_node_id" value="{{ $sourceNode->node_id }}">
                <input type="hidden" name="target_group_id" value="{{ $targetGroup->group_label_id }}">
                <input type="hidden" name="target_level_id" value="{{ $targetLevel->level_id }}">
                @error('target_level_id')<span style="font-size:8px; color:#c62828;">{{ $message }}</span>@enderror
                @error('target_group_id')<span style="font-size:8px; color:#c62828;">{{ $message }}</span>@enderror
                <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                    <button type="submit" class="ltg-btn">{{ __('cbe_masterfile.btn_save_link') }}</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <div style="flex-shrink:0;">
        @if($sourceNode && $targetGroup)
        <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $sourceGroup->group_label_id, 'source_node' => $sourceNode->node_id]) }}" class="ltg-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('cbe_masterfile.select_target_group_prompt') }}</a>
        @elseif($sourceNode)
        <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $sourceGroup->group_label_id]) }}" class="ltg-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('cbe_masterfile.select_source_node_prompt') }}</a>
        @elseif($sourceGroup)
        <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group') }}" class="ltg-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('cbe_masterfile.select_group_prompt') }}</a>
        @else
        <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create') }}" class="ltg-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('cbe_masterfile.link_back_to_create') }}</a>
        @endif
    </div>
</div>

<script>
function ltgFilterNodes(){
    var q = document.getElementById('ltg-node-search').value.toLowerCase();
    document.querySelectorAll('.ltg-node-row').forEach(function(row){
        row.style.display = row.getAttribute('data-name').indexOf(q) === -1 ? 'none' : 'flex';
    });
}
</script>
@endsection
