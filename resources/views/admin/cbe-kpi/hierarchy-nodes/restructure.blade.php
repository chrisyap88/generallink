@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.restructure_title'))

@section('content')

{{-- NEW 10 Sep 2026 (Task #399) — see AdminCbeHierarchyNodeController::
     restructure()/storeRestructure() for the full reasoning. One screen,
     no scrolling, same style vocabulary as the plain Entity Maintenance
     create screen (.chn-* classes) so this looks like the same family
     of screen rather than a one-off. --}}
<style>
.chn-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.chn-field{display:flex; flex-direction:column; gap:3px;}
.chn-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.chn-field input, .chn-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.chn-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.chn-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.chn-row{display:flex; align-items:center; gap:8px; padding:6px 12px; border-bottom:1px solid #eef2f7; font-size:9px;}
.chn-row:last-child{border-bottom:none;}
.chn-group-row{display:flex; align-items:center; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #eef2f7; font-size:9.5px; cursor:pointer; text-decoration:none; color:#263238;}
.chn-group-row:last-child{border-bottom:none;}
.chn-group-row:hover{background:var(--gl-light);}
.chn-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
.chn-lvl-tag{font-size:7.5px; font-weight:800; color:var(--gl-blue); background:var(--gl-light); border-radius:4px; padding:2px 6px; white-space:nowrap;}
.chn-radio-opt{display:flex; align-items:center; gap:5px; font-size:9px; color:#263238;}
.chn-hint{font-size:8px; color:#94A3B8; line-height:1.4;}
</style>

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px;">

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_masterfile.restructure_title') }}</div>
            @if($group)
            <div style="font-size:9px; color:#6b7280; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $group->group_name }}</div>
            @endif
        </div>
        @if($group)
        <div style="display:flex; gap:8px; flex-shrink:0;">
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', ['group' => $group->group_label_id]) }}" class="chn-btn-outline">{{ __('cbe_masterfile.link_back_to_create') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $group->group_label_id]) }}" class="chn-btn-outline">{{ __('cbe_masterfile.link_link_to_group') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.restructure') }}" class="chn-btn-outline">{{ __('cbe_masterfile.btn_change_group') }}</a>
        </div>
        @endif
    </div>

    @if(session('cbe_node_saved'))
    <div style="flex-shrink:0; font-size:8.5px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.node_saved') }} ({{ session('cbe_node_saved') }})</div>
    @endif
    @error('node_name')<div style="flex-shrink:0; font-size:8.5px; color:#c62828; font-weight:700;">{{ $message }}</div>@enderror
    @error('new_level_name')<div style="flex-shrink:0; font-size:8.5px; color:#c62828; font-weight:700;">{{ $message }}</div>@enderror
    @error('level_id')<div style="flex-shrink:0; font-size:8.5px; color:#c62828; font-weight:700;">{{ $message }}</div>@enderror

    @if(! $group)
    {{-- Step 1: no CBE Group selected yet — pick one. --}}
    <div class="chn-box">
        <div style="flex-shrink:0; padding:10px 12px; font-size:8.5px; color:#94A3B8; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.select_group_prompt') }}</div>
        <div style="flex:1; min-height:0; overflow-y:auto;">
            @forelse($groups as $g)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.restructure', ['group' => $g->group_label_id]) }}" class="chn-group-row">
                <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $g->group_name }}</span>
                <span style="color:var(--gl-blue); font-weight:700;">›</span>
            </a>
            @empty
            <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
            @endforelse
        </div>
    </div>
    @else

    <div style="flex:1; min-height:0; display:flex; gap:10px;">
        {{-- Left: the new node's level + parent + details. --}}
        <div class="chn-box" style="flex:1.3;">
            <div style="flex:1; min-height:0; overflow-y:auto; padding:12px;">
                <form method="POST" action="{{ route('admin.cbe-kpi.hierarchy-nodes.restructure.store') }}" style="display:flex; flex-direction:column; gap:8px;" id="chnRestructureForm">
                    @csrf
                    <input type="hidden" name="group_label_id" value="{{ $group->group_label_id }}">

                    <div class="chn-hint">{{ __('cbe_masterfile.restructure_intro') }}</div>

                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_level_mode') }}</label>
                        <div style="display:flex; gap:14px; margin-top:2px;">
                            <label class="chn-radio-opt"><input type="radio" name="level_mode" value="existing" @checked(old('level_mode', $levels->isNotEmpty() ? 'existing' : 'new') === 'existing') onchange="chnToggleLevelMode()"> {{ __('cbe_masterfile.form_level_mode_existing') }}</label>
                            <label class="chn-radio-opt"><input type="radio" name="level_mode" value="new" @checked(old('level_mode', $levels->isNotEmpty() ? 'existing' : 'new') === 'new') onchange="chnToggleLevelMode()"> {{ __('cbe_masterfile.form_level_mode_new') }}</label>
                        </div>
                    </div>

                    <div id="chnExistingLevelWrap" class="chn-field" style="{{ $levels->isEmpty() ? 'display:none;' : '' }}">
                        <label>{{ __('cbe_masterfile.form_level') }}</label>
                        <select name="level_id">
                            @foreach($levels as $lv)
                            <option value="{{ $lv->level_id }}" @selected(old('level_id') == $lv->level_id)>{{ $lv->level_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div id="chnNewLevelWrap" style="{{ $levels->isNotEmpty() ? 'display:none;' : '' }} display:flex; flex-direction:column; gap:8px;">
                        <div class="chn-field">
                            <label>{{ __('cbe_masterfile.form_new_level_name') }}</label>
                            <input type="text" name="new_level_name" value="{{ old('new_level_name') }}" placeholder="{{ __('cbe_masterfile.form_new_level_name_placeholder') }}">
                        </div>
                        @if($levels->isNotEmpty())
                        <div style="display:flex; gap:10px;">
                            <div class="chn-field" style="flex:1;">
                                <label>{{ __('cbe_masterfile.form_new_level_position') }}</label>
                                <select name="new_level_position">
                                    <option value="above" @selected(old('new_level_position') === 'above')>{{ __('cbe_masterfile.new_level_position_above') }}</option>
                                    <option value="below" @selected(old('new_level_position', 'below') === 'below')>{{ __('cbe_masterfile.new_level_position_below') }}</option>
                                </select>
                            </div>
                            <div class="chn-field" style="flex:1.4;">
                                <label>{{ __('cbe_masterfile.form_new_level_anchor') }}</label>
                                <select name="new_level_anchor_id">
                                    @foreach($levels as $lv)
                                    <option value="{{ $lv->level_id }}" @selected(old('new_level_anchor_id') == $lv->level_id)>{{ $lv->level_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_parent') }}</label>
                        <select name="parent_node_id">
                            <option value="">{{ __('cbe_masterfile.form_parent_none') }}</option>
                            @foreach($nodes as $n)
                            <option value="{{ $n->node_id }}" @selected(old('parent_node_id') == $n->node_id)>{{ $n->level_name }} — {{ $n->node_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <div class="chn-field" style="flex:1.4; min-width:160px;">
                            <label>{{ __('cbe_masterfile.form_node_name') }}</label>
                            <input type="text" name="node_name" value="{{ old('node_name') }}" required>
                        </div>
                        <div class="chn-field" style="flex:1.4; min-width:160px;">
                            <label>{{ __('cbe_masterfile.form_node_name_zh') }}</label>
                            <input type="text" name="node_name_zh" value="{{ old('node_name_zh') }}">
                        </div>
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <div class="chn-field" style="flex:1; min-width:120px;">
                            <label>{{ __('cbe_masterfile.form_city') }}</label>
                            <input type="text" name="city" value="{{ old('city') }}">
                        </div>
                        <div class="chn-field" style="flex:1; min-width:100px;">
                            <label>{{ __('cbe_masterfile.form_postcode') }}</label>
                            <input type="text" name="postcode" value="{{ old('postcode') }}">
                        </div>
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_address') }}</label>
                        <input type="text" name="address" value="{{ old('address') }}">
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; margin-top:4px;">
                        <button type="submit" class="chn-btn">{{ __('cbe_masterfile.btn_save_restructure') }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right: existing nodes, searchable, tick which ones become
             children of the new node above. --}}
        <div class="chn-box" style="flex:1;">
            <div style="flex-shrink:0; padding:8px 12px; border-bottom:1px solid #eef2f7;">
                <div style="font-size:9.5px; font-weight:700; color:var(--gl-blue); margin-bottom:5px;">{{ __('cbe_masterfile.form_children_title') }}</div>
                <input type="text" id="chnChildSearch" placeholder="{{ __('cbe_masterfile.children_search_placeholder') }}" oninput="chnFilterChildren()" style="font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;">
            </div>
            <div style="flex:1; min-height:0; overflow-y:auto;" id="chnChildList">
                @forelse($nodes as $n)
                <label class="chn-row chn-child-row" data-name="{{ mb_strtolower($n->node_name) }}" style="cursor:pointer;">
                    <input type="checkbox" name="child_node_ids[]" value="{{ $n->node_id }}" form="chnRestructureForm" @checked(collect(old('child_node_ids', []))->contains($n->node_id))>
                    <span class="chn-lvl-tag">{{ $n->level_name }}</span>
                    <span style="flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-weight:600;">{{ $n->node_name }}</span>
                    <span style="color:#94A3B8; flex-shrink:0;">{{ $n->city }}</span>
                </label>
                @empty
                <div style="padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('admin_cbe_directory.no_results') }}</div>
                @endforelse
                <div id="chnChildNoMatches" style="display:none; padding:20px; text-align:center; color:#94A3B8; font-size:9px;">{{ __('cbe_masterfile.children_no_matches') }}</div>
            </div>
        </div>
    </div>
    @endif

    <div style="flex-shrink:0;">
        <a href="{{ route('admin.cbe-kpi') }}" class="chn-back-btn"><i class="ti ti-arrow-back-up"></i> {{ __('admin_cbe_directory.back_to_temple') }}</a>
    </div>
</div>

<script>
(function(){
    window.chnToggleLevelMode = function(){
        var mode = document.querySelector('input[name="level_mode"]:checked').value;
        document.getElementById('chnExistingLevelWrap').style.display = mode === 'existing' ? '' : 'none';
        document.getElementById('chnNewLevelWrap').style.display = mode === 'new' ? 'flex' : 'none';
    };
    window.chnFilterChildren = function(){
        var q = document.getElementById('chnChildSearch').value.trim().toLowerCase();
        var rows = document.querySelectorAll('.chn-child-row');
        var anyVisible = false;
        rows.forEach(function(row){
            var match = q === '' || row.getAttribute('data-name').indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
            if (match) anyVisible = true;
        });
        document.getElementById('chnChildNoMatches').style.display = (anyVisible || rows.length === 0) ? 'none' : 'block';
    };
})();
</script>
@endsection
