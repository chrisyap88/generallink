@extends(session('portal') === 'glade' ? 'layouts.glade' : 'layouts.dashboard')

@section('page-title', __('cbe_masterfile.node_title'))

@section('content')

{{-- REDESIGNED 26 Sep 2026 — per Chris: "YOU SHOULD FOLLOW THE METHOD
     add search/edit METHOD, PLEASE REDESIGN" + "ADD ENTITY ALLOW ME TO
     SELECT RANGE OF POST CODE". Flow now:
       1. Pick CBE Group
       2. Landing: [Add New Entity] or [Search / View / Edit]
       3. Add: pick Level -> entity form (Postcode Coverage From/To is on
          the form, clearly boxed) -> Next -> tick-box postcode selection
          (Entity Name / Postcode / City / checkbox, all ticked) -> Save
       4. Search / View / Edit: see index.blade.php; View opens this same
          form pre-filled (mode = edit).
     Prev always bottom-left, Next/Save bottom-right, bold blue. No
     scrolling: the form is laid out in 3 columns to fit one screen. --}}
<style>
.chn-box{flex:1; min-height:0; background:#fff; border:1px solid #e2e8f0; border-left:4px solid var(--gl-blue); border-radius:10px; box-shadow:0 2px 8px rgba(21,101,192,0.08); display:flex; flex-direction:column; overflow:hidden; box-sizing:border-box;}
.chn-field{display:flex; flex-direction:column; gap:3px;}
.chn-field label{font-size:8px; font-weight:700; color:#6b7280; text-transform:uppercase;}
.chn-field input, .chn-field select{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box; width:100%;}
.chn-locked{font-family:'Poppins',sans-serif; font-size:9.5px; font-weight:700; color:var(--gl-blue); background:var(--gl-light); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 7px; box-sizing:border-box; width:100%;}
.chn-btn{background:var(--gl-blue); color:#fff; border:none; border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.chn-btn-outline{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:6px 16px; font-size:9px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;}
.chn-group-row{display:flex; align-items:center; justify-content:space-between; padding:9px 12px; border-bottom:1px solid #eef2f7; font-size:9.5px; cursor:pointer; text-decoration:none; color:#263238;}
.chn-group-row:last-child{border-bottom:none;}
.chn-group-row:hover{background:var(--gl-light);}
.chn-group-row .cnt{font-size:8.5px; color:#94A3B8; font-weight:600;}
.chn-phone-row{display:flex; gap:6px; align-items:center; margin-bottom:5px;}
.chn-phone-row input{font-family:'Poppins',sans-serif; font-size:9px; color:#263238; border:1px solid #d1d5db; border-radius:5px; padding:5px 7px; box-sizing:border-box;}
.chn-phone-add-btn{background:var(--gl-light); color:var(--gl-blue); border:1px solid var(--gl-cyan2); border-radius:5px; padding:5px 10px; font-size:8.5px; font-weight:700; cursor:pointer; align-self:flex-start; margin-top:2px;}
.chn-phone-remove-btn{background:#fdecea; color:#c62828; border:1px solid #f5c6cb; border-radius:5px; padding:4px 8px; font-size:8.5px; font-weight:700; cursor:pointer;}
.chn-cov-table{width:100%; border-collapse:collapse; font-size:9.5px; color:#263238;}
.chn-cov-table th{position:sticky; top:0; background:var(--gl-light); color:var(--gl-blue); font-size:8.5px; font-weight:700; text-transform:uppercase; text-align:left; padding:6px 10px; border-bottom:1px solid var(--gl-cyan2);}
.chn-cov-table td{padding:5px 10px; border-bottom:1px solid #eef2f7; height:18px;}
.chn-cov-table tr.chn-cov-off td{color:#94A3B8;}
.chn-cov-table input[type=checkbox]{width:14px; height:14px; margin:0; cursor:pointer; accent-color:var(--gl-blue);}
.chn-nav-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:10px; font-weight:700; padding:6px 18px; border-radius:20px; border:none; cursor:pointer;}
.chn-grid{display:grid; grid-template-columns:repeat(3, 1fr); gap:8px 12px;}
.chn-cov-box{border:1px solid var(--gl-cyan2); background:var(--gl-light); border-radius:8px; padding:8px 10px;}
.chn-back-btn{display:inline-flex; align-items:center; gap:4px; background:var(--gl-blue); color:#fff; text-decoration:none; font-size:8.5px; font-weight:700; padding:5px 14px; border-radius:5px;}
</style>

@php
    $isEdit = ($mode ?? null) === 'edit' && $node;
    $showForm = $group && $level && (($mode ?? null) === 'add' || $isEdit);
    // @json() splits on commas, so the initial ticked list is prepared here.
    $covInit = old('coverage_postcodes', $coveragePicked ?? null);
    $v = function ($field) use ($node) { return old($field, $node->{$field} ?? ''); };
    if (! $group) {
        $prevUrl = ($mode ?? null) === 'add' ? route('admin.cbe-kpi.hierarchy-nodes.create') : route('admin.cbe-kpi');
    } elseif ($isEdit) {
        // Back to wherever View / Edit was opened from (same site only).
        $ret = (string) request('return', old('return', ''));
        $prevUrl = ($ret !== '' && str_starts_with($ret, url('/')))
            ? $ret
            : route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $group->group_label_id]);
    } elseif ($level) {
        // CHANGED 27 Sep 2026 — Add form Prev: back to where it was opened from (the landing).
        $ret = (string) request('return', old('return', ''));
        $prevUrl = ($ret !== '' && str_starts_with($ret, url('/'))) ? $ret : route('admin.cbe-kpi.hierarchy-nodes.create', ['group' => $group->group_label_id]);
    } elseif (($mode ?? null) === 'add') {
        $ret = (string) request('return');
        $prevUrl = ($ret !== '' && str_starts_with($ret, url('/'))) ? $ret : route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $group->group_label_id]);
    } else {
        $prevUrl = $isOfficer ? route('admin.cbe-kpi') : route('admin.cbe-kpi.hierarchy-nodes.create');  // group landing -> main landing
    }
@endphp

<div style="height:calc(100vh - 46px); display:flex; flex-direction:column; padding:8px 16px; box-sizing:border-box; gap:7px; overflow:hidden;">

    <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <div style="min-width:0;">
            <div style="font-size:13px; font-weight:700; color:#263238; white-space:nowrap;">{{ __('cbe_masterfile.node_title') }}@if($isEdit) — {{ __('cbe_masterfile.mode_edit') }}@elseif(($mode ?? null) === 'add') — {{ __('cbe_masterfile.mode_add') }}@endif</div>
            @if($group)
            <div style="font-size:9px; color:#6b7280; white-space:nowrap;">{{ __('cbe_masterfile.hier_link_group') }}: <b style="color:#263238;">{{ $group->group_name }}</b>@if($level) &nbsp;›&nbsp; {{ $level->level_name }}@endif
                @if(! $isEdit && ! $isOfficer)&nbsp; <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', ['mode' => 'add']) }}" style="color:#1565C0; font-weight:700; text-decoration:none;">{{ __('cbe_masterfile.change_group') }}</a>@endif
            </div>
            @endif
        </div>
        @if($group && ! $mode)
        <div style="display:flex; gap:8px; flex-shrink:0;">
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.restructure', ['group' => $group->group_label_id]) }}" class="chn-btn-outline">{{ __('cbe_masterfile.link_restructure') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.link-to-group', ['source_group' => $group->group_label_id]) }}" class="chn-btn-outline">{{ __('cbe_masterfile.link_link_to_group') }}</a>
        </div>
        @endif
    </div>

    @if(session('cbe_node_saved'))
    <div style="flex-shrink:0; font-size:9px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.node_saved') }} ({{ session('cbe_node_saved') }})</div>
    @endif

    @if(! $group)
    {{-- CHANGED 27 Sep 2026 — per Chris: Entity Maintenance covers ALL CBEs.
         Straight from the sidebar: the Add | Search / View / Edit landing.
         Add asks the CBE Group first (then the entity form at that CBE's last
         level); Search starts its boxes with the CBE Group. --}}
    @if(($mode ?? null) === 'add')
    <div class="chn-box" style="justify-content:flex-start;">
        <div style="padding:14px 14px 6px; display:flex; flex-direction:column; gap:4px; max-width:420px;">
            <label style="font-size:9px; font-weight:700; color:#6b7280; text-transform:uppercase;">{{ __('cbe_masterfile.hier_link_group') }}</label>
            <input type="text" id="chn-add-group" list="chn-add-group-list" autocomplete="off" placeholder="{{ __('cbe_masterfile.pick_group_first') }}" style="font-size:11px; border:1px solid #d1d5db; border-radius:5px; padding:6px 8px;">
            <datalist id="chn-add-group-list">@foreach($groups as $g)<option value="{{ $g->group_name }}">@endforeach</datalist>
        </div>
    </div>
    <script>
    (function(){
        var groups = @json($groups->map(fn ($g) => ['v' => $g->group_name, 'id' => $g->group_label_id])->values());
        var inp = document.getElementById('chn-add-group');
        inp.addEventListener('change', function(){
            var hit = groups.filter(function(g){ return g.v === inp.value; })[0];
            if (hit) window.location.href = @json(route('admin.cbe-kpi.hierarchy-nodes.create')) + '?mode=add&group=' + encodeURIComponent(hit.id) + '&return=' + encodeURIComponent(@json(route('admin.cbe-kpi.hierarchy-nodes.create')));
        });
    })();
    </script>
    @else
    <div class="chn-box" style="justify-content:flex-start;">
        <div style="padding:14px 14px 6px; font-size:9.5px; color:#6b7280;">{{ __('cbe_masterfile.landing_prompt') }}</div>
        <div style="display:flex; gap:10px; padding:6px 14px;">
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', ['mode' => 'add']) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('cbe_masterfile.btn_add_new_entity') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.index') }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
        </div>
    </div>
    @endif

    @elseif(! $mode)
    {{-- Step 2: landing — the standard two choices --}}
    <div class="chn-box" style="justify-content:flex-start;">
        @if(session('cbe_node_saved'))
        <div style="padding:10px 14px 0; font-size:10px; color:#2e7d32; font-weight:700;">✓ {{ __('cbe_masterfile.node_saved') }} ({{ session('cbe_node_saved') }})</div>
        @endif
        <div style="padding:14px 14px 6px; font-size:9.5px; color:#6b7280;">{{ __('cbe_masterfile.landing_prompt') }}</div>
        <div style="display:flex; gap:10px; padding:6px 14px;">
            {{-- CHANGED 27 Sep 2026 — Add Entity opens the entity form at the LAST level straight away. --}}
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', array_filter(['group' => $group->group_label_id, 'level' => $bottomLevelId ?? null, 'mode' => ($bottomLevelId ?? null) ? null : 'add', 'return' => route('admin.cbe-kpi.hierarchy-nodes.create', ['group' => $group->group_label_id])])) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('cbe_masterfile.btn_add_new_entity') }}</a>
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.index', ['group' => $group->group_label_id]) }}" style="background:#1565C0; color:#fff; text-decoration:none; border-radius:6px; padding:8px 20px; font-size:12px; font-weight:600;">{{ __('masterfile.search_view_edit') }}</a>
        </div>
    </div>

    @elseif(! $level)
    {{-- Add, step 1: which level --}}
    <div class="chn-box">
        @if($levels->isEmpty())
        <div style="flex:1; display:flex; align-items:center; justify-content:center; padding:20px;">
            <div style="text-align:center; color:#94A3B8; font-size:9.5px; max-width:320px;">{{ __('cbe_masterfile.no_levels_yet') }}</div>
        </div>
        @else
        <div style="flex-shrink:0; padding:10px 12px; font-size:9px; color:#6b7280; border-bottom:1px solid #eef2f7;">{{ __('cbe_masterfile.select_level_prompt') }}</div>
        <div style="flex:1; min-height:0; overflow:hidden;">
            @foreach($levels as $lv)
            <a href="{{ route('admin.cbe-kpi.hierarchy-nodes.create', array_filter(['group' => $group->group_label_id, 'level' => $lv->level_id, 'return' => request('return')])) }}" class="chn-group-row">
                <span style="white-space:nowrap; font-weight:700;">{{ $lv->level_name }}</span>
                <span style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                    <span class="cnt">{{ $lv->node_count > 0 ? __('cbe_masterfile.level_entity_count', ['count' => $lv->node_count]) : __('cbe_masterfile.level_entity_count_zero') }}</span>
                    <span style="color:var(--gl-blue); font-weight:700;">›</span>
                </span>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    @else
    {{-- Add / Edit form (one screen, 3 columns) + postcode selection step --}}
    <div class="chn-box" style="padding:10px 12px;">
        <form id="chn-node-form" method="POST" action="{{ $isEdit ? route('admin.cbe-kpi.hierarchy-nodes.update', $node->node_id) : route('admin.cbe-kpi.hierarchy-nodes.store') }}" style="display:flex; flex-direction:column; gap:8px; height:100%; min-height:0;">
            @csrf
            @if($isEdit) @method('PUT') @endif
            @if(request('return') || old('return'))<input type="hidden" name="return" value="{{ request('return', old('return')) }}">@endif
            <input type="hidden" name="group_label_id" value="{{ $group->group_label_id }}">
            <input type="hidden" name="level_id" value="{{ $level->level_id }}">

            <div id="chn-step-form" style="display:flex; flex-direction:column; gap:8px;">
                @if($isTopLevel && $topLevelExisting->isNotEmpty())
                <div style="background:#FFF8E1; border:1px solid #FFE082; color:#8d6e00; font-size:8.5px; border-radius:6px; padding:6px 10px;">
                    {{ __('cbe_masterfile.warn_hq_exists', ['level' => $level->level_name, 'names' => $topLevelExisting->pluck('node_name')->join(', ')]) }}
                </div>
                @endif

                <div class="chn-grid">
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_level') }}</label>
                        <div class="chn-locked">{{ $level->level_name }}</div>
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_node_name') }}</label>
                        <input type="text" name="node_name" value="{{ $v('node_name') }}" required>
                        @error('node_name')<span style="font-size:8px; color:#c62828;">{{ $message }}</span>@enderror
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_node_name_zh') }}</label>
                        <input type="text" name="node_name_zh" value="{{ $v('node_name_zh') }}">
                    </div>

                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_postcode') }}</label>
                        <input type="text" name="postcode" value="{{ $v('postcode') }}" maxlength="5">
                        @error('postcode')<span style="font-size:8px; color:#c62828;">{{ $message }}</span>@enderror
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_city') }}</label>
                        <input type="text" name="city" value="{{ $v('city') }}">
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_reference_no') }}</label>
                        <input type="text" name="external_reference_no" value="{{ $v('external_reference_no') }}">
                    </div>

                    <div class="chn-field" style="grid-column:1 / -1;">
                        <label>{{ __('cbe_masterfile.form_address') }}</label>
                        <input type="text" name="address" value="{{ $v('address') }}">
                    </div>

                    {{-- NEW 27 Sep 2026 — per Chris: the upline is ALWAYS chosen here.
                         Type-ahead of entities from ALL CBE groups (code · name ·
                         level · CBE group). Blank = stand-alone / HQ pending
                         affiliation. Replaces Postcode Coverage + auto-linking. --}}
                    <div class="chn-cov-box" style="grid-column:1 / -1;">
                        <div class="chn-field">
                            <label style="color:var(--gl-blue);">{{ __('cbe_masterfile.form_affiliated_parent') }}</label>
                            <input type="hidden" name="parent_node_id" id="chn-parent-id" value="{{ old('parent_node_id', $currentParent->node_id ?? '') }}">
                            <div style="position:relative;">
                                <input type="text" id="chn-parent-text" autocomplete="off" style="width:100%;"
                                       value="{{ $currentParent ? $currentParent->node_name.' ('.($currentParent->level_name ?: '—').' · '.$currentParent->group_name.')' : '' }}"
                                       placeholder="{{ __('cbe_masterfile.form_affiliated_parent_hint') }}">
                                <div id="chn-parent-drop" style="display:none; position:absolute; left:0; right:0; top:100%; margin-top:2px; background:#fff; border:1px solid var(--gl-cyan2); border-radius:6px; box-shadow:0 6px 20px rgba(0,0,0,.15); z-index:60;"></div>
                            </div>
                            <div id="chn-parent-state" style="font-size:8.5px; color:#6b7280;"></div>
                            @error('parent_node_id')<span style="font-size:8px; color:#c62828;">{{ $message }}</span>@enderror
                        </div>
                    </div>

                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_contact_person_1') }}</label>
                        <input type="text" name="contact_person_1" value="{{ $v('contact_person_1') }}">
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_contact_person_2') }}</label>
                        <input type="text" name="contact_person_2" value="{{ $v('contact_person_2') }}">
                    </div>
                    <div class="chn-field">
                        <label>{{ __('cbe_masterfile.form_phones') }}</label>
                        <div id="chn-phone-rows">
                            @foreach(($nodePhones ?? collect()) as $ph)
                            <div class="chn-phone-row">
                                <input type="text" name="phone_number[]" value="{{ $ph->phone_number }}" placeholder="{{ __('cbe_masterfile.phone_placeholder') }}" style="flex:1;">
                                <input type="text" name="phone_note[]" value="{{ $ph->contact_note }}" placeholder="{{ __('cbe_masterfile.note_placeholder') }}" style="flex:1;">
                                <button type="button" class="chn-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" class="chn-phone-add-btn" onclick="chnAddPhoneRow()">{{ __('cbe_masterfile.btn_add_phone') }}</button>
                    </div>
                </div>

            </div>

            {{-- Postcode selection step (Entity Name / Postcode / City / tick box) --}}
            <div id="chn-step-cov" style="display:none; flex:1; min-height:0; flex-direction:column; gap:6px;">
                <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <div>
                        <div style="font-size:11px; font-weight:700; color:#263238;">{{ __('cbe_masterfile.cov_title') }}</div>
                        <div style="font-size:8.5px; color:#6b7280;">{{ __('cbe_masterfile.cov_hint') }}</div>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px; flex-shrink:0;">
                        <span id="chn-cov-count" style="font-size:8.5px; font-weight:700; color:var(--gl-blue);"></span>
                        <button type="button" class="chn-btn-outline" onclick="chnCovTickAll(true)">{{ __('cbe_masterfile.cov_tick_all') }}</button>
                        <button type="button" class="chn-btn-outline" onclick="chnCovTickAll(false)">{{ __('cbe_masterfile.cov_untick_all') }}</button>
                    </div>
                </div>
                <div id="chn-cov-wrap" style="flex:1; min-height:0; overflow:hidden; border:1px solid #eef2f7; border-radius:6px;">
                    <table class="chn-cov-table">
                        <thead><tr>
                            <th>{{ __('cbe_masterfile.cov_col_entity') }}</th>
                            <th style="width:90px;">{{ __('cbe_masterfile.cov_col_postcode') }}</th>
                            <th>{{ __('cbe_masterfile.cov_col_city') }}</th>
                            <th style="width:60px; text-align:center;">{{ __('cbe_masterfile.cov_col_select') }}</th>
                        </tr></thead>
                        <tbody id="chn-cov-body"></tbody>
                    </table>
                    <div id="chn-cov-msg" style="padding:20px; text-align:center; color:#94A3B8; font-size:9px; display:none;"></div>
                </div>
                <div style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between; gap:10px;">
                    <button type="button" class="chn-nav-btn" onclick="chnCovPrev()">{{ __('masterfile.prev') }}</button>
                    <span id="chn-cov-page" style="font-size:9px; color:#6b7280; font-weight:600;"></span>
                    <span style="display:flex; align-items:center; gap:8px;">
                        <span id="chn-cov-err" style="font-size:8px; color:#c62828; display:none;">{{ __('cbe_masterfile.cov_none_ticked') }}</span>
                        <button type="button" id="chn-cov-next" class="chn-nav-btn" onclick="chnCovNext()">{{ __('masterfile.next') }}</button>
                    </span>
                </div>
            </div>
        </form>
    </div>
    @endif

    {{-- Bottom bar: Prev left, Next/Save right (bold blue) --}}
    <div id="chn-bottom-bar" style="flex-shrink:0; display:flex; align-items:center; justify-content:space-between;">
        <a href="{{ $prevUrl }}" class="chn-nav-btn">{{ __('masterfile.prev') }}</a>
        @if($showForm)
        <button type="button" id="chn-main-btn" class="chn-nav-btn" onclick="chnMainAction()">{{ __('cbe_masterfile.btn_save_node') }}</button>
        @else
        <span></span>
        @endif
    </div>
</div>

<script>
(function(){
    window.chnAddPhoneRow = function(){
        var wrap = document.getElementById('chn-phone-rows');
        if (!wrap) return;
        var div = document.createElement('div');
        div.className = 'chn-phone-row';
        div.innerHTML = '<input type="text" name="phone_number[]" placeholder="{{ __('cbe_masterfile.phone_placeholder') }}" style="flex:1;">'
            + '<input type="text" name="phone_note[]" placeholder="{{ __('cbe_masterfile.note_placeholder') }}" style="flex:1;">'
            + '<button type="button" class="chn-phone-remove-btn" onclick="this.parentElement.remove()">✕</button>';
        wrap.appendChild(div);
    };
})();

// NEW 26 Sep 2026 — postcode coverage tick-box selection (see Blade note above).
(function(){
    var form = document.getElementById('chn-node-form');
    if (!form) return;
    var startIn = form.querySelector('[name=coverage_postcode_start]') || document.createElement('input');
    var endIn = form.querySelector('[name=coverage_postcode_end]') || document.createElement('input');
    // NEW 27 Sep 2026 — Affiliated To (Parent): live type-ahead drop-down
    // (code · name · level · CBE group) from the server; picking a row sets
    // the hidden id. Clearing the box = stand-alone / HQ pending affiliation.
    (function(){
        var txt = document.getElementById('chn-parent-text'), hid = document.getElementById('chn-parent-id');
        var st = document.getElementById('chn-parent-state'), drop = document.getElementById('chn-parent-drop');
        if (!txt) return;
        var url = @json(route('admin.cbe-kpi.parent-lookup')), nodeId = @json($node->node_id ?? null);
        var none = @json(__('cbe_masterfile.form_affiliated_none')), noMatch = @json(__('admin_cbe_directory.no_results'));
        var t, picked = txt.value;
        function esc(x){ var d = document.createElement('div'); d.textContent = x == null ? '' : x; return d.innerHTML; }
        function close(){ drop.style.display = 'none'; }
        txt.addEventListener('input', function(){
            clearTimeout(t);
            var q = txt.value.trim();
            if (q === '') { hid.value = ''; st.textContent = none; close(); return; }
            t = setTimeout(function(){
                fetch(url + '?q=' + encodeURIComponent(q) + (nodeId ? '&node=' + encodeURIComponent(nodeId) : ''), {headers: {'Accept': 'application/json'}})
                    .then(function(r){ return r.json(); })
                    .then(function(rows){
                        drop.innerHTML = '';
                        if (!rows.length) { drop.innerHTML = '<div style="padding:6px 10px; font-size:10px; color:#94A3B8;">' + esc(noMatch) + '</div>'; drop.style.display = 'block'; return; }
                        rows.forEach(function(r){
                            var d = document.createElement('div');
                            d.style.cssText = 'padding:4px 10px; font-size:10.5px; cursor:pointer; display:flex; gap:8px; align-items:baseline; white-space:nowrap;';
                            d.innerHTML = '<span style="color:var(--gl-blue); font-weight:700;">' + esc(r.code || '') + '</span><span style="font-weight:600;">' + esc(r.name) + '</span><span style="color:#6b7280;">' + esc(r.level) + ' · ' + esc(r.group) + '</span>';
                            d.addEventListener('mouseenter', function(){ d.style.background = 'var(--gl-light)'; });
                            d.addEventListener('mouseleave', function(){ d.style.background = ''; });
                            d.addEventListener('mousedown', function(e){ e.preventDefault(); txt.value = r.v; picked = r.v; hid.value = r.id; st.textContent = ''; close(); });
                            drop.appendChild(d);
                        });
                        drop.style.display = 'block';
                    }).catch(function(){});
            }, 250);
        });
        txt.addEventListener('blur', function(){ setTimeout(close, 150); if (txt.value.trim() !== '' && txt.value !== picked) { txt.value = picked; } });
        if (txt.value.trim() === '') st.textContent = none;
    })();
    var mainBtn = document.getElementById('chn-main-btn');
    var stepForm = document.getElementById('chn-step-form');
    var stepCov = document.getElementById('chn-step-cov');
    var body = document.getElementById('chn-cov-body');
    var wrap = document.getElementById('chn-cov-wrap');
    var msg = document.getElementById('chn-cov-msg');
    var pageLbl = document.getElementById('chn-cov-page');
    var countLbl = document.getElementById('chn-cov-count');
    var nextBtn = document.getElementById('chn-cov-next');
    var bottomBar = document.getElementById('chn-bottom-bar');
    var lookupUrl = @json(route('admin.cbe-kpi.hierarchy-nodes.coverage-postcodes'));
    var oldTicked = @json($covInit);
    var reopenCov = @json(old('coverage_postcodes') !== null);
    var T = {
        save: @json(__('cbe_masterfile.btn_save_node')),
        next: @json(__('cbe_masterfile.cov_next')),
        page: @json(__('cbe_masterfile.cov_page')),
        sel: @json(__('cbe_masterfile.cov_selected')),
        loading: @json(__('cbe_masterfile.cov_loading')),
        none: @json(__('cbe_masterfile.cov_none'))
    };
    var rows = [], page = 0, perPage = 15, loadedKey = null;

    function hasRange(){ return (startIn.value.trim() !== '' || endIn.value.trim() !== ''); }
    function refreshMainBtn(){ mainBtn.textContent = hasRange() ? T.next : T.save; }
    startIn.addEventListener('input', refreshMainBtn);
    endIn.addEventListener('input', refreshMainBtn);
    refreshMainBtn();

    function esc(t){ var d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; }

    function showCov(on){
        stepForm.style.display = on ? 'none' : 'flex';
        stepCov.style.display = on ? 'flex' : 'none';
        bottomBar.style.display = on ? 'none' : 'flex';
    }

    function updateCount(){
        var n = 0;
        rows.forEach(function(r){ var cb = r.querySelector('input'); if (cb.checked) n++; r.classList.toggle('chn-cov-off', !cb.checked); });
        countLbl.textContent = T.sel.replace(':count', n).replace(':total', rows.length);
    }

    function calcPerPage(){
        if (!rows.length) return;
        rows.forEach(function(r){ r.style.display = 'none'; });
        rows[0].style.display = '';
        var rowH = rows[0].offsetHeight || 29;
        var headH = wrap.querySelector('thead').offsetHeight || 26;
        perPage = Math.max(3, Math.floor((wrap.clientHeight - headH - 2) / rowH));
    }

    function render(){
        var pages = Math.max(1, Math.ceil(rows.length / perPage));
        if (page > pages - 1) page = pages - 1;
        rows.forEach(function(r, i){ r.style.display = (i >= page * perPage && i < (page + 1) * perPage) ? '' : 'none'; });
        pageLbl.textContent = rows.length ? T.page.replace(':page', page + 1).replace(':pages', pages) : '';
        nextBtn.textContent = (page >= pages - 1) ? T.save : T.next;
        document.getElementById('chn-cov-err').style.display = 'none';
    }

    function build(list){
        var entity = (form.querySelector('[name=node_name]').value || '').trim();
        body.innerHTML = '';
        rows = [];
        list.forEach(function(p){
            var val = p.postcode + '|' + (p.city || '');
            var ticked = Array.isArray(oldTicked) ? oldTicked.indexOf(val) !== -1 : true;
            var tr = document.createElement('tr');
            tr.innerHTML = '<td class="chn-cov-entity">' + esc(entity) + '</td><td>' + esc(p.postcode) + '</td><td>' + esc(p.city) + '</td>'
                + '<td style="text-align:center;"><input type="checkbox" name="coverage_postcodes[]" value="' + esc(val) + '"' + (ticked ? ' checked' : '') + '></td>';
            tr.querySelector('input').addEventListener('change', updateCount);
            body.appendChild(tr);
            rows.push(tr);
        });
        oldTicked = null;
        msg.style.display = rows.length ? 'none' : 'block';
        msg.textContent = T.none;
        page = 0;
        calcPerPage();
        updateCount();
        render();
    }

    window.chnMainAction = function(){
        // FIXED 27 Sep 2026 — the Postcode Coverage box (and its error
        // line) was removed; without this guard Save threw an error and
        // never submitted.
        var err = document.getElementById('chn-range-err') || document.createElement('span');
        err.style.display = 'none';
        if (!hasRange()) {
            if (form.reportValidity()) form.submit();
            return;
        }
        if (!form.reportValidity()) return;
        var s = startIn.value.replace(/\D/g, ''), e = endIn.value.replace(/\D/g, '');
        if (s.length !== 5 || e.length !== 5) { err.style.display = 'inline'; return; }
        var entity = (form.querySelector('[name=node_name]').value || '').trim();
        showCov(true);
        var key = s + '-' + e;
        if (loadedKey === key) {
            body.querySelectorAll('.chn-cov-entity').forEach(function(td){ td.textContent = entity; });
            calcPerPage(); page = 0; render();
            return;
        }
        body.innerHTML = ''; rows = [];
        msg.style.display = 'block'; msg.textContent = T.loading;
        pageLbl.textContent = ''; countLbl.textContent = '';
        fetch(lookupUrl + '?start=' + encodeURIComponent(s) + '&end=' + encodeURIComponent(e), {headers: {'Accept': 'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(list){ loadedKey = key; build(list || []); })
            .catch(function(){ build([]); });
    };

    window.chnCovPrev = function(){
        if (page > 0) { page--; render(); return; }
        showCov(false);
    };

    window.chnCovNext = function(){
        var pages = Math.max(1, Math.ceil(rows.length / perPage));
        if (page < pages - 1) { page++; render(); return; }
        if (rows.length && !rows.some(function(r){ return r.querySelector('input').checked; })) {
            document.getElementById('chn-cov-err').style.display = 'inline';
            return;
        }
        form.submit();
    };

    window.chnCovTickAll = function(on){
        rows.forEach(function(r){ r.querySelector('input').checked = on; });
        updateCount();
    };

    window.addEventListener('resize', function(){
        if (stepCov.style.display === 'none' || !rows.length) return;
        var first = page * perPage;
        calcPerPage();
        page = Math.floor(first / perPage);
        render();
    });

    // Coming back after a validation error with ticked postcodes kept —
    // reopen the selection screen so the unticks are not lost.
    if (reopenCov && hasRange()) { window.chnMainAction(); }
})();
</script>
@endsection
